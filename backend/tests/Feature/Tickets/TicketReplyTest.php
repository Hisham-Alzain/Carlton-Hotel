<?php

namespace Tests\Feature\Tickets;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** POST /api/support-tickets/{ticket}/reply (Phase 7, TICKET-05; D-16, D-17, D-21). */
class TicketReplyTest extends TestCase
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

    private function reply(Ticket $ticket, array $body, ?User $actor = null)
    {
        $actor ??= $this->staff('tickets.respond');

        return $this->withToken($actor->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/support-tickets/{$ticket->uuid}/reply", $body);
    }

    public function test_requires_a_token(): void
    {
        $ticket = Ticket::factory()->create();

        $this->postJson("/api/support-tickets/{$ticket->uuid}/reply", ['body' => 'Hi'])->assertStatus(401);
    }

    public function test_tickets_view_alone_cannot_reply(): void
    {
        $ticket = Ticket::factory()->create();

        $this->reply($ticket, ['body' => 'Hi'], $this->staff('tickets.view'))->assertStatus(403);
    }

    public function test_body_is_validated(): void
    {
        $ticket = Ticket::factory()->create();

        $this->reply($ticket, [])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['body']);

        $this->reply($ticket, ['body' => str_repeat('a', 5001)])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['body']);
    }

    public function test_closed_ticket_is_refused(): void
    {
        $ticket = Ticket::factory()->closed()->create();

        $this->reply($ticket, ['body' => 'Too late'])->assertStatus(422)
            ->assertJsonPath('error_code', 'ticket_closed')
            ->assertJsonPath('context.status', 'closed');
    }

    public function test_reply_is_appended_without_status_change(): void
    {
        $actor  = $this->staff('tickets.respond');
        $ticket = Ticket::factory()->create();

        $this->reply($ticket, ['body' => 'Called the guest, waiting on engineering.'], $actor)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.ticket_replied'))
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.actions.0.type', 'reply')
            ->assertJsonPath('data.actions.0.body', 'Called the guest, waiting on engineering.')
            ->assertJsonPath('data.actions.0.actor.uuid', $actor->uuid)
            ->assertJsonPath('data.actions.0.from_status', null);

        $this->assertSame('open', $ticket->fresh()->status->value);
    }

    public function test_reply_is_allowed_on_a_resolved_ticket(): void
    {
        $ticket = Ticket::factory()->resolved()->create();

        $this->reply($ticket, ['body' => 'Follow-up call done.'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_reply_is_internal_only(): void
    {
        $conversation = Conversation::factory()->create();
        $ticket       = Ticket::factory()->create(['conversation_id' => $conversation->id, 'guest_id' => $conversation->guest_id]);
        $messages     = Message::count();

        $this->reply($ticket, ['body' => 'Internal note'])->assertStatus(201);

        $this->assertDatabaseHas('ticket_actions', ['ticket_id' => $ticket->id, 'type' => 'reply', 'message_id' => null]);
        $this->assertSame($messages, Message::count());
        $this->assertSame([], $this->firebase->mirrors);
    }
}
