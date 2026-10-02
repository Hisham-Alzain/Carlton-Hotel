<?php

namespace Tests\Feature\Tickets;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** POST /api/support-tickets/{ticket}/escalate (Phase 7, TICKET-07; D-10, D-18..D-21, PR-2). */
class TicketEscalateTest extends TestCase
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

    private function escalate(Ticket $ticket, array $body, ?User $actor = null)
    {
        $actor ??= $this->staff('tickets.respond');

        return $this->withToken($actor->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/support-tickets/{$ticket->uuid}/escalate", $body);
    }

    public function test_requires_a_token(): void
    {
        $ticket = Ticket::factory()->create();

        $this->postJson("/api/support-tickets/{$ticket->uuid}/escalate", ['user_uuid' => $this->eligible()->uuid, 'reason' => 'VIP'])
            ->assertStatus(401);
    }

    public function test_view_or_assign_alone_cannot_escalate(): void
    {
        $ticket = Ticket::factory()->create();
        $body   = ['user_uuid' => $this->eligible()->uuid, 'reason' => 'VIP guest'];

        $this->escalate($ticket, $body, $this->staff('tickets.view'))->assertStatus(403);
        $this->escalate($ticket, $body, $this->staff('tickets.assign'))->assertStatus(403);
        $this->assertSame(0, $ticket->fresh()->escalation_level);
    }

    public function test_body_is_validated(): void
    {
        $ticket = Ticket::factory()->create();
        $target = $this->eligible()->uuid;

        $this->escalate($ticket, ['reason' => 'VIP guest'])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['user_uuid']);

        $this->escalate($ticket, ['user_uuid' => $target, 'reason' => 'ab'])->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->escalate($ticket, ['user_uuid' => $target, 'reason' => str_repeat('a', 1001)])->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_self_escalation_is_invalid(): void
    {
        $actor  = $this->eligible();
        $ticket = Ticket::factory()->create();

        $this->escalate($ticket, ['user_uuid' => $actor->uuid, 'reason' => 'VIP guest'], $actor)->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_escalation_invalid')
            ->assertJsonPath('context.reason', 'self');
    }

    public function test_same_assignee_is_invalid(): void
    {
        $owner  = $this->eligible();
        $ticket = Ticket::factory()->assignedTo($owner)->create();

        $this->escalate($ticket, ['user_uuid' => $owner->uuid, 'reason' => 'VIP guest'])->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_escalation_invalid')
            ->assertJsonPath('context.reason', 'same_assignee');
    }

    public function test_cap_is_enforced(): void
    {
        config(['hotel.ticket_max_escalation_level' => 1]);
        $ticket = Ticket::factory()->escalated(1)->create();

        $this->escalate($ticket, ['user_uuid' => $this->eligible()->uuid, 'reason' => 'VIP guest'])->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_escalation_limit')
            ->assertJsonPath('context.level', 1)
            ->assertJsonPath('context.max', 1);
    }

    public function test_ineligible_target_is_refused(): void
    {
        $ticket = Ticket::factory()->create();

        $this->escalate($ticket, ['user_uuid' => $this->staff('tickets.view')->uuid, 'reason' => 'VIP guest'])->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible');
    }

    public function test_closed_ticket_is_refused(): void
    {
        $ticket = Ticket::factory()->closed()->create();

        $this->escalate($ticket, ['user_uuid' => $this->eligible()->uuid, 'reason' => 'VIP guest'])->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_closed');
    }

    public function test_escalation_happy_path(): void
    {
        $ticket = Ticket::factory()->create();
        $target = $this->eligible();

        $response = $this->escalate($ticket, ['user_uuid' => $target->uuid, 'reason' => 'Guest is VIP'])
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.ticket_escalated'))
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.escalation_level', 1)
            ->assertJsonPath('data.assigned_user.uuid', $target->uuid)
            ->assertJsonPath('data.latest_escalation.level', 1)
            ->assertJsonPath('data.latest_escalation.target_user.uuid', $target->uuid)
            ->assertJsonPath('data.latest_escalation.reason', 'Guest is VIP')
            ->assertJsonPath('data.actions.0.type', 'escalation');

        $this->assertNotNull($response->json('data.latest_escalation.created_at'));
        $this->assertSame(1, collect($this->firebase->mirrors)->where('document', "ticket_{$ticket->uuid}")->count());
    }

    public function test_body_level_is_ignored(): void
    {
        $ticket = Ticket::factory()->create();

        $this->escalate($ticket, ['user_uuid' => $this->eligible()->uuid, 'reason' => 'VIP guest', 'level' => 99])
            ->assertOk()
            ->assertJsonPath('data.escalation_level', 1);

        $this->assertSame(1, $ticket->fresh()->escalation_level);
    }

    public function test_escalation_sends_no_notification(): void
    {
        Notification::fake();
        $ticket = Ticket::factory()->create();

        $this->escalate($ticket, ['user_uuid' => $this->eligible()->uuid, 'reason' => 'VIP guest'])->assertOk();

        Notification::assertNothingSent();
        $this->assertSame([], $this->firebase->pushes);
    }
}
