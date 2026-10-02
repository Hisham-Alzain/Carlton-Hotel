<?php

namespace Tests\Feature\Tickets;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\TicketActionType;
use App\Enums\TicketRecoveryType;
use App\Enums\TicketStatus;
use App\Http\Resources\Tickets\TicketResource;
use App\Models\Conversation;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\TicketRecovery;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** GET /api/support-tickets/{ticket} (Phase 7, TICKET-01; D-13, A7, PR-2, PR-3, PR-8). */
class TicketShowTest extends TestCase
{
    use RefreshDatabase;

    private const TICKET_KEYS = [
        'uuid', 'subject', 'description', 'category', 'status', 'priority', 'department', 'source',
        'escalation_level', 'allowed_statuses', 'guest', 'reservation', 'room', 'conversation_uuid',
        'assigned_user', 'created_by', 'folio_credit_total_usd', 'recorded_value_usd',
        'resolved_at', 'closed_at', 'created_at', 'updated_at', 'actions', 'actions_truncated', 'latest_escalation',
    ];

    private const ACTION_KEYS = [
        'uuid', 'type', 'body', 'from_status', 'to_status', 'actor', 'target_user', 'recovery', 'meta', 'created_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService());
    }

    private function token(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function show(Ticket $ticket, ?string $token = null)
    {
        return $this->withToken($token ?? $this->token('tickets.view'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/support-tickets/{$ticket->uuid}");
    }

    /** @return array{Ticket, array<string, mixed>} */
    private function linkedTicket(): array
    {
        $reservation = Reservation::factory()->create();
        $room        = Room::factory()->create();
        $assignee    = User::factory()->create();
        $creator     = User::factory()->create();
        $target      = User::factory()->create();
        $conversation = Conversation::factory()->create(['guest_id' => $reservation->guest_id]);

        $ticket = Ticket::factory()->withReservation($reservation)->assignedTo($target)->escalated(1)->create([
            'room_id'         => $room->id,
            'conversation_id' => $conversation->id,
            'created_by'      => $creator->id,
            'description'     => 'The AC is loud',
        ]);

        $t0 = Carbon::parse('2027-03-12 10:00:00');
        $mk = fn (int $i, array $attrs) => TicketAction::factory()->create(
            ['ticket_id' => $ticket->id, 'created_at' => $t0->copy()->addMinutes($i)] + $attrs,
        );

        $mk(0, ['type' => TicketActionType::CREATED, 'to_status' => 'open', 'user_id' => $creator->id]);
        $mk(1, ['type' => TicketActionType::STATUS_CHANGE, 'from_status' => 'open', 'to_status' => 'in_progress', 'body' => 'Working', 'user_id' => $assignee->id]);
        $mk(2, ['type' => TicketActionType::ASSIGNMENT, 'to_status' => null, 'target_user_id' => $assignee->id, 'user_id' => $assignee->id, 'meta' => ['claim' => true]]);
        $mk(3, ['type' => TicketActionType::ESCALATION, 'to_status' => null, 'target_user_id' => $target->id, 'user_id' => $assignee->id, 'body' => 'Guest is VIP', 'meta' => ['level' => 1, 'previous_assignee_uuid' => $assignee->uuid]]);
        $mk(4, ['type' => TicketActionType::REPLY, 'to_status' => null, 'body' => 'Called the guest', 'user_id' => $target->id]);

        $credit = FolioItem::factory()->credit()->create(['amount_usd' => '-25.00']);
        $creditAction = $mk(5, ['type' => TicketActionType::RECOVERY, 'to_status' => null, 'user_id' => $target->id]);
        TicketRecovery::factory()->folioCredit($credit)->create(['ticket_action_id' => $creditAction->id, 'description' => 'Goodwill']);

        $upgradeAction = $mk(6, ['type' => TicketActionType::RECOVERY, 'to_status' => null, 'user_id' => $target->id]);
        TicketRecovery::factory()->create([
            'ticket_action_id' => $upgradeAction->id,
            'type'             => TicketRecoveryType::ROOM_UPGRADE,
            'amount_usd'       => '40.00',
        ]);

        return [$ticket, compact('reservation', 'room', 'assignee', 'creator', 'target', 'conversation', 'credit')];
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/support-tickets/' . Ticket::factory()->create()->uuid)->assertStatus(401);
    }

    public function test_respond_without_view_is_forbidden(): void
    {
        $this->show(Ticket::factory()->create(), $this->token('tickets.respond'))->assertStatus(403);
    }

    public function test_unknown_ticket_is_404(): void
    {
        $this->withToken($this->token('tickets.view'))
            ->getJson('/api/support-tickets/00000000-0000-4000-8000-000000000000')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_show_returns_the_full_shape(): void
    {
        [$ticket, $f] = $this->linkedTicket();

        $response = $this->show($ticket)->assertOk()->assertJsonPath('success', true);
        $data     = $response->json('data');

        $this->assertSame(self::TICKET_KEYS, array_keys($data));
        $this->assertSame($ticket->uuid, $data['uuid']);
        $this->assertSame('The AC is loud', $data['description']);
        $this->assertSame('assigned', $data['status']);
        $this->assertSame('normal', $data['priority']);
        $this->assertSame('chatbot', $data['source']);
        $this->assertSame(1, $data['escalation_level']);
        $this->assertSame(['in_progress', 'waiting_guest', 'resolved', 'closed'], $data['allowed_statuses']);
        $this->assertSame(['uuid' => $f['reservation']->guest->uuid, 'name' => $f['reservation']->guest->name], $data['guest']);
        $this->assertSame(['uuid' => $f['reservation']->uuid, 'booking_code' => $f['reservation']->booking_code], $data['reservation']);
        $this->assertSame(['uuid' => $f['room']->uuid, 'number' => $f['room']->number], $data['room']);
        $this->assertSame($f['conversation']->uuid, $data['conversation_uuid']);
        $this->assertSame(['uuid' => $f['target']->uuid, 'name' => $f['target']->name], $data['assigned_user']);
        $this->assertSame(['uuid' => $f['creator']->uuid, 'name' => $f['creator']->name], $data['created_by']);
        $this->assertSame('25.00', $data['folio_credit_total_usd']);
        $this->assertSame('65.00', $data['recorded_value_usd']);
        $this->assertNull($data['resolved_at']);
        $this->assertFalse($data['actions_truncated']);

        $this->assertCount(7, $data['actions']);
        $this->assertSame(
            ['created', 'status_change', 'assignment', 'escalation', 'reply', 'recovery', 'recovery'],
            array_column($data['actions'], 'type'),
        );

        foreach ($data['actions'] as $action) {
            $this->assertSame(self::ACTION_KEYS, array_keys($action));
            $this->assertArrayNotHasKey('message_id', $action);
        }
        $this->assertArrayNotHasKey('message_id', $data);

        [$created, $status, $assignment, $escalation, $reply, $credit, $upgrade] = $data['actions'];

        $this->assertSame(['uuid' => $f['creator']->uuid, 'name' => $f['creator']->name], $created['actor']);
        $this->assertNull($created['body']);
        $this->assertSame('Working', $status['body']);
        $this->assertSame('open', $status['from_status']);
        $this->assertSame('in_progress', $status['to_status']);
        $this->assertSame(['claim' => true], $assignment['meta']);
        $this->assertSame(['uuid' => $f['assignee']->uuid, 'name' => $f['assignee']->name], $assignment['target_user']);
        $this->assertSame(['level' => 1, 'previous_assignee_uuid' => $f['assignee']->uuid], $escalation['meta']);
        $this->assertSame('Called the guest', $reply['body']);
        $this->assertNull($reply['recovery']);

        $this->assertSame('folio_credit', $credit['recovery']['type']);
        $this->assertSame('25.00', $credit['recovery']['amount_usd']);
        $this->assertSame('Goodwill', $credit['recovery']['description']);
        $this->assertSame($f['credit']->uuid, $credit['recovery']['folio_item_uuid']);
        $this->assertSame(['uuid', 'type', 'amount_usd', 'description', 'folio_item_uuid'], array_keys($credit['recovery']));
        $this->assertSame('room_upgrade', $upgrade['recovery']['type']);
        $this->assertSame('40.00', $upgrade['recovery']['amount_usd']);
        $this->assertNull($upgrade['recovery']['folio_item_uuid']);

        $this->assertSame(1, $data['latest_escalation']['level']);
        $this->assertSame(['uuid' => $f['target']->uuid, 'name' => $f['target']->name], $data['latest_escalation']['target_user']);
        $this->assertSame('Guest is VIP', $data['latest_escalation']['reason']);
        $this->assertSame($escalation['created_at'], $data['latest_escalation']['created_at']);
    }

    public function test_latest_escalation_is_null_without_escalations(): void
    {
        $ticket = Ticket::factory()->create();
        TicketAction::factory()->create(['ticket_id' => $ticket->id]);

        $this->show($ticket)->assertOk()->assertJsonPath('data.latest_escalation', null);
    }

    public function test_totals_default_to_zero_strings(): void
    {
        $this->show(Ticket::factory()->create())
            ->assertOk()
            ->assertJsonPath('data.folio_credit_total_usd', '0.00')
            ->assertJsonPath('data.recorded_value_usd', '0.00')
            ->assertJsonPath('data.actions', [])
            ->assertJsonPath('data.actions_truncated', false);
    }

    public function test_actions_are_the_newest_200_in_ascending_order(): void
    {
        $ticket = Ticket::factory()->create();
        $t0     = Carbon::parse('2027-03-12 10:00:00');
        $ids    = [];

        for ($i = 0; $i < 205; $i++) {
            $ids[] = TicketAction::factory()->reply("r{$i}")->create([
                'ticket_id'  => $ticket->id,
                'created_at' => $t0->copy()->addSeconds($i),
            ])->uuid;
        }

        $oldest = TicketAction::where('uuid', $ids[0])->sole();
        // Put a recovery on the oldest row via a separate recovery action at the same instant.
        $oldRecoveryAction = TicketAction::factory()->recovery()->create([
            'ticket_id'  => $ticket->id,
            'created_at' => $oldest->created_at->copy()->subMinute(),
        ]);
        TicketRecovery::factory()->create([
            'ticket_action_id' => $oldRecoveryAction->id,
            'type'             => TicketRecoveryType::ROOM_UPGRADE,
            'amount_usd'       => '10.00',
        ]);

        $data = $this->show($ticket)->assertOk()->json('data');

        $this->assertCount(200, $data['actions']);
        $this->assertTrue($data['actions_truncated']);
        $this->assertSame($ids[5], $data['actions'][0]['uuid']);
        $this->assertSame($ids[204], $data['actions'][199]['uuid']);

        $times = array_column($data['actions'], 'created_at');
        for ($i = 1; $i < count($times); $i++) {
            $this->assertLessThan($times[$i], $times[$i - 1]);
        }

        $this->assertSame('10.00', $data['recorded_value_usd']);
    }

    public function test_trashed_room_keeps_its_number(): void
    {
        $room   = Room::factory()->create();
        $ticket = Ticket::factory()->create(['room_id' => $room->id]);
        $room->delete();

        $this->show($ticket)->assertOk()->assertJsonPath('data.room.number', $room->number);
    }

    public function test_resolved_ticket_shows_resolved_at(): void
    {
        $ticket = Ticket::factory()->resolved()->create();

        $this->show($ticket)
            ->assertOk()
            ->assertJsonPath('data.status', TicketStatus::RESOLVED->value)
            ->assertJsonPath('data.resolved_at', $ticket->fresh()->resolved_at->toIso8601String())
            ->assertJsonPath('data.allowed_statuses', ['closed', 'in_progress']);
    }

    public function test_show_stays_within_the_query_budget(): void
    {
        [$ticket] = $this->linkedTicket();
        $ticket   = Ticket::findOrFail($ticket->id);

        $this->expectsDatabaseQueryCount(6);

        $result = app(TicketService::class)->show($ticket);
        (new TicketResource($result['data']))->resolve();
    }
}
