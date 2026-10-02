<?php

namespace Tests\Feature\Operations;

use App\Actions\Operations\AssignRequestAction;
use App\Actions\Operations\UpdateRequestStatusAction;
use App\Contracts\FirebaseServiceInterface;
use App\Enums\ServiceRequestStatus;
use App\Enums\TicketStatus;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * The queue's ticket arm (Phase 7, OPS-03, TICKET-03; D-05, D-07, D-21, D-24,
 * council A2): ticket verbs delegate to the ticket single writers and every
 * row carries its `queue_type` path segment.
 */
class OperationsQueueTicketArmTest extends TestCase
{
    use RefreshDatabase;

    private FakeFirebaseService $firebase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->firebase = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);
    }

    private function staff(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function as(User $user)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('t')->plainTextToken)->withHeaders(['Accept-Language' => 'en']);
    }

    private function queue(): array
    {
        return $this->as($this->staff('service_requests.view', 'tickets.view', 'housekeeping.view'))
            ->getJson('/api/operations/queue')
            ->assertOk()
            ->json('data.items');
    }

    private function row(array $items, string $uuid): array
    {
        return collect($items)->firstWhere('uuid', $uuid);
    }

    private function mirrorsOf(Ticket $ticket): int
    {
        return collect($this->firebase->mirrors)->where('document', "ticket_{$ticket->uuid}")->count();
    }

    public function test_every_row_carries_queue_type(): void
    {
        $request = ServiceRequest::factory()->create();
        $ticket  = Ticket::factory()->create();
        $task    = HousekeepingTask::factory()->create();

        $items = $this->queue();

        $this->assertSame(['service-requests', 'service_request'], [$this->row($items, $request->uuid)['queue_type'], $this->row($items, $request->uuid)['type']]);
        $this->assertSame(['tickets', 'ticket'], [$this->row($items, $ticket->uuid)['queue_type'], $this->row($items, $ticket->uuid)['type']]);
        $this->assertSame(['housekeeping-tasks', 'housekeeping_task'], [$this->row($items, $task->uuid)['queue_type'], $this->row($items, $task->uuid)['type']]);
    }

    public function test_ticket_rows_carry_their_room_number(): void
    {
        $room    = Room::factory()->create(['number' => '305']);
        $trashed = Room::factory()->create(['number' => '410']);
        $onRoom  = Ticket::factory()->create(['room_id' => $room->id]);
        $noRoom  = Ticket::factory()->create();
        $onGone  = Ticket::factory()->create(['room_id' => $trashed->id]);
        $trashed->delete();

        $items = $this->queue();

        $this->assertSame('305', $this->row($items, $onRoom->uuid)['room_number']);
        $this->assertNull($this->row($items, $noRoom->uuid)['room_number']);
        $this->assertSame('410', $this->row($items, $onGone->uuid)['room_number']);
    }

    public function test_queue_lists_the_four_active_ticket_statuses(): void
    {
        foreach (TicketStatus::cases() as $status) {
            Ticket::factory()->create(['status' => $status]);
        }

        $statuses = collect($this->queue())->where('type', 'ticket')->pluck('status')->sort()->values()->all();

        $this->assertSame(['assigned', 'in_progress', 'open', 'waiting_guest'], $statuses);
    }

    public function test_ticket_allowed_statuses_are_enforced_targets(): void
    {
        $inProgress = Ticket::factory()->inProgress()->create();
        $assigned   = Ticket::factory()->assignedTo($this->staff('tickets.respond'))->create();

        $items = $this->queue();

        $this->assertSame(['waiting_guest', 'resolved', 'closed'], $this->row($items, $inProgress->uuid)['allowed_statuses']);
        $this->assertSame(['in_progress', 'waiting_guest', 'resolved', 'closed'], $this->row($items, $assigned->uuid)['allowed_statuses']);
    }

    public function test_queue_status_writes_the_timeline(): void
    {
        $actor  = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->create();

        $this->as($actor)->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.queue_type', 'tickets')
            ->assertJsonPath('data.assigned_user_uuid', $actor->uuid);

        $row = TicketAction::where('ticket_id', $ticket->id)->sole();
        $this->assertSame('status_change', $row->type->value);
        $this->assertSame($actor->id, $row->user_id);
    }

    public function test_queue_status_to_assigned_is_422(): void
    {
        $ticket = Ticket::factory()->create();

        $this->as($this->staff('tickets.respond'))
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/status", ['status' => 'assigned'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_transition_invalid');

        $this->assertSame(TicketStatus::OPEN, $ticket->fresh()->status);
    }

    public function test_queue_close_needs_a_reason(): void
    {
        $ticket = Ticket::factory()->create();
        $actor  = $this->staff('tickets.respond');

        $this->as($actor)->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/status", ['status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['reason']);

        $this->as($actor)->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/status", ['status' => 'closed', 'reason' => 'duplicate'])
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->closed_at);
        $this->assertSame('duplicate', TicketAction::where('ticket_id', $ticket->id)->sole()->body);
    }

    public function test_queue_assign_delegates(): void
    {
        $supervisor = $this->staff('tickets.assign');
        $eligible   = $this->staff('tickets.respond');
        $ticket     = Ticket::factory()->create();

        $this->as($supervisor)->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/assign", ['user_uuid' => $eligible->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_user_uuid', $eligible->uuid);
        $this->assertSame('assignment', TicketAction::where('ticket_id', $ticket->id)->sole()->type->value);

        $this->as($supervisor)->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/assign", ['user_uuid' => $this->staff('tickets.view')->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible');

        $resolved = Ticket::factory()->resolved()->create();
        $this->as($supervisor)->patchJson("/api/operations/queue/tickets/{$resolved->uuid}/assign", ['user_uuid' => $eligible->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_closed');
    }

    public function test_ticket_queue_writes_mirror_exactly_once(): void
    {
        $ticket     = Ticket::factory()->create();
        $worker     = $this->staff('tickets.respond');
        $supervisor = $this->staff('tickets.assign');
        $colleague  = $this->staff('tickets.respond');

        $this->as($worker)
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/status", ['status' => 'in_progress'])->assertOk();
        $this->assertSame(1, $this->mirrorsOf($ticket));

        $this->as($supervisor)
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/assign", ['user_uuid' => $colleague->uuid])->assertOk();
        $this->assertSame(2, $this->mirrorsOf($ticket));
    }

    public function test_ticket_arms_require_an_actor(): void
    {
        $ticket = Ticket::factory()->create();

        try {
            app(UpdateRequestStatusAction::class)->handle($ticket, 'resolved');
            $this->fail('the ticket status arm needs an actor (A2)');
        } catch (LogicException) {
        }

        try {
            app(AssignRequestAction::class)->handle($ticket, $this->staff('tickets.respond'));
            $this->fail('the ticket assign arm needs an actor (A2)');
        } catch (LogicException) {
        }

        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::OPEN, $fresh->status);
        $this->assertNull($fresh->assigned_user_id);
        $this->assertSame(0, TicketAction::count());
    }

    public function test_service_request_arm_still_accepts_a_null_actor(): void
    {
        $request = ServiceRequest::factory()->create();

        $result = app(UpdateRequestStatusAction::class)->handle($request, 'in_progress');

        $this->assertSame(200, $result['code']);
        $this->assertSame(ServiceRequestStatus::IN_PROGRESS, $request->fresh()->status);
    }

    public function test_summary_counts_the_new_ticket_statuses(): void
    {
        Ticket::factory()->inProgress()->create();
        Ticket::factory()->waitingGuest()->create();

        $this->as($this->staff('tickets.view'))->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.tickets.in_progress', 1)
            ->assertJsonPath('data.tickets.waiting_guest', 1);
    }
}
