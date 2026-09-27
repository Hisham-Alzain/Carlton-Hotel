<?php

namespace Tests\Feature\Operations;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\CheckOutMode;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceBooking;
use App\Models\ServiceRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Operations\DepartureServiceService;
use Database\Seeders\GuestServiceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RecordsRowLocks;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * GET /api/departure-services (Phase 6, SVC-02; D-18, D-19, D-21) and
 * PATCH /api/departure-services/{uuid}/status (SVC-03; D-22).
 *
 * Hotel timezone Asia/Damascus (UTC+3); now = 2027-03-12 09:00 UTC, so the
 * hotel-local today D is 2027-03-12, whose window is
 * [2027-03-11 21:00, 2027-03-12 21:00) UTC.
 */
class DepartureServicesTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    private const URL = '/api/departure-services';

    private const D = '2027-03-12';

    private int $roomNumber = 400;

    private FakeFirebaseService $firebase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GuestServiceCatalogSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 09:00:00'));
        $this->firebase = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);
    }

    // ── fixtures ──────────────────────────────────────────────────────────

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

    private function list(array $query = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('service_requests.view'))
            ->getJson(self::URL . ($query ? '?' . http_build_query($query) : ''));
    }

    private function items(array $query = []): array
    {
        return $this->list($query)->assertOk()->json('data.items');
    }

    private function uuids(array $query = []): array
    {
        return array_column($this->items($query), 'uuid');
    }

    /** A checked-in stay leaving on D with one room, and a guest with name and phone. */
    private function departingStay(array $attrs = [], ?string $roomNumber = null): Reservation
    {
        $guest       = Guest::factory()->create(['name' => 'Rania Khoury', 'phone' => '+963944' . random_int(100000, 999999)]);
        $reservation = Reservation::factory()->checkedIn()->create(array_merge([
            'guest_id'  => $guest->id,
            'check_in'  => '2027-03-09',
            'check_out' => self::D,
        ], $attrs));

        $type = RoomType::factory()->create();
        $room = Room::factory()->create(['room_type_id' => $type->id, 'number' => $roomNumber ?? (string) ++$this->roomNumber]);
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $type->id, 'room_id' => $room->id]);

        return $reservation;
    }

    /** A stay already checked out at the given UTC instant. */
    private function checkedOutStay(string $checkedOutAt, string $checkOut = self::D, ?CheckOutMode $mode = CheckOutMode::NONE): Reservation
    {
        return $this->departingStay([
            'status'         => 'checked_out',
            'check_out'      => $checkOut,
            'checked_out_at' => $checkedOutAt,
            'check_out_mode' => $mode,
        ]);
    }

    private function transfer(Reservation $reservation, array $attrs = []): ServiceBooking
    {
        return ServiceBooking::factory()->for(Transfer::factory(), 'bookable')->create(array_merge([
            'guest_id'       => $reservation->guest_id,
            'reservation_id' => $reservation->id,
            'scheduled_at'   => '2027-03-12 08:00:00',
            'status'         => ServiceBookingStatus::PENDING,
        ], $attrs));
    }

    private function request(Reservation $reservation, string $type, array $attrs = []): ServiceRequest
    {
        return ServiceRequest::factory()->create(array_merge([
            'guest_id'       => $reservation->guest_id,
            'reservation_id' => $reservation->id,
            'type'           => $type,
            'department'     => $type === 'late_checkout' ? 'reception' : 'concierge',
            'status'         => ServiceRequestStatus::NEW,
        ], $attrs));
    }

    // ── GET /departure-services ───────────────────────────────────────────

    public function test_requires_a_token(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_requires_service_requests_view(): void
    {
        $this->list([], $this->staffToken('housekeeping.view'))->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_reception_and_concierge_can_list(): void
    {
        $this->list([], $this->presetToken('reception'))->assertOk();
        $this->list([], $this->presetToken('concierge'))->assertOk();
    }

    public function test_lists_all_four_kinds_with_the_row_shape(): void
    {
        $stay     = $this->departingStay([], '301');
        $transfer = $this->transfer($stay);
        $late     = $this->request($stay, 'late_checkout', ['notes' => 'Until 2pm']);
        $luggage  = $this->request($stay, 'luggage', ['status' => ServiceRequestStatus::IN_PROGRESS, 'assigned_user_id' => User::factory()]);
        $express  = $this->checkedOutStay('2027-03-12 07:30:00', self::D, CheckOutMode::GUEST_EXPRESS);

        $response = $this->list()->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.meta', ['count' => 4, 'truncated' => false]);
        $rows = collect($response->json('data.items'))->keyBy('uuid');

        foreach ($rows as $row) {
            $this->assertSame([
                'uuid', 'kind', 'source_type', 'status', 'stage', 'allowed_statuses', 'scheduled_at', 'notes',
                'reservation', 'guest', 'room_number', 'assigned_user_uuid', 'created_at',
            ], array_keys($row));
            $this->assertSame(['uuid', 'booking_code', 'check_out', 'checked_out_at', 'status'], array_keys($row['reservation']));
            $this->assertSame(['uuid', 'name', 'phone'], array_keys($row['guest']));
        }

        $t = $rows[$transfer->uuid];
        $this->assertSame(['transfer', 'service_booking', 'pending', 'open'], [$t['kind'], $t['source_type'], $t['status'], $t['stage']]);
        $this->assertSame(['confirmed', 'cancelled'], $t['allowed_statuses']);
        $this->assertSame('2027-03-12T08:00:00+00:00', $t['scheduled_at']);
        $this->assertSame('301', $t['room_number']);
        $this->assertSame(['uuid' => $stay->guest->uuid, 'name' => 'Rania Khoury', 'phone' => $stay->guest->phone], $t['guest']);
        $this->assertSame($stay->uuid, $t['reservation']['uuid']);
        $this->assertSame(self::D, $t['reservation']['check_out']);
        $this->assertSame('checked_in', $t['reservation']['status']);

        $l = $rows[$late->uuid];
        $this->assertSame(['late_checkout', 'service_request', 'new', 'open'], [$l['kind'], $l['source_type'], $l['status'], $l['stage']]);
        $this->assertSame(['in_progress', 'completed', 'cancelled'], $l['allowed_statuses']);
        $this->assertNull($l['scheduled_at']);
        $this->assertSame('Until 2pm', $l['notes']);

        $g = $rows[$luggage->uuid];
        $this->assertSame(['luggage', 'in_progress'], [$g['kind'], $g['stage']]);
        $this->assertSame($luggage->assignedUser->uuid, $g['assigned_user_uuid']);

        $e = $rows[$express->uuid];
        $this->assertSame(['express_checkout', 'reservation', 'completed', 'resolved', []],
            [$e['kind'], $e['source_type'], $e['status'], $e['stage'], $e['allowed_statuses']]);
        $this->assertSame('2027-03-12T07:30:00+00:00', $e['scheduled_at']);
        $this->assertNull($e['notes']);
        $this->assertNull($e['assigned_user_uuid']);
    }

    public function test_stays_are_selected_by_check_out_date_or_hotel_day_check_out(): void
    {
        $leaving     = $this->transfer($this->departingStay());
        $lateLocal   = $this->transfer($this->checkedOutStay('2027-03-12 20:59:59', '2027-03-13'));
        $nextDay     = $this->transfer($this->checkedOutStay('2027-03-12 21:00:00', '2027-03-13'));
        $notArrived  = $this->transfer($this->departingStay(['status' => 'confirmed']));
        $cancelled   = $this->transfer($this->departingStay(['status' => 'cancelled']));

        $uuids = $this->uuids();

        $this->assertContains($leaving->uuid, $uuids);
        $this->assertContains($lateLocal->uuid, $uuids);
        $this->assertNotContains($nextDay->uuid, $uuids);
        $this->assertNotContains($notArrived->uuid, $uuids);
        $this->assertNotContains($cancelled->uuid, $uuids);
    }

    public function test_arrival_pickups_are_excluded(): void
    {
        $stay      = $this->departingStay();
        $midnight  = $this->transfer($stay, ['scheduled_at' => '2027-03-11 21:00:00']);
        $yesterday = $this->transfer($stay, ['scheduled_at' => '2027-03-11 20:59:00']);

        $uuids = $this->uuids();

        $this->assertContains($midnight->uuid, $uuids);
        $this->assertNotContains($yesterday->uuid, $uuids);
    }

    public function test_spa_bookings_are_not_departure_services(): void
    {
        $stay = $this->departingStay();
        ServiceBooking::factory()->create(['guest_id' => $stay->guest_id, 'reservation_id' => $stay->id, 'scheduled_at' => '2027-03-12 06:00:00']);
        $this->request($stay, 'room_service', ['department' => 'kitchen']);

        $this->list()->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_date_param(): void
    {
        $tomorrow = $this->transfer($this->departingStay(['check_out' => '2027-03-13']), ['scheduled_at' => '2027-03-13 06:00:00']);
        $this->transfer($this->departingStay());

        $this->assertSame([$tomorrow->uuid], $this->uuids(['date' => '2027-03-13']));

        foreach (['2027-04-12', '2027-02-09', '12-03-2027'] as $bad) {
            $this->list(['date' => $bad])
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors('date');
        }

        $this->list(['date' => '2027-04-11'])->assertOk();
    }

    public function test_kind_and_status_filters(): void
    {
        $stay     = $this->departingStay();
        $transfer = $this->transfer($stay);
        $late     = $this->request($stay, 'late_checkout');
        $luggage  = $this->request($stay, 'luggage', ['status' => ServiceRequestStatus::COMPLETED]);

        $this->assertSame([$transfer->uuid], $this->uuids(['kind' => 'transfer']));
        $this->assertEqualsCanonicalizing([$late->uuid, $luggage->uuid], $this->uuids(['kind' => ['in' => 'late_checkout,luggage']]));
        $this->assertEqualsCanonicalizing([$late->uuid, $luggage->uuid], $this->uuids(['kind' => 'late_checkout,luggage']));
        $this->assertEqualsCanonicalizing([$transfer->uuid, $late->uuid], $this->uuids(['status' => ['in' => 'pending,new']]));

        $this->list(['kind' => 'spa'])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->list(['status' => 'bogus'])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_ordering_is_stable(): void
    {
        $stay  = $this->departingStay();
        $req   = $this->request($stay, 'luggage', ['created_at' => '2027-03-12 01:00:00']);
        $late  = $this->transfer($stay, ['scheduled_at' => '2027-03-12 10:00:00', 'created_at' => '2027-03-12 02:00:00']);
        $early = $this->transfer($stay, ['scheduled_at' => '2027-03-12 06:00:00', 'created_at' => '2027-03-12 05:00:00']);
        $tieB  = $this->transfer($stay, ['scheduled_at' => '2027-03-12 10:00:00', 'created_at' => '2027-03-12 02:00:00']);

        $tied     = collect([$late->uuid, $tieB->uuid])->sort()->values()->all();
        $expected = [$early->uuid, ...$tied, $req->uuid];

        $this->assertSame($expected, $this->uuids());
        $this->assertSame($expected, $this->uuids());
    }

    public function test_empty_day(): void
    {
        $this->list()->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.meta.count', 0)
            ->assertJsonPath('data.meta.truncated', false);
    }

    public function test_truncates_at_five_hundred(): void
    {
        $stay = $this->departingStay();
        ServiceRequest::factory()->count(501)->create([
            'guest_id'       => $stay->guest_id,
            'reservation_id' => $stay->id,
            'type'           => 'luggage',
            'department'     => 'concierge',
        ]);

        $this->list()->assertOk()
            ->assertJsonCount(500, 'data.items')
            ->assertJsonPath('data.meta.count', 500)
            ->assertJsonPath('data.meta.truncated', true);
    }

    public function test_service_path_is_at_most_seven_queries(): void
    {
        $stay = $this->departingStay();
        $this->transfer($stay);
        $this->request($stay, 'late_checkout', ['assigned_user_id' => User::factory()]);
        $this->request($stay, 'luggage');
        $this->checkedOutStay('2027-03-12 07:30:00', self::D, CheckOutMode::GUEST_EXPRESS);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = app(DepartureServiceService::class)->index(self::D, [], []);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(4, $result['data']['meta']['count']);
        $this->assertLessThanOrEqual(7, $queries);
    }

    public function test_listing_is_a_pure_read(): void
    {
        $stay = $this->departingStay();
        $this->transfer($stay);
        $this->request($stay, 'luggage');

        $token  = $this->staffToken('service_requests.view');
        $counts = fn () => [ServiceBooking::count(), ServiceRequest::count(), Reservation::count(), DB::table('activity_log')->count()];
        $before = $counts();

        $this->list([], $token)->assertOk();
        $this->assertSame($before, $counts());
        $this->assertSame([], $this->lockedSelects(fn () => app(DepartureServiceService::class)->index(self::D, [], [])));
    }

    // ── PATCH /departure-services/{uuid}/status (06-08, D-22) ─────────────

    private function patchStatus(string $uuid, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->presetToken('concierge'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson(self::URL . "/{$uuid}/status", $body);
    }

    public function test_transfer_booking_moves_through_its_table(): void
    {
        $booking = $this->transfer($this->departingStay());

        $this->patchStatus($booking->uuid, ['status' => 'confirmed', 'reason' => 'driver booked'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.departure_service_status_updated'))
            ->assertJsonPath('data.uuid', $booking->uuid)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.stage', 'in_progress')
            ->assertJsonPath('data.allowed_statuses', ['completed', 'cancelled']);

        $this->patchStatus($booking->uuid, ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'resolved')
            ->assertJsonPath('data.allowed_statuses', []);

        $row = collect($this->items())->firstWhere('uuid', $booking->uuid);
        $this->assertSame('completed', $row['status']);
        $this->assertSame('resolved', $row['stage']);
    }

    public function test_disallowed_booking_transition_is_422(): void
    {
        $booking = $this->transfer($this->departingStay(), ['status' => ServiceBookingStatus::COMPLETED]);

        $this->patchStatus($booking->uuid, ['status' => 'confirmed'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'service_booking_transition_invalid')
            ->assertJsonPath('context.from', 'completed')
            ->assertJsonPath('context.to', 'confirmed')
            ->assertJsonPath('context.allowed', []);

        $this->assertSame(ServiceBookingStatus::COMPLETED, $booking->fresh()->status);
    }

    public function test_late_checkout_request_is_progressed(): void
    {
        $request = $this->request($this->departingStay(), 'late_checkout');

        $this->patchStatus($request->uuid, ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.kind', 'late_checkout')
            ->assertJsonPath('data.stage', 'in_progress');

        $this->patchStatus($request->uuid, ['status' => 'completed', 'reason' => 'granted until 14:00'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.stage', 'resolved');

        $this->assertSame(ServiceRequestStatus::COMPLETED, $request->fresh()->status);
        $this->assertNotEmpty(array_filter($this->firebase->mirrors, fn ($m) => $m['document'] === "service_request_{$request->uuid}"));
    }

    public function test_wrong_family_status_is_validation_failed(): void
    {
        $stay    = $this->departingStay();
        $request = $this->request($stay, 'luggage');
        $booking = $this->transfer($stay);

        $this->patchStatus($request->uuid, ['status' => 'confirmed'])
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors('status');
        $this->patchStatus($booking->uuid, ['status' => 'in_progress'])
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors('status');
        $this->patchStatus($booking->uuid, ['status' => 'bogus'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->patchStatus($booking->uuid, [])
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->assertSame(ServiceRequestStatus::NEW, $request->fresh()->status);
        $this->assertSame(ServiceBookingStatus::PENDING, $booking->fresh()->status);
    }

    public function test_express_row_is_readonly(): void
    {
        $express = $this->checkedOutStay('2027-03-12 07:30:00', self::D, CheckOutMode::GUEST_EXPRESS);
        $before  = $express->fresh()->only(['status', 'check_out_mode', 'checked_out_at', 'updated_at']);

        $this->patchStatus($express->uuid, ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'departure_service_readonly');

        $this->assertEquals($before, $express->fresh()->only(['status', 'check_out_mode', 'checked_out_at', 'updated_at']));
    }

    public function test_resolution_and_hints(): void
    {
        $stay    = $this->departingStay();
        $booking = $this->transfer($stay);
        $spa     = ServiceBooking::factory()->create(['guest_id' => $stay->guest_id, 'reservation_id' => $stay->id]);
        $food    = $this->request($stay, 'room_service', ['department' => 'kitchen']);
        $staff   = $this->checkedOutStay('2027-03-12 07:00:00', self::D, CheckOutMode::NONE);

        $this->patchStatus($booking->uuid, ['status' => 'confirmed', 'source_type' => 'service_request'])->assertStatus(404);
        $this->patchStatus($booking->uuid, ['status' => 'confirmed', 'source_type' => 'service_booking'])->assertOk();
        $this->patchStatus('00000000-0000-0000-0000-000000000000', ['status' => 'confirmed'])->assertStatus(404)->assertJsonPath('error_code', 'not_found');
        $this->patchStatus($spa->uuid, ['status' => 'confirmed'])->assertStatus(404);
        $this->patchStatus($food->uuid, ['status' => 'completed'])->assertStatus(404);
        $this->patchStatus($staff->uuid, ['status' => 'completed'])->assertStatus(404);
        $this->patchStatus($booking->uuid, ['status' => 'completed', 'source_type' => 'banana'])->assertStatus(422)->assertJsonValidationErrors('source_type');

        $this->assertSame(ServiceBookingStatus::PENDING, $spa->fresh()->status);
        $this->assertSame(ServiceRequestStatus::NEW, $food->fresh()->status);
    }

    public function test_auth(): void
    {
        $booking = $this->transfer($this->departingStay());

        $this->patchJson(self::URL . "/{$booking->uuid}/status", ['status' => 'confirmed'])->assertStatus(401);
        $this->patchStatus($booking->uuid, ['status' => 'confirmed'], $this->staffToken('service_requests.view'))
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->patchStatus($booking->uuid, ['status' => 'confirmed', 'reason' => str_repeat('x', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->assertSame(ServiceBookingStatus::PENDING, $booking->fresh()->status);

        // Post-build consultant ruling: the reception preset holds service_requests.update.
        $this->patchStatus($booking->uuid, ['status' => 'confirmed'], $this->presetToken('reception'))->assertOk();
        $this->assertSame(ServiceBookingStatus::CONFIRMED, $booking->fresh()->status);
    }

    public function test_second_confirm_is_rejected(): void
    {
        $booking = $this->transfer($this->departingStay());

        $this->patchStatus($booking->uuid, ['status' => 'confirmed'])->assertOk();
        $this->patchStatus($booking->uuid, ['status' => 'confirmed'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'service_booking_transition_invalid')
            ->assertJsonPath('context.from', 'confirmed');
    }
}
