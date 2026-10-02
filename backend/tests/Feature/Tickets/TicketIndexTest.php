<?php

namespace Tests\Feature\Tickets;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\Department;
use App\Enums\TicketActionType;
use App\Enums\TicketCategory;
use App\Enums\TicketRecoveryType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Http\Resources\Tickets\TicketResource;
use App\Models\Conversation;
use App\Models\FolioItem;
use App\Models\Guest;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** GET /api/support-tickets (Phase 7, TICKET-01; D-12, D-13, A7, PR-8). */
class TicketIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $caller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService());
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));

        $this->caller = User::factory()->create();
        $this->caller->givePermissionTo('tickets.view');
    }

    private function list(string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->caller->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/support-tickets' . ($query !== '' ? '?' . $query : ''));
    }

    /** @return list<string> */
    private function uuids(string $query): array
    {
        return array_column($this->list($query)->assertOk()->json('data.items'), 'uuid');
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/support-tickets')->assertStatus(401);
    }

    public function test_tickets_respond_alone_cannot_list(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('tickets.respond');

        $this->list('', $user->createToken('t')->plainTextToken)->assertStatus(403);
    }

    public function test_lists_every_status_newest_first(): void
    {
        $made = [];
        foreach (TicketStatus::cases() as $i => $status) {
            $made[] = Ticket::factory()->create(['status' => $status, 'created_at' => now()->subHours(10 - $i)]);
        }
        $tieA = Ticket::factory()->create(['created_at' => now()]);
        $tieB = Ticket::factory()->create(['created_at' => now()]);

        $response = $this->list()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 8)
            ->assertJsonStructure(['data' => ['items', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]]);

        $expected = array_merge([$tieB->uuid, $tieA->uuid], array_reverse(array_map(fn ($t) => $t->uuid, $made)));
        $this->assertSame($expected, array_column($response->json('data.items'), 'uuid'));
    }

    public function test_rows_have_the_list_shape(): void
    {
        $ticket = Ticket::factory()->withReservation()->create([
            'conversation_id' => Conversation::factory()->create()->id,
        ]);
        $credit = FolioItem::factory()->credit()->create(['amount_usd' => '-25.00']);
        $a1 = TicketAction::factory()->recovery()->create(['ticket_id' => $ticket->id]);
        TicketRecovery::factory()->folioCredit($credit)->create(['ticket_action_id' => $a1->id]);
        $a2 = TicketAction::factory()->recovery()->create(['ticket_id' => $ticket->id]);
        TicketRecovery::factory()->create(['ticket_action_id' => $a2->id, 'type' => TicketRecoveryType::ROOM_UPGRADE, 'amount_usd' => '40.00']);

        $row = $this->list()->assertOk()->json('data.items.0');

        $this->assertSame([
            'uuid', 'subject', 'description', 'category', 'status', 'priority', 'department', 'source',
            'escalation_level', 'allowed_statuses', 'guest', 'reservation', 'room', 'conversation_uuid',
            'assigned_user', 'created_by', 'folio_credit_total_usd', 'recorded_value_usd',
            'resolved_at', 'closed_at', 'created_at', 'updated_at',
        ], array_keys($row));
        $this->assertSame('25.00', $row['folio_credit_total_usd']);
        $this->assertSame('65.00', $row['recorded_value_usd']);
        $this->assertSame($ticket->conversation->uuid, $row['conversation_uuid']);
        $this->assertSame($ticket->reservation->booking_code, $row['reservation']['booking_code']);
        $this->assertArrayNotHasKey('actions', $row);
        $this->assertArrayNotHasKey('actions_truncated', $row);
        $this->assertArrayNotHasKey('latest_escalation', $row);
    }

    public function test_status_filter_eq_and_in(): void
    {
        $open     = Ticket::factory()->create();
        $closed   = Ticket::factory()->closed()->create();
        $progress = Ticket::factory()->inProgress()->create();

        $this->assertSame([$open->uuid], $this->uuids('status=open'));
        $this->assertEqualsCanonicalizing([$closed->uuid, $progress->uuid], $this->uuids('status[in]=closed,in_progress'));
        $this->assertSame([], $this->uuids('status=bogus'));
    }

    public function test_department_filter_eq_and_in(): void
    {
        $events = Ticket::factory()->create(['department' => Department::EVENTS]);
        $rec    = Ticket::factory()->create(['department' => Department::RECEPTION]);
        Ticket::factory()->create(['department' => Department::CONCIERGE]);

        $this->assertSame([$events->uuid], $this->uuids('department=events'));
        $this->assertEqualsCanonicalizing([$events->uuid, $rec->uuid], $this->uuids('department[in]=events,reception'));
    }

    public function test_source_filter(): void
    {
        $staff = Ticket::factory()->staff()->create();
        $bot   = Ticket::factory()->create(['source' => TicketSource::CHATBOT]);

        $this->assertSame([$staff->uuid], $this->uuids('source=staff'));
        $this->assertSame([$bot->uuid], $this->uuids('source=chatbot'));
    }

    public function test_category_filter(): void
    {
        $complaint = Ticket::factory()->create(['category' => TicketCategory::COMPLAINT]);
        Ticket::factory()->create(['category' => TicketCategory::INQUIRY]);

        $this->assertSame([$complaint->uuid], $this->uuids('category=complaint'));
    }

    public function test_priority_filter_uses_labels(): void
    {
        $low    = Ticket::factory()->create(['priority' => 1]);
        $normal = Ticket::factory()->create(['priority' => 2]);
        $high   = Ticket::factory()->create(['priority' => 3]);

        $this->assertSame([$high->uuid], $this->uuids('priority=high'));
        $this->assertEqualsCanonicalizing([$low->uuid, $normal->uuid], $this->uuids('priority[in]=low,normal'));
        $this->assertSame([], $this->uuids('priority=critical'));
    }

    public function test_assignee_filter_uuid_unassigned_and_me(): void
    {
        $other      = User::factory()->create();
        $mine       = Ticket::factory()->assignedTo($this->caller)->create();
        $theirs     = Ticket::factory()->assignedTo($other)->create();
        $unassigned = Ticket::factory()->create();

        $this->assertSame([$theirs->uuid], $this->uuids("assignee={$other->uuid}"));
        $this->assertSame([$unassigned->uuid], $this->uuids('assignee=unassigned'));
        $this->assertSame([$mine->uuid], $this->uuids('assignee=me'));
    }

    public function test_guest_and_reservation_filters(): void
    {
        $reservation = Reservation::factory()->create();
        $linked      = Ticket::factory()->withReservation($reservation)->create();
        $guestOnly   = Ticket::factory()->create(['guest_id' => Guest::factory()->create()->id]);

        $this->assertSame([$linked->uuid], $this->uuids("reservation={$reservation->uuid}"));
        $this->assertSame([$linked->uuid], $this->uuids("guest={$reservation->guest->uuid}"));
        $this->assertSame([$guestOnly->uuid], $this->uuids("guest={$guestOnly->guest->uuid}"));
    }

    public function test_escalated_filter(): void
    {
        $escalated = Ticket::factory()->escalated(2)->create();
        $calm      = Ticket::factory()->create();

        $this->assertSame([$escalated->uuid], $this->uuids('escalated=true'));
        $this->assertSame([$calm->uuid], $this->uuids('escalated=false'));
    }

    public function test_created_at_bounds(): void
    {
        $old = Ticket::factory()->create(['created_at' => '2027-03-01 10:00:00']);
        $new = Ticket::factory()->create(['created_at' => '2027-03-10 10:00:00']);

        $this->assertSame([$new->uuid], $this->uuids('created_at[gte]=2027-03-05'));
        $this->assertSame([$old->uuid], $this->uuids('created_at[lte]=2027-03-05'));
    }

    public static function malformed(): array
    {
        return [
            'assignee'    => ['assignee=not-a-uuid', 'assignee'],
            'guest'       => ['guest=123', 'guest'],
            'reservation' => ['reservation=x', 'reservation'],
            'escalated'   => ['escalated=maybe', 'escalated'],
            'created_at'  => ['created_at[gte]=not-a-date', 'created_at.gte'],
        ];
    }

    #[DataProvider('malformed')]
    public function test_malformed_filters_are_422(string $query, string $key): void
    {
        $this->list($query)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors([$key]);
    }

    public function test_sorts(): void
    {
        $low  = Ticket::factory()->create(['priority' => 1, 'status' => TicketStatus::RESOLVED, 'updated_at' => now()->subHour()]);
        $high = Ticket::factory()->create(['priority' => 3, 'status' => TicketStatus::CLOSED, 'updated_at' => now()->subHours(2)]);
        $mid  = Ticket::factory()->create(['priority' => 2, 'status' => TicketStatus::OPEN, 'updated_at' => now()]);

        $this->assertSame([$low->uuid, $mid->uuid, $high->uuid], $this->uuids('sort=priority'));
        $this->assertSame([$high->uuid, $mid->uuid, $low->uuid], $this->uuids('sort=priority&sort_dir=desc'));
        $this->assertSame([$high->uuid, $mid->uuid, $low->uuid], $this->uuids('sort=status'));
        $this->assertSame([$high->uuid, $low->uuid, $mid->uuid], $this->uuids('sort=updated_at'));
    }

    public function test_list_stays_within_the_query_budget(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $ticket = Ticket::factory()->withReservation()->assignedTo(User::factory()->create())->create([
                'room_id'         => Room::factory()->create()->id,
                'conversation_id' => Conversation::factory()->create()->id,
                'created_by'      => User::factory()->create()->id,
            ]);
            $action = TicketAction::factory()->create(['ticket_id' => $ticket->id, 'type' => TicketActionType::RECOVERY, 'to_status' => null]);
            TicketRecovery::factory()->create(['ticket_action_id' => $action->id, 'amount_usd' => '5.00']);
        }

        $this->expectsDatabaseQueryCount(6);

        $page = app(TicketService::class)->index([])['data'];
        $rows = TicketResource::collection($page->getCollection())->resolve();

        $this->assertCount(3, $rows);
        $this->assertSame('5.00', $rows[0]['recorded_value_usd']);
        $this->assertNotNull($rows[0]['assigned_user']);
        $this->assertNotNull($rows[0]['created_by']);
    }
}
