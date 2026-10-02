<?php

namespace Tests\Feature\Tickets;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** PATCH /api/support-tickets/{ticket}/status (Phase 7, TICKET-03; D-06, D-07, D-10, D-21). */
class TicketStatusTest extends TestCase
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

    private function patchStatus(Ticket $ticket, array $body, ?User $actor = null)
    {
        $actor ??= $this->staff('tickets.respond');

        return $this->withToken($actor->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/support-tickets/{$ticket->uuid}/status", $body);
    }

    private function mirrorsOf(Ticket $ticket): int
    {
        return collect($this->firebase->mirrors)->where('document', "ticket_{$ticket->uuid}")->count();
    }

    public function test_requires_a_token(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchJson("/api/support-tickets/{$ticket->uuid}/status", ['status' => 'resolved'])->assertStatus(401);
    }

    public function test_tickets_view_alone_is_forbidden(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchStatus($ticket, ['status' => 'resolved'], $this->staff('tickets.view'))->assertStatus(403);
        $this->assertSame('open', $ticket->fresh()->status->value);
    }

    public function test_status_is_validated(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchStatus($ticket, [])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['status']);

        $this->patchStatus($ticket, ['status' => 'completed'])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['status']);
    }

    public function test_assigned_is_system_managed(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchStatus($ticket, ['status' => 'assigned'])->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_transition_invalid')
            ->assertJsonPath('context.from', 'open')
            ->assertJsonPath('context.to', 'assigned')
            ->assertJsonPath('context.allowed', ['in_progress', 'resolved', 'closed']);

        $this->assertSame(0, $this->mirrorsOf($ticket));
    }

    public function test_closing_an_open_ticket_needs_a_reason(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchStatus($ticket, ['status' => 'closed'])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame('open', $ticket->fresh()->status->value);
    }

    public function test_open_to_in_progress_self_assigns(): void
    {
        $actor  = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->create();

        $this->patchStatus($ticket, ['status' => 'in_progress'], $actor)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.ticket_status_updated'))
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assigned_user.uuid', $actor->uuid)
            ->assertJsonPath('data.actions.0.type', 'status_change')
            ->assertJsonPath('data.actions.0.from_status', 'open')
            ->assertJsonPath('data.actions.0.to_status', 'in_progress')
            ->assertJsonPath('data.actions.0.actor.uuid', $actor->uuid);

        $this->assertSame(1, $this->mirrorsOf($ticket));
    }

    public function test_resolve_reopen_and_close_stamp_the_ticket(): void
    {
        $ticket = Ticket::factory()->inProgress()->create();

        $this->patchStatus($ticket, ['status' => 'resolved'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->patchStatus($ticket, ['status' => 'in_progress', 'reason' => 'Guest called back'])
            ->assertOk()
            ->assertJsonPath('data.resolved_at', null)
            ->assertJsonPath('data.actions.1.body', 'Guest called back');

        $this->patchStatus($ticket, ['status' => 'closed', 'reason' => 'Handled at the desk'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
        $this->assertNotNull($ticket->fresh()->closed_at);

        $this->assertSame(3, $this->mirrorsOf($ticket));
    }

    public function test_closed_is_terminal(): void
    {
        $ticket = Ticket::factory()->closed()->create();

        $this->patchStatus($ticket, ['status' => 'in_progress', 'reason' => 'x'])->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_transition_invalid')
            ->assertJsonPath('context.allowed', []);

        $this->assertSame(0, $this->mirrorsOf($ticket));
    }
}
