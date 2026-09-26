<?php

namespace Tests\Feature\Reservations;

use App\Enums\CheckOutMode;
use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceBookingStatus;
use App\Events\ReservationCheckedOut;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\PoolCabana;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceBooking;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * POST /api/cms/reservations/{reservation}/check-out (Phase 3, RESV-04, D-06, D-07, D-09).
 */
class CheckOutTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;
    private int $roomNumber = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $this->type = RoomType::factory()->create();
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function presetUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function presetToken(string $role): string
    {
        return $this->presetUser($role)->createToken('t')->plainTextToken;
    }

    /**
     * A checked_in stay 2027-03-10..2027-03-12, total 300, one line per entry of
     * $roomStatuses (null = a line without a room), and a folio in
     * $folioStatus (null = no folio).
     */
    private function checkedInStay(?string $folioStatus = 'settled', array $roomStatuses = ['available'], array $attrs = []): Reservation
    {
        $reservation = Reservation::factory()->checkedIn()->create(array_merge([
            'check_in'      => '2027-03-10',
            'check_out'     => '2027-03-12',
            'checked_in_at' => '2027-03-10 12:00:00',
            'total_usd'     => 300,
        ], $attrs));

        foreach ($roomStatuses as $status) {
            $room = $status === null ? null : Room::factory()->create([
                'room_type_id'      => $this->type->id,
                'number'            => (string) ++$this->roomNumber,
                'status'            => $status,
                'status_changed_by' => User::factory()->create()->id,
                'status_changed_at' => now()->subDays(3),
            ]);
            ReservationRoom::factory()->create([
                'reservation_id' => $reservation->id,
                'room_type_id'   => $this->type->id,
                'room_id'        => $room?->id,
                'price_usd'      => 300,
            ]);
        }

        if ($folioStatus !== null) {
            $folio = Folio::factory()->create([
                'reservation_id' => $reservation->id,
                'status'         => $folioStatus,
                'subtotal_usd'   => 300,
                'total_usd'      => 300,
                'settled_at'     => $folioStatus === 'settled' ? now()->subHour() : null,
            ]);
            FolioItem::factory()->create([
                'folio_id'    => $folio->id,
                'amount_usd'  => 300,
                'source_type' => 'reservation',
                'source_id'   => $reservation->id,
            ]);
        }

        return $reservation;
    }

    private function checkOut(Reservation $reservation, array $body = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->presetToken('reception'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-out", $body);
    }

    private function roomsOf(Reservation $reservation)
    {
        return Room::whereIn('id', $reservation->rooms()->whereNotNull('room_id')->pluck('room_id'))->orderBy('number')->get();
    }

    private function forcedRows(?Reservation $reservation = null)
    {
        return DB::table('activity_log')
            ->where('description', 'reservation.check_out_forced')
            ->when($reservation, fn ($q) => $q->where('subject_id', $reservation->id))
            ->get();
    }

    private function assertStillCheckedIn(Reservation $reservation): void
    {
        $fresh = $reservation->fresh();
        $this->assertSame(ReservationStatus::CHECKED_IN, $fresh->status);
        $this->assertNull($fresh->checked_out_at);
    }

    // ── Happy path ──────────────────────────────────────────────────────

    public function test_check_out_with_a_settled_folio(): void
    {
        $reservation = $this->checkedInStay('settled');
        $room        = $this->roomsOf($reservation)->first();
        $folio       = $reservation->folio;

        $response = $this->checkOut($reservation)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Guest checked out.')
            ->assertJsonPath('data.uuid', $reservation->uuid)
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.checked_out_at', '2027-03-12T10:00:00+00:00')
            ->assertJsonPath('data.folio.uuid', $folio->uuid)
            ->assertJsonPath('data.folio.status', 'settled')
            ->assertJsonPath('data.folio.total_usd', '300.00')
            ->assertJsonPath('data.rooms.0.room_uuid', $room->uuid)
            ->assertJsonPath('data.guest.uuid', $reservation->guest->uuid);

        $this->assertSame(['uuid', 'status', 'total_usd'], array_keys($response->json('data.folio')));

        $fresh = $room->fresh();
        $this->assertSame('dirty', $fresh->status->value);
        $this->assertNull($fresh->status_changed_by);

        $history = DB::table('room_status_history')->where('room_id', $room->id)->get();
        $this->assertCount(1, $history);
        $this->assertSame('available', $history[0]->from_status);
        $this->assertSame('dirty', $history[0]->to_status);
        $this->assertSame('check-out', $history[0]->reason);
        $this->assertNull($history[0]->changed_by);
    }

    // ── The folio gate (D-06, D-07) ─────────────────────────────────────

    public function test_open_folio_is_refused(): void
    {
        $reservation = $this->checkedInStay('open');
        $folio       = $reservation->folio;
        $room        = $this->roomsOf($reservation)->first();

        $this->checkOut($reservation, [], $this->presetToken('reception'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'folio_unsettled')
            ->assertJsonPath('context.folio_uuid', $folio->uuid)
            ->assertJsonPath('context.total_usd', '300.00')
            ->assertJsonPath('context.can_force', true);

        $this->checkOut($reservation, [], $this->staffToken('reservations.create'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_unsettled')
            ->assertJsonPath('context.can_force', false);

        $this->assertSame(FolioStatus::OPEN, $folio->fresh()->status);
        $this->assertStillCheckedIn($reservation);
        $this->assertSame('available', $room->fresh()->status->value);
        $this->assertSame(0, DB::table('room_status_history')->count());
    }

    public function test_missing_folio_is_generated_then_the_gate_applies(): void
    {
        Event::fake([ReservationCheckedOut::class]);
        $reservation = $this->checkedInStay(null);
        $this->assertSame(0, Folio::count());

        $response = $this->checkOut($reservation)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_unsettled');

        // Raised after the commit: the folio built under the lock is kept.
        $folio = Folio::where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($folio);
        $this->assertSame($folio->uuid, $response->json('context.folio_uuid'));
        $this->assertSame(FolioStatus::OPEN, $folio->status);
        $this->assertSame('300.00', (string) $folio->total_usd);
        $this->assertSame(1, $folio->items()->count());
        $this->assertSame('300.00', (string) $folio->items()->first()->amount_usd);

        $this->assertStillCheckedIn($reservation);
        $this->assertSame(0, DB::table('room_status_history')->count());
        Event::assertNotDispatched(ReservationCheckedOut::class);

        // The desk settles that exact folio, then an ordinary check-out passes.
        $token = $this->presetToken('reception');
        $this->withToken($token)
            ->postJson("/api/cms/folios/{$response->json('context.folio_uuid')}/settle", ['method' => 'cash', 'amount_usd' => 300])
            ->assertOk()
            ->assertJsonPath('data.status', 'settled');

        $this->checkOut($reservation, [], $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.folio.status', 'settled');
    }

    public function test_force_without_folios_settle_is_forbidden(): void
    {
        Event::fake([ReservationCheckedOut::class]);
        $token = $this->staffToken('reservations.create');

        foreach (['open', 'settled'] as $status) {
            $reservation = $this->checkedInStay($status);
            $folio       = $reservation->folio;

            $this->checkOut($reservation, ['force' => true, 'reason' => 'Company pays'], $token)
                ->assertForbidden()
                ->assertJsonPath('error_code', 'forbidden');

            $this->assertStillCheckedIn($reservation);
            $this->assertSame($status, $folio->fresh()->status->value);
        }

        $this->assertSame(0, DB::table('room_status_history')->count());
        $this->assertCount(0, $this->forcedRows());
        Event::assertNotDispatched(ReservationCheckedOut::class);
    }

    public function test_force_requires_a_valid_reason(): void
    {
        $reservation = $this->checkedInStay('open');

        $this->checkOut($reservation, ['force' => true])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['reason'])
            ->assertJsonPath('errors.reason.0', 'The reason field is required.');

        $this->checkOut($reservation, ['force' => true, 'reason' => str_repeat('r', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->checkOut($reservation, ['force' => 'yes', 'reason' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['force']);

        $this->assertStillCheckedIn($reservation);
    }

    public function test_staff_force_checks_out_and_keeps_the_folio_open(): void
    {
        $staff       = $this->presetUser('reception');
        $reservation = $this->checkedInStay('open');
        $folio       = $reservation->folio;
        $payments    = DB::table('payments')->count();

        $this->checkOut($reservation, ['force' => true, 'reason' => 'Left early; company will pay'], $staff->createToken('t')->plainTextToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.folio.uuid', $folio->uuid)
            ->assertJsonPath('data.folio.status', 'open');

        $fresh = $folio->fresh();
        $this->assertSame(FolioStatus::OPEN, $fresh->status);
        $this->assertNull($fresh->settled_at);
        $this->assertSame($payments, DB::table('payments')->count());
        $this->assertSame('dirty', $this->roomsOf($reservation)->first()->status->value);

        $rows = $this->forcedRows($reservation);
        $this->assertCount(1, $rows);
        $this->assertSame($reservation->getMorphClass(), $rows[0]->subject_type);
        $this->assertSame($staff->getMorphClass(), $rows[0]->causer_type);
        $this->assertEquals($staff->id, $rows[0]->causer_id);
        $this->assertEquals([
            'folio_uuid'   => $folio->uuid,
            'folio_status' => 'open',
            'total_usd'    => '300.00',
            'reason'       => 'Left early; company will pay',
        ], json_decode($rows[0]->properties, true));
    }

    public function test_force_with_a_settled_folio_is_an_ordinary_check_out(): void
    {
        $reservation = $this->checkedInStay('settled');

        $this->checkOut($reservation, ['force' => true, 'reason' => 'Habit'])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.folio.status', 'settled');

        $this->assertCount(0, $this->forcedRows());
    }

    public function test_check_out_never_changes_a_settled_folio(): void
    {
        $reservation = $this->checkedInStay('settled');
        $folio       = $reservation->folio;
        $before      = [
            $folio->status, $folio->settled_at?->toIso8601String(), (string) $folio->total_usd,
            $folio->items()->orderBy('id')->pluck('id')->all(), DB::table('payments')->count(),
        ];

        $this->checkOut($reservation)->assertOk();

        $fresh = $folio->fresh();
        $this->assertSame($before, [
            $fresh->status, $fresh->settled_at?->toIso8601String(), (string) $fresh->total_usd,
            $fresh->items()->orderBy('id')->pluck('id')->all(), DB::table('payments')->count(),
        ]);
    }

    // ── Rooms (D-06: ensure dirty through UpdateRoomStatusAction) ────────

    public function test_every_assigned_room_turns_dirty(): void
    {
        $reservation = $this->checkedInStay('settled', ['available', 'maintenance']);

        $this->checkOut($reservation)->assertOk();

        $rooms = $this->roomsOf($reservation);
        $this->assertCount(2, $rooms);
        foreach ($rooms as $room) {
            $this->assertSame('dirty', $room->status->value);
            $this->assertNull($room->status_changed_by);
        }

        $history = DB::table('room_status_history')->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame(['available', 'maintenance'], $history->pluck('from_status')->sort()->values()->all());
        $this->assertSame(['check-out'], $history->pluck('reason')->unique()->values()->all());
        $this->assertSame([null], $history->pluck('changed_by')->unique()->values()->all());
    }

    public function test_an_already_dirty_room_is_left_alone(): void
    {
        $reservation = $this->checkedInStay('settled', ['dirty']);
        $room        = $this->roomsOf($reservation)->first();
        $changedBy   = $room->status_changed_by;

        $this->checkOut($reservation)->assertOk();

        $this->assertSame(0, DB::table('room_status_history')->count());
        $this->assertSame($changedBy, $room->fresh()->status_changed_by);
    }

    public function test_a_line_without_a_room_is_skipped(): void
    {
        $reservation = $this->checkedInStay('settled', [null, 'available']);

        $this->checkOut($reservation)->assertOk()->assertJsonPath('data.status', 'checked_out');

        $this->assertSame(1, DB::table('room_status_history')->count());
    }

    public function test_same_day_and_early_departure_are_allowed(): void
    {
        $this->travelTo(Carbon::parse('2027-03-10 18:00:00'));
        $sameDay = $this->checkedInStay('settled');
        $this->checkOut($sameDay)->assertOk()->assertJsonPath('data.status', 'checked_out');

        $this->travelTo(Carbon::parse('2027-03-11 08:00:00'));
        $early = $this->checkedInStay('settled');
        $this->checkOut($early)
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out')
            ->assertJsonPath('data.checked_out_at', '2027-03-11T08:00:00+00:00');
    }

    // ── Status guard ────────────────────────────────────────────────────

    public static function nonCheckedInStatuses(): array
    {
        return [
            'pending_verification' => ['pending_verification'],
            'pending'              => ['pending'],
            'confirmed'            => ['confirmed'],
            'checked_out'          => ['checked_out'],
            'cancelled'            => ['cancelled'],
        ];
    }

    #[DataProvider('nonCheckedInStatuses')]
    public function test_only_checked_in_reservations_check_out(string $status): void
    {
        $reservation = $this->checkedInStay('settled');
        $reservation->update([
            'status'          => $status,
            'hold_expires_at' => $status === 'pending_verification' ? now()->addMinutes(5) : null,
        ]);

        $this->checkOut($reservation)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state')
            ->assertJsonPath('context.status', $status)
            ->assertJsonPath('context.allowed', ['checked_in']);

        $this->assertSame($status, $reservation->fresh()->status->value);
        $this->assertSame(0, DB::table('room_status_history')->count());
    }

    public function test_second_check_out_is_refused(): void
    {
        Event::fake([ReservationCheckedOut::class]);
        $reservation = $this->checkedInStay('settled');

        $this->checkOut($reservation)->assertOk();
        $stamp   = $reservation->fresh()->checked_out_at;
        $history = DB::table('room_status_history')->count();

        $this->travelTo(Carbon::parse('2027-03-12 11:00:00'));
        $this->checkOut($reservation)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state')
            ->assertJsonPath('context.status', 'checked_out');

        $this->assertEquals($stamp, $reservation->fresh()->checked_out_at);
        $this->assertSame($history, DB::table('room_status_history')->count());
        Event::assertDispatchedTimes(ReservationCheckedOut::class, 1);
    }

    // ── Event (D-09) ────────────────────────────────────────────────────

    public function test_reservation_checked_out_event_payload(): void
    {
        $this->assertContains(ShouldDispatchAfterCommit::class, class_implements(ReservationCheckedOut::class));
        Event::fake([ReservationCheckedOut::class]);
        $staff = $this->presetUser('reception');
        $token = $staff->createToken('t')->plainTextToken;

        $ordinary = $this->checkedInStay('settled');
        $forced   = $this->checkedInStay('open');
        $settled  = $this->checkedInStay('settled');
        $refused  = $this->checkedInStay('open');

        $this->checkOut($ordinary, [], $token)->assertOk();
        $this->checkOut($forced, ['force' => true, 'reason' => 'Company pays'], $token)->assertOk();
        $this->checkOut($settled, ['force' => true, 'reason' => 'Habit'], $token)->assertOk();
        $this->checkOut($refused, [], $token)->assertStatus(422);

        Event::assertDispatchedTimes(ReservationCheckedOut::class, 3);
        $expect = [
            $ordinary->uuid => CheckOutMode::NONE,
            $forced->uuid   => CheckOutMode::STAFF_FORCE,
            $settled->uuid  => CheckOutMode::NONE,
        ];
        foreach ($expect as $uuid => $mode) {
            Event::assertDispatched(ReservationCheckedOut::class, fn (ReservationCheckedOut $e) => $e->reservation->uuid === $uuid
                && $e->mode === $mode
                && $e->actorId === $staff->id);
        }
        Event::assertNotDispatched(ReservationCheckedOut::class, fn (ReservationCheckedOut $e) => $e->reservation->uuid === $refused->uuid);
    }

    public function test_event_is_dispatched_after_commit(): void
    {
        $reservation = $this->checkedInStay('settled');
        $levels      = [];
        Event::listen(ReservationCheckedOut::class, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });
        $outer = DB::transactionLevel();

        $this->checkOut($reservation)->assertOk();

        $this->assertSame([$outer], $levels);
    }

    // ── Folio immutability after a forced check-out ─────────────────────

    public function test_generate_after_a_forced_check_out_keeps_the_total(): void
    {
        $reservation = $this->checkedInStay('open');
        $this->checkOut($reservation, ['force' => true, 'reason' => 'Company pays'])->assertOk();

        $folio   = $reservation->folio()->first();
        $total   = (string) $folio->total_usd;
        $itemIds = $folio->items()->orderBy('id')->pluck('id')->all();

        $cabana = PoolCabana::factory()->create(['price_usd' => 80]);
        ServiceBooking::factory()->create([
            'guest_id'       => $reservation->guest_id,
            'reservation_id' => $reservation->id,
            'bookable_type'  => 'pool_cabana',
            'bookable_id'    => $cabana->id,
            'status'         => ServiceBookingStatus::CONFIRMED,
        ]);

        $this->withToken($this->staffToken('folios.view'))
            ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
            ->assertOk()
            ->assertJsonPath('data.total_usd', $total);

        $fresh = $folio->fresh();
        $this->assertSame($total, (string) $fresh->total_usd);
        $this->assertSame($itemIds, $fresh->items()->orderBy('id')->pluck('id')->all());
    }

    public function test_board_reads_the_room_dirty_and_vacant(): void
    {
        $reservation = $this->checkedInStay('settled');
        $room        = $this->roomsOf($reservation)->first();

        $this->checkOut($reservation)->assertOk();

        $row = collect(
            $this->withToken($this->presetToken('reception'))
                ->getJson('/api/front-desk/room-board?date=2027-03-12')
                ->assertOk()
                ->json('data.items')
        )->firstWhere('uuid', $room->uuid);

        $this->assertSame('dirty', $row['housekeeping_status']);
        $this->assertSame('vacant', $row['occupancy']);
    }

    public function test_folio_summary_only_on_check_out(): void
    {
        $reservation = $this->checkedInStay('settled');

        $this->withToken($this->staffToken('reservations.view'))
            ->getJson("/api/cms/reservations/{$reservation->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('data.folio');

        $items = $this->withToken($this->staffToken('reservations.view'))
            ->getJson('/api/cms/reservations')
            ->assertOk()
            ->json('data.items');
        foreach ($items as $item) {
            $this->assertArrayNotHasKey('folio', $item);
        }
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_requires_reservations_create(): void
    {
        $reservation = $this->checkedInStay('settled');

        foreach ([$this->staffToken('reservations.view'), $this->presetToken('housekeeping'), $this->staffToken('folios.settle')] as $token) {
            $this->checkOut($reservation, [], $token)
                ->assertForbidden()
                ->assertJsonPath('error_code', 'forbidden');
        }

        $this->assertStillCheckedIn($reservation);
    }

    public function test_requires_authentication(): void
    {
        $reservation = $this->checkedInStay('settled');

        $this->postJson("/api/cms/reservations/{$reservation->uuid}/check-out")
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');

        $this->withToken($reservation->guest->createToken('guest')->plainTextToken)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-out")
            ->assertUnauthorized();

        $this->assertStillCheckedIn($reservation);
    }

    public function test_unknown_reservation_returns_404(): void
    {
        $this->withToken($this->presetToken('reception'))
            ->postJson('/api/cms/reservations/'.Str::uuid().'/check-out')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }
}
