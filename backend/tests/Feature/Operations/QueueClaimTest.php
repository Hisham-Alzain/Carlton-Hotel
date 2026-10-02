<?php

namespace Tests\Feature\Operations;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TicketActionType;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RecordsRowLocks;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * PATCH /api/operations/queue/{type}/{uuid}/claim (Phase 7, OPS-01; D-22,
 * D-25, council A1, A8, A9, PR-1).
 *
 * A claim is an assignment to oneself made inside the type's own assign
 * writer and lock: closed check → ClaimGuard → assign. Each type answers a
 * terminal item with its own closed code; there is no shared claim-closed code.
 *
 * Lock caveat (council A9): the RecordsRowLocks assertions prove only that
 * each claim issues `for update` on the right rows in the right order. SQLite
 * never blocks, so concurrent-claim serialisation is verified on MySQL only.
 */
class QueueClaimTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

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

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function claim(User $user, string $type, string $uuid): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/operations/queue/{$type}/{$uuid}/claim");
    }

    private function mirrors(string $document): int
    {
        return collect($this->firebase->mirrors)->where('document', $document)->count();
    }

    // ── common ────────────────────────────────────────────────────────

    public function test_requires_a_token(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/claim")
            ->assertStatus(401)->assertJson(['success' => false, 'error_code' => 'unauthorized']);
    }

    public function test_unknown_type_is_404(): void
    {
        $this->claim($this->staff('tickets.respond'), 'nope', (string) \Illuminate\Support\Str::uuid())
            ->assertStatus(404)->assertJson(['success' => false, 'error_code' => 'not_found']);
    }

    public function test_unknown_uuid_is_404(): void
    {
        $this->claim($this->staff('tickets.respond'), 'tickets', (string) \Illuminate\Support\Str::uuid())
            ->assertStatus(404)->assertJson(['success' => false, 'error_code' => 'not_found']);
    }

    public function test_without_any_permission_is_403(): void
    {
        $ticket = Ticket::factory()->create();

        $this->claim($this->staff(), 'tickets', $ticket->uuid)
            ->assertStatus(403)->assertJson(['error_code' => 'forbidden']);
    }

    // ── tickets ───────────────────────────────────────────────────────

    public function test_claims_an_unassigned_open_ticket(): void
    {
        $caller = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->create();

        $this->claim($caller, 'tickets', $ticket->uuid)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => __('custom.messages.queue_item_claimed', [], 'en'),
                'data'    => [
                    'uuid'               => $ticket->uuid,
                    'queue_type'         => 'tickets',
                    'status'             => 'assigned',
                    'assigned_user_uuid' => $caller->uuid,
                ],
            ]);

        $actions = TicketAction::where('ticket_id', $ticket->id)->where('type', TicketActionType::ASSIGNMENT)->get();
        $this->assertCount(1, $actions);
        $this->assertSame(['claim' => true], $actions->first()->meta);
        $this->assertSame($caller->id, $actions->first()->target_user_id);
        $this->assertSame(1, $this->mirrors("ticket_{$ticket->uuid}"));
    }

    public function test_claiming_an_in_progress_ticket_keeps_its_status(): void
    {
        $caller = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->inProgress()->create();

        $this->claim($caller, 'tickets', $ticket->uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assigned_user_uuid', $caller->uuid);
    }

    public function test_reclaiming_own_ticket_is_a_no_op(): void
    {
        $caller = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->create();
        $this->claim($caller, 'tickets', $ticket->uuid)->assertOk();
        $actions = TicketAction::where('ticket_id', $ticket->id)->count();
        $mirrors = $this->mirrors("ticket_{$ticket->uuid}");

        $this->claim($caller, 'tickets', $ticket->uuid)
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.queue_item_already_yours', [], 'en'))
            ->assertJsonPath('data.assigned_user_uuid', $caller->uuid);

        $this->assertSame($actions, TicketAction::where('ticket_id', $ticket->id)->count());
        $this->assertSame($mirrors, $this->mirrors("ticket_{$ticket->uuid}"));
    }

    public function test_someone_elses_ticket_is_409(): void
    {
        $first  = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->create();
        $this->claim($first, 'tickets', $ticket->uuid)->assertOk();

        $this->claim($this->staff('tickets.respond'), 'tickets', $ticket->uuid)
            ->assertStatus(409)
            ->assertJson([
                'success'    => false,
                'error_code' => 'queue_item_already_claimed',
                'context'    => ['assigned_user_uuid' => $first->uuid],
            ]);

        $this->assertSame($first->id, $ticket->fresh()->assigned_user_id);
    }

    public function test_resolved_ticket_is_ticket_closed(): void
    {
        $ticket = Ticket::factory()->resolved()->create();

        $this->claim($this->staff('tickets.respond'), 'tickets', $ticket->uuid)
            ->assertStatus(422)
            ->assertJson(['error_code' => 'ticket_closed', 'context' => ['status' => 'resolved']]);
    }

    public function test_own_closed_ticket_is_ticket_closed_not_a_no_op(): void
    {
        $caller = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->closed()->create(['assigned_user_id' => $caller->id]);

        $this->claim($caller, 'tickets', $ticket->uuid)
            ->assertStatus(422)
            ->assertJson(['error_code' => 'ticket_closed', 'context' => ['status' => 'closed']]);
    }

    public function test_assign_permission_alone_cannot_claim_a_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $this->claim($this->staff('tickets.view', 'tickets.assign'), 'tickets', $ticket->uuid)
            ->assertStatus(403)->assertJson(['error_code' => 'forbidden']);

        $this->assertNull($ticket->fresh()->assigned_user_id);
    }

    public function test_ticket_claim_locks_the_ticket_row(): void
    {
        $caller = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->create();

        $this->assertLocksRow('tickets', fn () => $this->claim($caller, 'tickets', $ticket->uuid)->assertOk());
    }

    // ── housekeeping tasks ────────────────────────────────────────────

    public function test_claims_a_pending_housekeeping_task(): void
    {
        $caller = $this->staff('housekeeping.update');
        $task   = HousekeepingTask::factory()->create();

        $this->claim($caller, 'housekeeping-tasks', $task->uuid)
            ->assertOk()
            ->assertJson([
                'message' => __('custom.messages.queue_item_claimed', [], 'en'),
                'data'    => ['queue_type' => 'housekeeping-tasks', 'status' => 'assigned', 'assigned_user_uuid' => $caller->uuid],
            ]);

        $history = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->get();
        $this->assertCount(1, $history);
        $this->assertSame(['pending', 'assigned', 'claimed', $caller->id], [
            $history[0]->from_status instanceof \BackedEnum ? $history[0]->from_status->value : $history[0]->from_status,
            $history[0]->to_status instanceof \BackedEnum ? $history[0]->to_status->value : $history[0]->to_status,
            $history[0]->reason,
            $history[0]->changed_by,
        ]);
        $this->assertSame(1, $this->mirrors("housekeeping_task_{$task->uuid}"));
    }

    public function test_reception_preset_cannot_claim_a_housekeeping_task(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->claim($this->withRole('reception'), 'housekeeping-tasks', $task->uuid)
            ->assertStatus(403)->assertJson(['error_code' => 'forbidden']);
    }

    public function test_done_housekeeping_task_is_housekeeping_task_closed(): void
    {
        $task = HousekeepingTask::factory()->done()->create();

        $this->claim($this->staff('housekeeping.update'), 'housekeeping-tasks', $task->uuid)
            ->assertStatus(422)
            ->assertJson(['error_code' => 'housekeeping_task_closed', 'context' => ['status' => 'done']]);
    }

    public function test_someone_elses_housekeeping_task_is_409(): void
    {
        $other = $this->staff('housekeeping.update');
        $task  = HousekeepingTask::factory()->assigned($other)->create();

        $this->claim($this->staff('housekeeping.update'), 'housekeeping-tasks', $task->uuid)
            ->assertStatus(409)
            ->assertJson(['error_code' => 'queue_item_already_claimed', 'context' => ['assigned_user_uuid' => $other->uuid]]);

        $this->assertSame($other->id, $task->fresh()->assigned_user_id);
    }

    public function test_reclaiming_own_housekeeping_task_is_a_no_op(): void
    {
        $caller = $this->staff('housekeeping.update');
        $task   = HousekeepingTask::factory()->assigned($caller)->create();

        $this->claim($caller, 'housekeeping-tasks', $task->uuid)
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.queue_item_already_yours', [], 'en'));

        $this->assertSame(0, HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->count());
        $this->assertSame(0, $this->mirrors("housekeeping_task_{$task->uuid}"));
    }

    public function test_housekeeping_claim_locks_room_before_task(): void
    {
        $caller = $this->staff('housekeeping.update');
        $task   = HousekeepingTask::factory()->create();

        $locked = $this->lockedSelects(fn () => $this->claim($caller, 'housekeeping-tasks', $task->uuid)->assertOk());

        $tables = array_values(array_filter(array_map(
            fn (string $sql) => str_contains($sql, 'from "rooms"') ? 'rooms' : (str_contains($sql, 'from "housekeeping_tasks"') ? 'housekeeping_tasks' : null),
            $locked,
        )));

        $this->assertSame(['rooms', 'housekeeping_tasks'], array_slice($tables, 0, 2));
    }

    // ── service requests ──────────────────────────────────────────────

    public function test_kitchen_preset_claims_a_new_service_request(): void
    {
        $caller  = $this->withRole('kitchen');
        $request = ServiceRequest::factory()->create();

        $this->claim($caller, 'service-requests', $request->uuid)
            ->assertOk()
            ->assertJson([
                'message' => __('custom.messages.queue_item_claimed', [], 'en'),
                'data'    => ['queue_type' => 'service-requests', 'status' => 'new', 'assigned_user_uuid' => $caller->uuid],
            ]);

        $this->assertSame(ServiceRequestStatus::NEW, $request->fresh()->status);
        $this->assertSame(1, $this->mirrors("service_request_{$request->uuid}"));
    }

    public function test_assign_permission_alone_cannot_claim_a_service_request(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->claim($this->staff('service_requests.view', 'service_requests.assign'), 'service-requests', $request->uuid)
            ->assertStatus(403)->assertJson(['error_code' => 'forbidden']);
    }

    public function test_completed_service_request_is_service_request_closed(): void
    {
        $request = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::COMPLETED]);

        $this->claim($this->staff('service_requests.update'), 'service-requests', $request->uuid)
            ->assertStatus(422)
            ->assertJson(['error_code' => 'service_request_closed', 'context' => ['status' => 'completed']]);
    }

    public function test_someone_elses_service_request_is_409(): void
    {
        $other   = $this->staff('service_requests.update');
        $request = ServiceRequest::factory()->create(['assigned_user_id' => $other->id]);

        $this->claim($this->staff('service_requests.update'), 'service-requests', $request->uuid)
            ->assertStatus(409)
            ->assertJson(['error_code' => 'queue_item_already_claimed', 'context' => ['assigned_user_uuid' => $other->uuid]]);
    }

    public function test_reclaiming_own_service_request_is_a_no_op(): void
    {
        $caller  = $this->staff('service_requests.update');
        $request = ServiceRequest::factory()->create(['assigned_user_id' => $caller->id]);

        $this->claim($caller, 'service-requests', $request->uuid)
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.queue_item_already_yours', [], 'en'));

        $this->assertSame(0, $this->mirrors("service_request_{$request->uuid}"));
    }

    public function test_service_request_claim_locks_the_request_row(): void
    {
        $caller  = $this->staff('service_requests.update');
        $request = ServiceRequest::factory()->create();

        $this->assertLocksRow('service_requests', fn () => $this->claim($caller, 'service-requests', $request->uuid)->assertOk());
    }

    // ── per-type closed codes (council A1) ────────────────────────────

    public function test_each_type_reports_its_own_closed_code(): void
    {
        $caller = $this->staff('tickets.respond', 'housekeeping.update', 'service_requests.update');

        $codes = [
            $this->claim($caller, 'tickets', Ticket::factory()->resolved()->create()->uuid)->json('error_code'),
            $this->claim($caller, 'housekeeping-tasks', HousekeepingTask::factory()->cancelled()->create()->uuid)->json('error_code'),
            $this->claim($caller, 'service-requests', ServiceRequest::factory()->create(['status' => ServiceRequestStatus::CANCELLED])->uuid)->json('error_code'),
        ];

        $this->assertSame(['ticket_closed', 'housekeeping_task_closed', 'service_request_closed'], $codes);
    }
}
