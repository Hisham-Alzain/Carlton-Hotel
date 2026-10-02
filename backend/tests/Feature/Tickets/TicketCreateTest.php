<?php

namespace Tests\Feature\Tickets;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\Department;
use App\Enums\TicketCategory;
use App\Models\Conversation;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/** POST /api/support-tickets (Phase 7, TICKET-02; D-04, D-05, D-11, D-12, D-13, D-21). */
class TicketCreateTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/support-tickets';

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

    private function create(array $body, ?User $actor = null)
    {
        $actor ??= $this->staff('tickets.respond');

        return $this->withToken($actor->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson(self::URL, $body);
    }

    public function test_requires_a_token(): void
    {
        $this->postJson(self::URL, ['subject' => 'Broken AC', 'category' => 'complaint'])->assertStatus(401);
    }

    public function test_tickets_view_alone_cannot_create(): void
    {
        $this->create(['subject' => 'Broken AC', 'category' => 'complaint'], $this->staff('tickets.view'))
            ->assertStatus(403);

        $this->assertSame(0, Ticket::count());
    }

    public function test_internal_ticket_is_created_with_source_staff(): void
    {
        $actor = $this->staff('tickets.respond');

        $response = $this->create(['subject' => 'Broken AC', 'category' => 'complaint'], $actor)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.ticket_created'))
            ->assertJsonPath('data.source', 'staff')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'normal')
            ->assertJsonPath('data.department', Department::forTicketCategory(TicketCategory::COMPLAINT)->value)
            ->assertJsonPath('data.created_by.uuid', $actor->uuid)
            ->assertJsonPath('data.created_by.name', $actor->name)
            ->assertJsonPath('data.guest', null)
            ->assertJsonPath('data.reservation', null)
            ->assertJsonPath('data.room', null)
            ->assertJsonPath('data.conversation_uuid', null)
            ->assertJsonPath('data.escalation_level', 0)
            ->assertJsonPath('data.allowed_statuses', ['in_progress', 'resolved', 'closed'])
            ->assertJsonCount(1, 'data.actions')
            ->assertJsonPath('data.actions.0.type', 'created')
            ->assertJsonPath('data.actions.0.from_status', null)
            ->assertJsonPath('data.actions.0.to_status', 'open')
            ->assertJsonPath('data.actions.0.actor.uuid', $actor->uuid)
            ->assertJsonPath('data.actions.0.body', null);

        $ticket = Ticket::sole();
        $this->assertSame($actor->id, $ticket->created_by);
        $response->assertJsonPath('data.uuid', $ticket->uuid);
    }

    public function test_body_source_and_conversation_are_ignored(): void
    {
        $conversation = Conversation::factory()->create();

        $this->create([
            'subject'           => 'From chat',
            'category'          => 'inquiry',
            'source'            => 'chatbot',
            'status'            => 'closed',
            'conversation_uuid' => $conversation->uuid,
            'conversation_id'   => $conversation->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.source', 'staff')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.conversation_uuid', null);

        $this->assertNull(Ticket::sole()->conversation_id);
    }

    public function test_body_department_wins_over_the_category(): void
    {
        $this->create(['subject' => 'Leak', 'category' => 'maintenance', 'department' => 'events'])
            ->assertStatus(201)
            ->assertJsonPath('data.department', 'events');
    }

    public function test_priority_label_is_stored_on_the_ticket_scale(): void
    {
        $this->create(['subject' => 'Urgent', 'category' => 'complaint', 'priority' => 'high'])
            ->assertStatus(201)
            ->assertJsonPath('data.priority', 'high');

        $this->assertSame(3, (int) Ticket::sole()->priority);
    }

    public function test_guest_is_derived_from_the_reservation(): void
    {
        $reservation = Reservation::factory()->create();

        $this->create(['subject' => 'Noise', 'category' => 'complaint', 'reservation_uuid' => $reservation->uuid])
            ->assertStatus(201)
            ->assertJsonPath('data.reservation.uuid', $reservation->uuid)
            ->assertJsonPath('data.reservation.booking_code', $reservation->booking_code)
            ->assertJsonPath('data.guest.uuid', $reservation->guest->uuid);
    }

    public function test_guest_reservation_mismatch_is_422(): void
    {
        $reservation = Reservation::factory()->create();
        $other       = Guest::factory()->create();

        $this->create([
            'subject'          => 'Noise',
            'category'         => 'complaint',
            'reservation_uuid' => $reservation->uuid,
            'guest_uuid'       => $other->uuid,
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['guest_uuid']);

        $this->assertSame(0, Ticket::count());
    }

    public function test_room_is_independent_of_the_reservation(): void
    {
        $reservation = Reservation::factory()->create();
        $room        = Room::factory()->create();
        $guest       = $reservation->guest;

        $this->create([
            'subject'          => 'Wrong room',
            'category'         => 'complaint',
            'reservation_uuid' => $reservation->uuid,
            'guest_uuid'       => $guest->uuid,
            'room_uuid'        => $room->uuid,
        ])->assertStatus(201)
            ->assertJsonPath('data.room.uuid', $room->uuid)
            ->assertJsonPath('data.room.number', $room->number)
            ->assertJsonPath('data.guest.uuid', $guest->uuid);
    }

    public static function invalidBodies(): array
    {
        $ok = ['subject' => 'Broken AC', 'category' => 'complaint'];

        return [
            'missing subject'     => [['category' => 'complaint'], 'subject'],
            'subject too short'   => [['subject' => 'ab'] + $ok, 'subject'],
            'subject too long'    => [['subject' => str_repeat('a', 151)] + $ok, 'subject'],
            'description too long'=> [$ok + ['description' => str_repeat('a', 5001)], 'description'],
            'unknown category'    => [['category' => 'rant'] + $ok, 'category'],
            'critical priority'   => [$ok + ['priority' => 'critical'], 'priority'],
            'unknown department'  => [$ok + ['department' => 'spa'], 'department'],
            'unknown guest'       => [$ok + ['guest_uuid' => '00000000-0000-4000-8000-000000000000'], 'guest_uuid'],
            'unknown reservation' => [$ok + ['reservation_uuid' => '00000000-0000-4000-8000-000000000000'], 'reservation_uuid'],
            'unknown room'        => [$ok + ['room_uuid' => '00000000-0000-4000-8000-000000000000'], 'room_uuid'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_input_is_422(array $body, string $field): void
    {
        $this->create($body)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, Ticket::count());
    }

    public function test_trashed_room_is_rejected(): void
    {
        $room = Room::factory()->create();
        $room->delete();

        $this->create(['subject' => 'Old room', 'category' => 'complaint', 'room_uuid' => $room->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['room_uuid']);
    }

    public function test_create_is_mirrored_once(): void
    {
        $this->create(['subject' => 'Broken AC', 'category' => 'complaint'])->assertStatus(201);

        $ticket  = Ticket::sole();
        $mirrors = collect($this->firebase->mirrors)->where('document', "ticket_{$ticket->uuid}");

        $this->assertCount(1, $mirrors);
        $this->assertSame('ops_queue', $mirrors->first()['collection']);
        $this->assertSame('open', $mirrors->first()['data']['status']);
    }
}
