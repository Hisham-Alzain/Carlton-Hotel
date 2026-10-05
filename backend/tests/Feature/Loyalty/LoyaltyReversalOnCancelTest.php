<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Booking\CancelReservationAction;
use App\Actions\Folio\GenerateFolioAction;
use App\Actions\Loyalty\EarnLoyaltyPointsAction;
use App\Actions\Loyalty\ReverseLoyaltyForReservationAction;
use App\Enums\LoyaltyApplicationStatus;
use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyVoucherStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationStateException;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReservationApplication;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\HotelClock;
use App\Support\LoyaltyLedger;
use App\Support\LoyaltyProgram;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\CountsDomainQueries;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-17 / LOY-18 (Q1, Q2, Q3, Q13, M-3, M-4, M-6, M-9): cancelling a
 * reservation refunds spent points, restores a used voucher and claws back
 * folio earnings, exactly once, in one lock order, and never leaves a negative
 * balance.
 */
class LoyaltyReversalOnCancelTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use CountsDomainQueries;
    use RecordsRowLocks;
    use RefreshDatabase;

    private Guest $guest;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->configureLoyalty([
            'redeem_value_usd' => '0.0100',
            'max_redeem_percent' => '50.00',
            'min_redeem_points' => 100,
            'earn_rate' => '1.0000',
        ]);
        $this->guest = Guest::factory()->create();
        // 2 nights x 150.00 = 300.00; six rooms so several bookings fit.
        $this->roomType = RoomType::factory()->create(['base_price_usd' => 150, 'is_active' => true]);
        Room::factory()->count(6)->create(['room_type_id' => $this->roomType->id, 'is_active' => true]);
    }

    /** Book as the guest (201) and return the reservation. */
    private function bookWith(array $extra, string $key = 'K-1'): Reservation
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($this->guestToken($this->guest))
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/reservations', array_merge([
                'room_type_uuid' => $this->roomType->uuid,
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(12)->toDateString(),
                'payment_method' => 'on_arrival',
            ], $extra))
            ->assertStatus(201);

        return Reservation::where('uuid', $response->json('data.uuid'))->firstOrFail();
    }

    private function cancelAsGuest(Reservation $reservation): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->guestToken($this->guest))->deleteJson('/api/reservations/'.$reservation->uuid);
    }

    private function cancelAsStaff(Reservation $reservation): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->staffToken('reservations.cancel'))->deleteJson('/api/cms/reservations/'.$reservation->uuid);
    }

    private function voucher(array $overrides = []): LoyaltyVoucher
    {
        return LoyaltyVoucher::factory()->create(array_merge(['guest_id' => $this->guest->id], $overrides));
    }

    /**
     * A confirmed stay of `$total` for `$guest` with a generated folio, optionally already earned on
     * (the real EarnLoyaltyPointsAction, called the way the settle actions call it).
     *
     * @return array{0: Reservation, 1: Folio}
     */
    private function stayWithFolio(Guest $guest, bool $earned = true, string $total = '300.00'): array
    {
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $guest->id, 'total_usd' => $total]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];

        if ($earned) {
            DB::transaction(fn () => app(EarnLoyaltyPointsAction::class)->handle(
                Folio::query()->whereKey($folio->id)->lockForUpdate()->firstOrFail(),
            ));
        }

        return [$reservation, $folio];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return $this->loyaltyRowCounts();
    }

    public function test_cancelling_a_points_booking_refunds_the_points_to_the_original_batch(): void
    {
        $batch = $this->grantPoints($this->guest, 20000);
        $reservation = $this->bookWith(['loyalty_points' => 10000]);
        $redeem = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->sole();
        $this->assertSame(10000, app(LoyaltyLedger::class)->available($this->guest->id));

        $this->cancelAsGuest($reservation)->assertStatus(204);

        $refund = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REFUND->value)->sole();
        $this->assertSame(10000, $refund->points);
        $this->assertSame($redeem->id, (int) $refund->reverses_entry_id);
        $this->assertSame($reservation->id, (int) $refund->reservation_id);

        $batch->refresh();
        $this->assertSame(20000, $batch->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $batch->status);

        $application = LoyaltyReservationApplication::sole();
        $this->assertSame(LoyaltyApplicationStatus::REVERSED, $application->status);
        $this->assertNotNull($application->reversed_at);
        $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
        $this->assertSame(ReservationStatus::CANCELLED, $reservation->fresh()->status);
    }

    public function test_an_expired_source_batch_is_not_revived_and_a_fresh_refund_batch_takes_the_points(): void
    {
        $source = $this->grantPoints($this->guest, 20000, now()->addDays(60));
        $reservation = $this->bookWith(['loyalty_points' => 10000]);

        $this->travelTo(now()->addDays(61));
        $this->cancelAsGuest($reservation)->assertStatus(204);

        $refundBatch = LoyaltyEarnBatch::where('source', LoyaltyBatchSource::REFUND->value)->sole();
        $this->assertSame(10000, $refundBatch->points);
        $this->assertSame(10000, $refundBatch->points_remaining);
        $this->assertSame(
            LoyaltyProgram::current()->expiresAtFrom(now())->toDateTimeString(),
            $refundBatch->expires_at->toDateTimeString(),
        );

        $source->refresh();
        $this->assertSame(10000, $source->points_remaining);
        $this->assertSame(LoyaltyBatchStatus::ACTIVE, $source->status);
        $this->assertSame(10000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_cancelling_restores_a_used_voucher_with_its_expiry_unchanged(): void
    {
        $voucher = $this->voucher();
        $expiresAt = $voucher->expires_at->toDateTimeString();
        $reservation = $this->bookWith(['voucher_code' => $voucher->code]);
        $this->assertSame(LoyaltyVoucherStatus::USED, $voucher->fresh()->status);

        $this->cancelAsGuest($reservation)->assertStatus(204);

        $fresh = $voucher->fresh();
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $fresh->status);
        $this->assertNull($fresh->reservation_id);
        $this->assertNull($fresh->used_at);
        $this->assertSame($expiresAt, $fresh->expires_at->toDateTimeString());
        $this->assertSame(LoyaltyApplicationStatus::REVERSED, LoyaltyReservationApplication::sole()->status);
    }

    public function test_a_voucher_restored_after_its_expiry_gets_the_grace_period(): void
    {
        $voucher = $this->voucher();
        $reservation = $this->bookWith(['voucher_code' => $voucher->code]);

        $this->travelTo(now()->addDays(91));
        $this->cancelAsGuest($reservation)->assertStatus(204);

        $expected = HotelClock::today()
            ->addDays((int) config('loyalty.restored_voucher_grace_days'))
            ->endOfDay()
            ->utc();

        $fresh = $voucher->fresh();
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $fresh->status);
        $this->assertNull($fresh->reservation_id);
        $this->assertSame($expected->toDateTimeString(), $fresh->expires_at->toDateTimeString());
        $this->assertTrue($fresh->expires_at->gt(now()));
    }

    public function test_staff_cancel_performs_the_same_reversal(): void
    {
        $this->grantPoints($this->guest, 20000);
        $voucher = $this->voucher();
        $withPoints = $this->bookWith(['loyalty_points' => 10000], 'K-1');
        $withVoucher = $this->bookWith(['voucher_code' => $voucher->code], 'K-2');

        $this->cancelAsStaff($withPoints)->assertStatus(204);
        $this->cancelAsStaff($withVoucher)->assertStatus(204);

        $this->assertSame(1, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REFUND->value)->count());
        $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $voucher->fresh()->status);
        $this->assertNull($voucher->fresh()->reservation_id);
        $this->assertSame(2, LoyaltyReservationApplication::where('status', LoyaltyApplicationStatus::REVERSED->value)->count());
    }

    public function test_cancel_endpoints_still_require_authentication_and_the_cancel_permission(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $this->guest->id]);

        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/reservations/'.$reservation->uuid)->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/cms/reservations/'.$reservation->uuid)->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken())->deleteJson('/api/cms/reservations/'.$reservation->uuid)->assertStatus(403);

        $this->assertSame(ReservationStatus::CONFIRMED, $reservation->fresh()->status);
    }

    public function test_cancelling_a_settled_stay_claws_back_its_earnings(): void
    {
        [$reservation, $folio] = $this->stayWithFolio($this->guest);
        $earn = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EARN->value)->sole();
        $this->assertSame(300, $earn->points);

        $this->cancelAsGuest($reservation)->assertStatus(204);

        $clawback = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::CLAWBACK->value)->sole();
        $this->assertSame(-300, $clawback->points);
        $this->assertSame(0, $clawback->shortfall_points);
        $this->assertSame($earn->id, (int) $clawback->reverses_entry_id);
        $this->assertSame($folio->id, (int) $clawback->folio_id);

        $origin = LoyaltyEarnBatch::findOrFail($earn->batch_id);
        $this->assertSame(LoyaltyBatchStatus::REVERSED, $origin->status);
        $this->assertSame(0, $origin->points_remaining);
        $this->assertSame(0, app(LoyaltyLedger::class)->available($this->guest->id));
        $this->assertSame(0, Activity::where('description', 'loyalty.clawback_shortfall')->count());
    }

    public function test_partly_spent_earnings_are_clawed_back_to_zero_and_the_shortfall_is_recorded(): void
    {
        [$reservation, $folio] = $this->stayWithFolio($this->guest);
        // 200 of the 300 earned points were spent elsewhere; the guest holds 50 more from another source.
        LoyaltyEarnBatch::where('folio_id', $folio->id)->update(['points_remaining' => 100]);
        $this->grantPoints($this->guest, 50);

        $this->cancelAsGuest($reservation)->assertStatus(204);

        $clawback = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::CLAWBACK->value)->sole();
        $this->assertSame(-150, $clawback->points);
        $this->assertSame(150, $clawback->shortfall_points);
        $this->assertSame(0, app(LoyaltyLedger::class)->available($this->guest->id));
        $this->assertSame(0, LoyaltyEarnBatch::where('guest_id', $this->guest->id)->where('points_remaining', '<', 0)->count());

        $log = Activity::where('description', 'loyalty.clawback_shortfall')->sole();
        $this->assertSame(Guest::class, $log->subject_type);
        $this->assertSame($this->guest->id, (int) $log->subject_id);
        $this->assertSame(150, $log->properties['shortfall_points']);
        $this->assertSame($reservation->uuid, $log->properties['reservation_uuid']);
    }

    public function test_fully_spent_earnings_do_not_block_the_cancel_or_go_negative(): void
    {
        [$reservation, $folio] = $this->stayWithFolio($this->guest);
        LoyaltyEarnBatch::where('folio_id', $folio->id)->update([
            'points_remaining' => 0,
            'status' => LoyaltyBatchStatus::DEPLETED->value,
        ]);

        $this->cancelAsGuest($reservation)->assertStatus(204);

        $clawback = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::CLAWBACK->value)->sole();
        $this->assertSame(0, $clawback->points);
        $this->assertSame(300, $clawback->shortfall_points);
        $this->assertSame(0, app(LoyaltyLedger::class)->available($this->guest->id));
        $this->assertSame(
            300,
            Activity::where('description', 'loyalty.clawback_shortfall')->sole()->properties['shortfall_points'],
        );
        $this->assertSame(ReservationStatus::CANCELLED, $reservation->fresh()->status);
    }

    public function test_a_points_booking_that_also_earned_refunds_first_then_claws_back(): void
    {
        $this->grantPoints($this->guest, 20000);
        $reservation = $this->bookWith(['loyalty_points' => 10000]);

        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        DB::transaction(fn () => app(EarnLoyaltyPointsAction::class)->handle(
            Folio::query()->whereKey($folio->id)->lockForUpdate()->firstOrFail(),
        ));
        $earn = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EARN->value)->where('folio_id', $folio->id)->sole();
        $this->assertGreaterThan(0, $earn->points);

        $this->cancelAsStaff($reservation)->assertStatus(204);

        $refund = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REFUND->value)->sole();
        $clawback = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::CLAWBACK->value)->sole();
        $this->assertLessThan($clawback->id, $refund->id, 'refund is written before the clawback');
        $this->assertSame(10000, $refund->points);
        $this->assertSame(-$earn->points, $clawback->points);
        $this->assertSame(0, $clawback->shortfall_points);
        $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_a_second_cancel_is_a_422_that_writes_nothing(): void
    {
        $this->grantPoints($this->guest, 20000);
        $reservation = $this->bookWith(['loyalty_points' => 10000]);
        $this->cancelAsGuest($reservation)->assertStatus(204);
        $before = $this->counts();

        $this->cancelAsGuest($reservation)->assertStatus(422)->assertJsonPath('error_code', 'reservation_state');
        $this->cancelAsStaff($reservation)->assertStatus(422)->assertJsonPath('error_code', 'reservation_state');

        $this->assertSame($before, $this->counts());
        $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_the_reversal_action_writes_nothing_the_second_time(): void
    {
        $this->grantPoints($this->guest, 20000);
        $voucher = $this->voucher();
        $reservation = $this->bookWith(['loyalty_points' => 10000]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        DB::transaction(fn () => app(EarnLoyaltyPointsAction::class)->handle(
            Folio::query()->whereKey($folio->id)->lockForUpdate()->firstOrFail(),
        ));
        LoyaltyEarnBatch::where('folio_id', $folio->id)->update(['points_remaining' => 0, 'status' => LoyaltyBatchStatus::DEPLETED->value]);

        $this->cancelAsGuest($reservation)->assertStatus(204);
        $before = $this->counts();
        $logs = Activity::count();
        $voucherBefore = $voucher->fresh()->getAttributes();

        DB::transaction(function () use ($reservation): void {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            app(ReverseLoyaltyForReservationAction::class)->handle($locked);
        });

        $this->assertSame($before, $this->counts());
        $this->assertSame($logs, Activity::count(), 'no second shortfall (or any other) activity row');
        $this->assertSame($voucherBefore, $voucher->fresh()->getAttributes());
    }

    public function test_the_status_is_rechecked_under_the_row_lock(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $this->guest->id]);
        $stale = Reservation::findOrFail($reservation->id);
        DB::table('reservations')->where('id', $reservation->id)->update(['status' => ReservationStatus::CHECKED_IN->value]);

        try {
            app(CancelReservationAction::class)->handle($stale);
            $this->fail('A reservation that is no longer cancellable must not be cancelled from a stale instance.');
        } catch (ReservationStateException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ReservationStatus::CHECKED_IN, $reservation->fresh()->status);
    }

    public function test_the_cancel_action_keeps_its_return_contract(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $this->guest->id]);

        $this->assertSame(['data' => null, 'code' => 204], app(CancelReservationAction::class)->handle($reservation));
        $this->assertSame(ReservationStatus::CANCELLED, $reservation->fresh()->status);
    }

    public function test_a_cancel_without_loyalty_writes_no_loyalty_row_and_adds_a_pinned_number_of_queries(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $this->guest->id]);
        $before = $this->loyaltyRowCounts();

        $queries = $this->countDomainQueries(fn () => app(CancelReservationAction::class)->handle($reservation));

        $this->assertSame($before, $this->loyaltyRowCounts());
        // Before Phase 10 a cancel ran 3 (status update, one more reservation read made while updating, key-revoke lock). Now: the
        // reservation lock (M-3) + those 3 + folio lock + guest lock + application lock = 7.
        $this->assertSame(7, $queries);
    }

    public function test_a_cancel_for_a_reservation_without_a_guest_loyalty_footprint_is_a_noop(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => null]);
        $before = $this->loyaltyRowCounts();

        $this->assertSame(['data' => null, 'code' => 204], app(CancelReservationAction::class)->handle($reservation));

        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_releasing_expired_holds_touches_no_loyalty_table(): void
    {
        $this->grantPoints($this->guest, 5000);
        $hold = Reservation::factory()->expiredHold()->create(['guest_id' => $this->guest->id]);
        $before = $this->loyaltyRowCounts();
        $batch = LoyaltyEarnBatch::where('guest_id', $this->guest->id)->sole()->only(['points_remaining', 'status']);

        $this->artisan('booking:release-holds')->assertSuccessful();

        $this->assertNotSame(ReservationStatus::PENDING_VERIFICATION, $hold->fresh()->status);
        $this->assertSame($before, $this->loyaltyRowCounts());
        $this->assertSame($batch, LoyaltyEarnBatch::where('guest_id', $this->guest->id)->sole()->only(['points_remaining', 'status']));
    }

    public function test_lock_order_for_a_cancel_is_reservation_folio_guest_batches(): void
    {
        $this->grantPoints($this->guest, 20000);
        $reservation = $this->bookWith(['loyalty_points' => 10000]);

        $locked = $this->lockedSelects(fn () => $this->cancelAsGuest($reservation)->assertStatus(204));

        $this->assertOrder($locked, ['reservations', 'folios', 'guests', 'loyalty_earn_batches']);
    }

    /** @param list<string> $locked */
    private function assertOrder(array $locked, array $tables): void
    {
        $positions = [];
        foreach ($tables as $table) {
            $position = null;
            foreach ($locked as $index => $sql) {
                if (str_contains($sql, 'from "'.$table.'"')) {
                    $position = $index;
                    break;
                }
            }
            $this->assertNotNull($position, "no `for update` select on {$table}; saw:\n".implode("\n", $locked));
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'lock order must be '.implode(' -> ', $tables));
    }
}
