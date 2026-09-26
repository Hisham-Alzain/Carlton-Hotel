<?php

namespace Tests\Feature\Reservations;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\NotificationType;
use App\Enums\ReservationStatus;
use App\Events\GuestCheckedIn;
use App\Events\RoomAssigned;
use App\Listeners\SendRoomReadyNotification;
use App\Models\DeviceToken;
use App\Models\GuestNotification;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * POST /api/cms/reservations/{reservation}/check-in (Phase 3, RESV-03, D-01, D-02, D-04, D-05).
 */
class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
        $this->type = RoomType::factory()->create();
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    private function room(string $number, string $status = 'available', ?RoomType $type = null): Room
    {
        return Room::factory()->create([
            'room_type_id' => ($type ?? $this->type)->id,
            'number'       => $number,
            'status'       => $status,
        ]);
    }

    private function confirmedStay(string $in = '2027-03-10', string $out = '2027-03-12', ?Room $room = null, array $attrs = []): Reservation
    {
        $reservation = Reservation::factory()->confirmed()->create(array_merge(['check_in' => $in, 'check_out' => $out], $attrs));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $this->type->id,
            'room_id'        => $room?->id,
        ]);
        return $reservation;
    }

    private function checkIn(Reservation $reservation, array $body = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('reservations.create'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-in", $body);
    }

    private function earlyRows(Reservation $reservation)
    {
        return DB::table('activity_log')
            ->where('description', 'reservation.early_check_in')
            ->where('subject_id', $reservation->id)
            ->get();
    }

    private function assertUntouched(Reservation $reservation, ?int $roomId): void
    {
        $fresh = $reservation->fresh();
        $this->assertSame(ReservationStatus::CONFIRMED, $fresh->status);
        $this->assertNull($fresh->checked_in_at);
        $this->assertSame($roomId, $fresh->rooms()->first()->room_id);
    }

    // ── Room resolution ─────────────────────────────────────────────────

    public function test_check_in_uses_the_pre_assigned_room(): void
    {
        $room        = $this->room('101');
        $this->room('100');
        $reservation = $this->confirmedStay(room: $room);

        $this->checkIn($reservation)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Guest checked in.')
            ->assertJsonPath('data.uuid', $reservation->uuid)
            ->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.checked_in_at', '2027-03-10T09:00:00+00:00')
            ->assertJsonPath('data.rooms.0.room_uuid', $room->uuid)
            ->assertJsonPath('data.guest.uuid', $reservation->guest->uuid)
            ->assertJsonMissingPath('data.folio');

        $fresh = $reservation->fresh();
        $this->assertSame(ReservationStatus::CHECKED_IN, $fresh->status);
        $this->assertSame($room->id, $fresh->rooms()->first()->room_id);
    }

    public function test_explicit_room_wins_over_the_pre_assigned_room(): void
    {
        $reserved    = $this->room('101');
        $explicit    = $this->room('105');
        $reservation = $this->confirmedStay(room: $reserved);

        $this->checkIn($reservation, ['room_uuid' => $explicit->uuid])
            ->assertOk()
            ->assertJsonPath('data.rooms.0.room_uuid', $explicit->uuid);

        $this->assertSame($explicit->id, $reservation->rooms()->first()->room_id);
    }

    public function test_auto_pick_prefers_available_then_lowest_number(): void
    {
        $this->room('101', 'dirty');
        $r103 = $this->room('103');
        $r102 = $this->room('102');
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation)
            ->assertOk()
            ->assertJsonPath('data.rooms.0.room_uuid', $r102->uuid);
    }

    public function test_auto_pick_takes_a_dirty_room_when_no_available_room_is_free(): void
    {
        $r101 = $this->room('101', 'dirty');
        $r102 = $this->room('102');
        $this->room('103', 'maintenance');
        // 102 is held by another stay over these dates.
        $other = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-09', 'check_out' => '2027-03-11']);
        ReservationRoom::factory()->create(['reservation_id' => $other->id, 'room_type_id' => $this->type->id, 'room_id' => $r102->id]);
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation)
            ->assertOk()
            ->assertJsonPath('data.rooms.0.room_uuid', $r101->uuid);
    }

    public function test_check_in_does_not_touch_housekeeping_status(): void
    {
        $changer = User::factory()->create();
        $room    = Room::factory()->create([
            'room_type_id'      => $this->type->id,
            'number'            => '101',
            'status'            => 'available',
            'status_changed_at' => now()->subDay(),
            'status_changed_by' => $changer->id,
        ]);
        $before      = $room->fresh()->only(['status', 'status_changed_at', 'status_changed_by']);
        $reservation = $this->confirmedStay(room: $room);

        $this->checkIn($reservation)->assertOk();

        $this->assertEquals($before, $room->fresh()->only(['status', 'status_changed_at', 'status_changed_by']));
        $this->assertSame(0, DB::table('room_status_history')->count());

        $row = collect(
            $this->withToken($this->presetToken('reception'))
                ->getJson('/api/front-desk/room-board?date=2027-03-10')
                ->assertOk()
                ->json('data.items')
        )->firstWhere('uuid', $room->uuid);

        $this->assertSame('occupied', $row['occupancy']);
        $this->assertSame('available', $row['housekeeping_status']);
    }

    public function test_dirty_room_is_accepted(): void
    {
        $room        = $this->room('101', 'dirty');
        $reservation = $this->confirmedStay(room: $room);

        $this->checkIn($reservation)->assertOk()->assertJsonPath('data.status', 'checked_in');

        $this->assertSame('dirty', $room->fresh()->status->value);
    }

    public function test_maintenance_room_is_refused(): void
    {
        $maintenance = $this->room('109', 'maintenance');
        $free        = $this->room('101');

        // Explicit room in maintenance.
        $explicit = $this->confirmedStay(room: $free);
        $this->checkIn($explicit, ['room_uuid' => $maintenance->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'room_out_of_order')
            ->assertJsonPath('context.room_uuid', $maintenance->uuid)
            ->assertJsonPath('context.housekeeping_status', 'maintenance');
        $this->assertUntouched($explicit, $free->id);

        // Pre-assigned room in maintenance.
        $preAssigned = $this->confirmedStay(room: $maintenance);
        $this->checkIn($preAssigned)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'room_out_of_order')
            ->assertJsonPath('context.room_uuid', $maintenance->uuid)
            ->assertJsonPath('context.housekeeping_status', 'maintenance');
        $this->assertUntouched($preAssigned, $maintenance->id);
    }

    public function test_room_of_another_type_is_refused(): void
    {
        $foreign     = $this->room('301', 'available', RoomType::factory()->create());
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation, ['room_uuid' => $foreign->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state');

        $this->assertUntouched($reservation, null);
    }

    public function test_room_held_by_an_overlapping_stay_is_refused(): void
    {
        $room  = $this->room('101');
        $other = Reservation::factory()->create(['check_in' => '2027-03-11', 'check_out' => '2027-03-14']); // pending still holds
        ReservationRoom::factory()->create(['reservation_id' => $other->id, 'room_type_id' => $this->type->id, 'room_id' => $room->id]);
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation, ['room_uuid' => $room->uuid])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'room_already_assigned');

        $this->assertUntouched($reservation, null);
    }

    public function test_back_to_back_turnover_room_can_be_checked_into(): void
    {
        $room    = $this->room('101', 'dirty');
        $leaving = Reservation::factory()->checkedIn()->create(['check_in' => '2027-03-07', 'check_out' => '2027-03-10']);
        ReservationRoom::factory()->create(['reservation_id' => $leaving->id, 'room_type_id' => $this->type->id, 'room_id' => $room->id]);
        $reservation = $this->confirmedStay(room: $room);

        $this->checkIn($reservation)
            ->assertOk()
            ->assertJsonPath('data.rooms.0.room_uuid', $room->uuid);
    }

    public function test_nothing_free_to_auto_pick_returns_no_availability(): void
    {
        $room  = $this->room('101');
        $this->room('102', 'maintenance');
        $other = Reservation::factory()->checkedIn()->create(['check_in' => '2027-03-09', 'check_out' => '2027-03-11']);
        ReservationRoom::factory()->create(['reservation_id' => $other->id, 'room_type_id' => $this->type->id, 'room_id' => $room->id]);
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'no_availability');

        $this->assertUntouched($reservation, null);
    }

    public function test_reservation_without_a_room_line_is_refused(): void
    {
        $this->room('101');
        $reservation = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-10', 'check_out' => '2027-03-12']);

        $this->checkIn($reservation)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state');

        $this->assertSame(ReservationStatus::CONFIRMED, $reservation->fresh()->status);
    }

    // ── Status guard ────────────────────────────────────────────────────

    public static function nonConfirmedStatuses(): array
    {
        return [
            'pending_verification' => ['pending_verification'],
            'pending'              => ['pending'],
            'checked_in'           => ['checked_in'],
            'checked_out'          => ['checked_out'],
            'cancelled'            => ['cancelled'],
        ];
    }

    #[DataProvider('nonConfirmedStatuses')]
    public function test_only_confirmed_reservations_check_in(string $status): void
    {
        $room        = $this->room('101');
        $reservation = $this->confirmedStay(room: $room);
        $reservation->update([
            'status'          => $status,
            'hold_expires_at' => $status === 'pending_verification' ? now()->addMinutes(5) : null,
        ]);

        $this->checkIn($reservation)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state')
            ->assertJsonPath('context.status', $status)
            ->assertJsonPath('context.allowed', ['confirmed']);

        $this->assertSame($status, $reservation->fresh()->status->value);
        $this->assertNull($reservation->fresh()->checked_in_at);
    }

    // ── Stay window (D-02) ──────────────────────────────────────────────

    public function test_stay_window_boundaries(): void
    {
        $this->room('101');
        $this->room('102');
        $this->room('103');

        // Last night of the stay: allowed.
        $this->travelTo(Carbon::parse('2027-03-11 09:00:00'));
        $lastNight = $this->confirmedStay('2027-03-10', '2027-03-12');
        $this->checkIn($lastNight)->assertOk();

        // Departure day: refused.
        $this->travelTo(Carbon::parse('2027-03-12 09:00:00'));
        $departure = $this->confirmedStay('2027-03-10', '2027-03-12');
        $this->checkIn($departure)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_outside_stay_window')
            ->assertJsonPath('context.check_in', '2027-03-10')
            ->assertJsonPath('context.check_out', '2027-03-12')
            ->assertJsonPath('context.today', '2027-03-12');
        $this->assertUntouched($departure, null);

        // The day before arrival without the override flag: refused.
        $early = $this->confirmedStay('2027-03-13', '2027-03-15');
        $this->checkIn($early)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_outside_stay_window')
            ->assertJsonPath('context.today', '2027-03-12');
        $this->assertUntouched($early, null);
    }

    public function test_window_uses_the_hotel_local_date(): void
    {
        $this->room('101');
        $this->room('102');
        $this->travelTo(Carbon::parse('2027-03-09 16:30:00', 'UTC'));

        // 01:30 on 2027-03-10 in Tokyo: the arrival day has begun.
        config(['hotel.timezone' => 'Asia/Tokyo']);
        $tokyo = $this->confirmedStay('2027-03-10', '2027-03-12');
        $this->checkIn($tokyo)->assertOk()->assertJsonPath('data.status', 'checked_in');

        // Same instant in UTC is still 2027-03-09.
        config(['hotel.timezone' => 'UTC']);
        $utc = $this->confirmedStay('2027-03-10', '2027-03-12');
        $this->checkIn($utc)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_outside_stay_window')
            ->assertJsonPath('context.today', '2027-03-09');
    }

    public function test_early_check_in_on_the_day_before_is_logged(): void
    {
        $this->travelTo(Carbon::parse('2027-03-09 09:00:00'));
        $this->room('101');
        $staff = User::factory()->create();
        $staff->givePermissionTo('reservations.create');
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation, ['early_check_in' => true, 'reason' => 'Arrived on the night flight'], $staff->createToken('t')->plainTextToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.checked_in_at', '2027-03-09T09:00:00+00:00');

        $rows = $this->earlyRows($reservation);
        $this->assertCount(1, $rows);
        $this->assertSame($reservation->getMorphClass(), $rows[0]->subject_type);
        $this->assertSame($staff->getMorphClass(), $rows[0]->causer_type);
        $this->assertEquals($staff->id, $rows[0]->causer_id);
        $this->assertEquals([
            'reason'   => 'Arrived on the night flight',
            'check_in' => '2027-03-10',
            'today'    => '2027-03-09',
        ], json_decode($rows[0]->properties, true));
    }

    public function test_early_check_in_is_refused_two_days_before_and_on_departure(): void
    {
        $this->room('101');
        $body = ['early_check_in' => true, 'reason' => 'Arrived early'];

        foreach (['2027-03-08 09:00:00' => '2027-03-08', '2027-03-12 09:00:00' => '2027-03-12'] as $instant => $today) {
            $this->travelTo(Carbon::parse($instant));
            $reservation = $this->confirmedStay();

            $this->checkIn($reservation, $body)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'reservation_outside_stay_window')
                ->assertJsonPath('context.today', $today);

            $this->assertCount(0, $this->earlyRows($reservation));
            $this->assertUntouched($reservation, null);
        }
    }

    public function test_early_check_in_requires_a_reason(): void
    {
        $this->travelTo(Carbon::parse('2027-03-09 09:00:00'));
        $this->room('101');
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation, ['early_check_in' => true])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['reason']);

        $this->checkIn($reservation, ['early_check_in' => true, 'reason' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->checkIn($reservation, ['early_check_in' => true, 'reason' => str_repeat('r', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertUntouched($reservation, null);

        // 255 characters is the limit.
        $this->checkIn($reservation, ['early_check_in' => true, 'reason' => str_repeat('r', 255)])->assertOk();
    }

    public function test_early_flag_inside_the_window_is_ignored(): void
    {
        $this->room('101');
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation, ['early_check_in' => true, 'reason' => 'Not needed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');

        $this->assertCount(0, $this->earlyRows($reservation));
    }

    public function test_existing_checked_in_at_is_kept(): void
    {
        $room        = $this->room('101');
        $reservation = $this->confirmedStay(room: $room, attrs: ['checked_in_at' => '2027-03-09 20:00:00']);

        $this->checkIn($reservation)
            ->assertOk()
            ->assertJsonPath('data.checked_in_at', '2027-03-09T20:00:00+00:00');

        $this->assertSame('2027-03-09 20:00:00', $reservation->fresh()->checked_in_at->format('Y-m-d H:i:s'));
    }

    public function test_invalid_body_is_rejected(): void
    {
        $this->room('101');
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation, ['room_uuid' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['room_uuid']);

        $this->checkIn($reservation, ['early_check_in' => 'yes', 'reason' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['early_check_in']);

        $this->assertUntouched($reservation, null);
    }

    // ── Notifications (D-05) ────────────────────────────────────────────

    public function test_check_in_pushes_room_ready(): void
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        $room        = $this->room('101');
        $reservation = $this->confirmedStay(room: $room);
        DeviceToken::factory()->create(['guest_id' => $reservation->guest_id, 'token' => 'guest-tok']);

        $this->checkIn($reservation)->assertOk();

        $this->assertSame(1, GuestNotification::where('guest_id', $reservation->guest_id)
            ->where('type', NotificationType::ROOM_READY->value)->count());
        $this->assertCount(1, $fake->pushes);
        $this->assertSame(['guest-tok'], $fake->pushes[0]['tokens']);
    }

    public function test_check_in_dispatches_guest_checked_in_only(): void
    {
        Event::fake([GuestCheckedIn::class, RoomAssigned::class]);
        $reserved    = $this->room('101');
        $other       = $this->room('102');
        $reservation = $this->confirmedStay(room: $reserved);

        // A room change at check-in is still a check-in, not a move.
        $this->checkIn($reservation, ['room_uuid' => $other->uuid])->assertOk();

        Event::assertDispatchedTimes(GuestCheckedIn::class, 1);
        Event::assertDispatched(GuestCheckedIn::class, fn (GuestCheckedIn $e) => $e->reservation->uuid === $reservation->uuid);
        Event::assertNotDispatched(RoomAssigned::class);

        // A refused check-in dispatches nothing.
        $this->checkIn($reservation)->assertStatus(422);
        Event::assertDispatchedTimes(GuestCheckedIn::class, 1);
    }

    public function test_room_ready_listener_handles_both_events(): void
    {
        Event::fake();

        Event::assertListening(GuestCheckedIn::class, SendRoomReadyNotification::class);
        Event::assertListening(RoomAssigned::class, SendRoomReadyNotification::class);
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_requires_reservations_create(): void
    {
        $this->room('101');
        $reservation = $this->confirmedStay();

        foreach ([$this->staffToken('reservations.view'), $this->presetToken('housekeeping')] as $token) {
            $this->checkIn($reservation, [], $token)
                ->assertForbidden()
                ->assertJsonPath('error_code', 'forbidden');
        }

        $this->assertUntouched($reservation, null);
    }

    public function test_reception_preset_can_check_in(): void
    {
        $this->room('101');
        $reservation = $this->confirmedStay();

        $this->checkIn($reservation, [], $this->presetToken('reception'))->assertOk();
    }

    public function test_requires_authentication(): void
    {
        $reservation = $this->confirmedStay();

        $this->postJson("/api/cms/reservations/{$reservation->uuid}/check-in")
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');

        $this->withToken($reservation->guest->createToken('guest')->plainTextToken)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-in")
            ->assertUnauthorized();
    }

    public function test_unknown_reservation_returns_404(): void
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations/'.Str::uuid().'/check-in')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }
}
