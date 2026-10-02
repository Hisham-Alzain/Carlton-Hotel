<?php

namespace Tests\Feature\Tickets;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** PATCH /api/support-tickets/{ticket}/assign (Phase 7, TICKET-04; D-08, D-09, D-10, D-21). */
class TicketAssignTest extends TestCase
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

    private function eligible(): User
    {
        return $this->staff('tickets.respond');
    }

    private function assign(Ticket $ticket, array $body, ?User $actor = null)
    {
        $actor ??= $this->staff('tickets.assign');

        return $this->withToken($actor->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/support-tickets/{$ticket->uuid}/assign", $body);
    }

    private function mirrorsOf(Ticket $ticket): int
    {
        return collect($this->firebase->mirrors)->where('document', "ticket_{$ticket->uuid}")->count();
    }

    public function test_requires_a_token(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchJson("/api/support-tickets/{$ticket->uuid}/assign", ['user_uuid' => $this->eligible()->uuid])
            ->assertStatus(401);
    }

    public function test_tickets_respond_alone_cannot_assign(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assign($ticket, ['user_uuid' => $this->eligible()->uuid], $this->staff('tickets.respond', 'tickets.view'))
            ->assertStatus(403);
        $this->assertNull($ticket->fresh()->assigned_user_id);
    }

    public function test_user_uuid_is_required_and_must_exist(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assign($ticket, [])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['user_uuid']);

        $this->assign($ticket, ['user_uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['user_uuid']);
    }

    public function test_inactive_user_is_not_eligible(): void
    {
        $ticket   = Ticket::factory()->create();
        $inactive = $this->eligible();
        $inactive->update(['is_active' => false]);

        $this->assign($ticket, ['user_uuid' => $inactive->uuid])->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible')
            ->assertJsonPath('context.user_uuid', $inactive->uuid)
            ->assertJsonPath('context.required_permission', 'tickets.respond');
    }

    public function test_user_without_tickets_respond_is_not_eligible(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assign($ticket, ['user_uuid' => $this->staff('tickets.view')->uuid])->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible');

        $this->assertSame(0, $this->mirrorsOf($ticket));
    }

    public function test_finished_tickets_are_refused(): void
    {
        foreach (['resolved', 'closed'] as $status) {
            $ticket = Ticket::factory()->create(['status' => $status]);

            $this->assign($ticket, ['user_uuid' => $this->eligible()->uuid])->assertStatus(422)
                ->assertJsonPath('error_code', 'ticket_closed')
                ->assertJsonPath('context.status', $status);
        }
    }

    public function test_open_ticket_is_assigned(): void
    {
        $ticket = Ticket::factory()->create();
        $target = $this->eligible();

        $this->assign($ticket, ['user_uuid' => $target->uuid])
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.ticket_assigned'))
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_user.uuid', $target->uuid)
            ->assertJsonPath('data.actions.0.type', 'assignment')
            ->assertJsonPath('data.actions.0.from_status', 'open')
            ->assertJsonPath('data.actions.0.to_status', 'assigned')
            ->assertJsonPath('data.actions.0.target_user.uuid', $target->uuid);

        $this->assertSame(1, $this->mirrorsOf($ticket));
    }

    public function test_in_progress_ticket_swaps_the_assignee(): void
    {
        $ticket = Ticket::factory()->inProgress()->create(['assigned_user_id' => $this->eligible()->id]);
        $next   = $this->eligible();

        $this->assign($ticket, ['user_uuid' => $next->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assigned_user.uuid', $next->uuid)
            ->assertJsonPath('data.actions.0.from_status', null);

        $this->assertSame(1, $this->mirrorsOf($ticket));
    }

    public function test_same_assignee_is_a_no_op(): void
    {
        $owner  = $this->eligible();
        $ticket = Ticket::factory()->assignedTo($owner)->create();

        $this->assign($ticket, ['user_uuid' => $owner->uuid])
            ->assertOk()
            ->assertJsonPath('data.assigned_user.uuid', $owner->uuid)
            ->assertJsonCount(0, 'data.actions');

        $this->assertSame(0, TicketAction::count());
        $this->assertSame(0, $this->mirrorsOf($ticket));
    }

    public function test_super_admin_is_assignable(): void
    {
        $ticket = Ticket::factory()->create();
        $admin  = User::factory()->superAdmin()->create();

        $this->assign($ticket, ['user_uuid' => $admin->uuid])
            ->assertOk()
            ->assertJsonPath('data.assigned_user.uuid', $admin->uuid);
    }
}
