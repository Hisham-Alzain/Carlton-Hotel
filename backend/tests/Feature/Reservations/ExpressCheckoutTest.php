<?php

namespace Tests\Feature\Reservations;

use App\Enums\CheckOutMode;
use App\Enums\ReservationStatus;
use App\Events\ReservationCheckedOut;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * POST /api/folio/approve — the guest's express checkout, now running the
 * shared CheckOutReservationAction in GUEST_EXPRESS mode (Phase 3, D-08, D-09).
 */
class ExpressCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const FOLIO_KEYS = ['uuid', 'status', 'subtotal_usd', 'total_usd', 'approved_by_guest_at', 'settled_at', 'items'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 09:00:00'));
    }

    /** @return array{0: Guest, 1: Reservation, 2: Room, 3: string} */
    private function guestInRoom(): array
    {
        $guest       = Guest::factory()->create();
        $type        = RoomType::factory()->create();
        $room        = Room::factory()->create(['room_type_id' => $type->id, 'status' => 'available']);
        $reservation = Reservation::factory()->checkedIn()->create([
            'guest_id'      => $guest->id,
            'check_in'      => '2027-03-10',
            'check_out'     => '2027-03-12',
            'checked_in_at' => '2027-03-10 12:00:00',
            'total_usd'     => 300,
        ]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $room->id,
        ]);

        return [$guest, $reservation, $room, $guest->createToken('guest')->plainTextToken];
    }

    private function approve(string $token)
    {
        return $this->withToken($token)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/folio/approve');
    }

    public function test_guest_express_checkout_runs_the_shared_check_out(): void
    {
        [, $reservation, $room, $token] = $this->guestInRoom();

        $response = $this->approve($token)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Checkout approved.')
            ->assertJsonPath('data.approved_by_guest_at', '2027-03-12T09:00:00+00:00')
            ->assertJsonPath('data.total_usd', '300.00');

        $this->assertSame(self::FOLIO_KEYS, array_keys($response->json('data')));

        $fresh = $reservation->fresh();
        $this->assertSame(ReservationStatus::CHECKED_OUT, $fresh->status);
        $this->assertSame('2027-03-12 09:00:00', $fresh->checked_out_at->format('Y-m-d H:i:s'));

        $this->assertSame('dirty', $room->fresh()->status->value);
        $history = DB::table('room_status_history')->where('room_id', $room->id)->get();
        $this->assertCount(1, $history);
        $this->assertSame('check-out', $history[0]->reason);
        $this->assertNull($history[0]->changed_by);
    }

    public function test_guest_express_is_unguarded_by_the_folio_balance(): void
    {
        [, $reservation, , $token] = $this->guestInRoom();

        $this->approve($token)->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.settled_at', null);

        $folio = Folio::where('reservation_id', $reservation->id)->first();
        $this->assertSame('open', $folio->status->value);
        $this->assertNull($folio->settled_at);
        $this->assertSame(0, DB::table('payments')->count());
    }

    public function test_guest_marker_is_logged_without_a_causer(): void
    {
        [, $reservation, , $token] = $this->guestInRoom();

        $this->approve($token)->assertOk();
        $folio = Folio::where('reservation_id', $reservation->id)->first();

        $rows = DB::table('activity_log')->where('description', 'reservation.check_out_guest_express')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($reservation->getMorphClass(), $rows[0]->subject_type);
        $this->assertEquals($reservation->id, $rows[0]->subject_id);
        $this->assertNull($rows[0]->causer_type);
        $this->assertNull($rows[0]->causer_id);
        $this->assertEquals([
            'folio_uuid'   => $folio->uuid,
            'folio_status' => 'open',
            'total_usd'    => '300.00',
        ], json_decode($rows[0]->properties, true));

        $this->assertSame(0, DB::table('activity_log')->where('description', 'reservation.check_out_forced')->count());
    }

    public function test_guest_path_dispatches_reservation_checked_out(): void
    {
        Event::fake([ReservationCheckedOut::class]);
        [, $reservation, , $token] = $this->guestInRoom();

        $this->approve($token)->assertOk();

        Event::assertDispatchedTimes(ReservationCheckedOut::class, 1);
        Event::assertDispatched(ReservationCheckedOut::class, fn (ReservationCheckedOut $e) => $e->reservation->uuid === $reservation->uuid
            && $e->mode === CheckOutMode::GUEST_EXPRESS
            && $e->actorId === null);
    }

    public function test_event_fires_after_the_approve_transaction_commits(): void
    {
        [, , , $token] = $this->guestInRoom();
        $levels = [];
        Event::listen(ReservationCheckedOut::class, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });
        $outer = DB::transactionLevel();

        $this->approve($token)->assertOk();

        $this->assertSame([$outer], $levels);
    }

    public function test_refused_express_checkout_writes_nothing(): void
    {
        // FA-06-1: the entitlement picks the latest booking; when that is not
        // the checked-in stay the shared action refuses and everything rolls back.
        Event::fake([ReservationCheckedOut::class]);
        [$guest, $stay, $room, $token] = $this->guestInRoom();
        $future = Reservation::factory()->confirmed()->create([
            'guest_id'  => $guest->id,
            'check_in'  => '2027-03-20',
            'check_out' => '2027-03-22',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $future->id, 'room_type_id' => $room->room_type_id]);

        $this->approve($token)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state')
            ->assertJsonPath('context.status', 'confirmed')
            ->assertJsonPath('context.allowed', ['checked_in']);

        $this->assertSame(0, Folio::where('reservation_id', $future->id)->count());
        $this->assertSame(ReservationStatus::CONFIRMED, $future->fresh()->status);
        $this->assertSame(ReservationStatus::CHECKED_IN, $stay->fresh()->status);
        $this->assertNull($stay->fresh()->checked_out_at);
        $this->assertSame('available', $room->fresh()->status->value);
        $this->assertSame(0, DB::table('room_status_history')->count());
        $this->assertSame(0, DB::table('activity_log')->where('description', 'reservation.check_out_guest_express')->count());
        Event::assertNotDispatched(ReservationCheckedOut::class);
    }

    public function test_second_approve_is_refused_by_the_in_room_gate(): void
    {
        Event::fake([ReservationCheckedOut::class]);
        [, , , $token] = $this->guestInRoom();

        $this->approve($token)->assertOk();
        $history = DB::table('room_status_history')->count();
        $markers = DB::table('activity_log')->where('description', 'reservation.check_out_guest_express')->count();

        $this->approve($token)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'no_active_reservation');

        Event::assertDispatchedTimes(ReservationCheckedOut::class, 1);
        $this->assertSame($history, DB::table('room_status_history')->count());
        $this->assertSame($markers, DB::table('activity_log')->where('description', 'reservation.check_out_guest_express')->count());
    }

    public function test_approve_requires_a_guest_token(): void
    {
        $this->postJson('/api/folio/approve')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');
    }
}
