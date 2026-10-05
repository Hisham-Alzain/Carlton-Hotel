<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Loyalty\ApplyLoyaltyToReservationAction;
use App\Actions\Loyalty\PriceLoyaltyRedemptionAction;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyVoucherStatus;
use App\Exceptions\LoyaltyVoucherInvalidException;
use App\Exceptions\ReservationStateException;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReservationApplication;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Support\LoyaltyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 LOY-16 (M-5, Q6): the persisting half of booking-time loyalty. It
 * refuses an unverified OTP hold, writes the points/voucher effects and the
 * application row, and re-checks a voucher under lock.
 */
class ApplyLoyaltyToReservationActionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLoyalty([
            'redeem_value_usd' => '0.0100',
            'max_redeem_percent' => '50.00',
            'min_redeem_points' => 100,
        ]);
        $this->guest = Guest::factory()->create();
    }

    /** @return array<string, mixed> */
    private function priced(array $input): array
    {
        return app(PriceLoyaltyRedemptionAction::class)
            ->handle($this->guest, ['total_usd' => 300.0, 'daily_rate_usd' => 150.0], $input)['data'];
    }

    /** Runs the action the way booking does: inside a transaction with the guest row locked. */
    private function apply(Reservation $reservation, array $redemption, string $key = 'K-1'): array
    {
        return DB::transaction(function () use ($reservation, $redemption, $key) {
            $locked = Guest::whereKey($this->guest->id)->lockForUpdate()->firstOrFail();

            return app(ApplyLoyaltyToReservationAction::class)->handle($reservation, $locked, $redemption, $key);
        });
    }

    public function test_it_consumes_points_records_the_redeem_and_returns_the_application(): void
    {
        $this->grantPoints($this->guest, 20000);
        $reservation = Reservation::factory()->create(['guest_id' => $this->guest->id, 'total_usd' => '200.00']);

        $result = $this->apply($reservation, $this->priced(['loyalty_points' => 10000]));

        $this->assertSame(201, $result['code']);
        $this->assertInstanceOf(LoyaltyReservationApplication::class, $result['data']);
        $this->assertSame(10000, $result['data']->points_redeemed);
        $this->assertSame('100.00', $result['data']->points_discount_usd);
        $this->assertSame('K-1', $result['data']->idempotency_key);

        $entry = LoyaltyLedgerEntry::where('type', LoyaltyEntryType::REDEEM->value)->sole();
        $this->assertSame(-10000, $entry->points);
        $this->assertSame('redeem:reservation:'.$reservation->id, $entry->idempotency_key);
        $this->assertSame($entry->id, (int) $result['data']->redeem_entry_id);
        $this->assertSame(10000, app(LoyaltyLedger::class)->available($this->guest->id));
    }

    public function test_it_marks_a_voucher_used_for_this_reservation(): void
    {
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $this->guest->id, 'value_usd' => '25.00']);
        $reservation = Reservation::factory()->create(['guest_id' => $this->guest->id]);

        $result = $this->apply($reservation, $this->priced(['voucher_code' => $voucher->code]));

        $this->assertSame($voucher->id, (int) $result['data']->voucher_id);
        $this->assertSame('25.00', $result['data']->voucher_discount_usd);
        $this->assertNull($result['data']->redeem_entry_id);

        $fresh = $voucher->fresh();
        $this->assertSame(LoyaltyVoucherStatus::USED, $fresh->status);
        $this->assertSame($reservation->id, (int) $fresh->reservation_id);
        $this->assertNotNull($fresh->used_at);
    }

    public function test_a_pending_verification_reservation_is_refused_and_nothing_is_written(): void
    {
        $this->grantPoints($this->guest, 20000);
        $reservation = Reservation::factory()->pendingVerification()->create(['guest_id' => $this->guest->id]);
        $redemption = $this->priced(['loyalty_points' => 10000]);
        $counts = $this->loyaltyRowCounts();

        $this->expectException(ReservationStateException::class);

        try {
            $this->apply($reservation, $redemption);
        } finally {
            $this->assertSame($counts, $this->loyaltyRowCounts());
            $this->assertSame(20000, app(LoyaltyLedger::class)->available($this->guest->id));
        }
    }

    public function test_a_reservation_with_a_hold_expiry_is_refused_even_when_not_pending_verification(): void
    {
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $this->guest->id]);
        $reservation = Reservation::factory()->create([
            'guest_id' => $this->guest->id,
            'hold_expires_at' => now()->addMinutes(5),
        ]);
        $redemption = $this->priced(['voucher_code' => $voucher->code]);

        try {
            $this->apply($reservation, $redemption);
            $this->fail('A reservation still on a hold must be refused.');
        } catch (ReservationStateException) {
            $this->assertNull($reservation->fresh()->loyaltyApplication);
            $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $voucher->fresh()->status);
        }
    }

    public function test_a_voucher_used_between_pricing_and_apply_is_refused(): void
    {
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $this->guest->id]);
        $reservation = Reservation::factory()->create(['guest_id' => $this->guest->id]);
        $redemption = $this->priced(['voucher_code' => $voucher->code]);
        $counts = $this->loyaltyRowCounts();

        $voucher->update(['status' => LoyaltyVoucherStatus::USED, 'used_at' => now()]);

        try {
            $this->apply($reservation, $redemption);
            $this->fail('A voucher that is no longer active must be refused.');
        } catch (LoyaltyVoucherInvalidException) {
            $this->assertSame($counts, $this->loyaltyRowCounts());
            $this->assertNull($voucher->fresh()->reservation_id);
        }
    }

    public function test_a_voucher_that_expired_between_pricing_and_apply_is_refused(): void
    {
        $voucher = LoyaltyVoucher::factory()->create(['guest_id' => $this->guest->id]);
        $reservation = Reservation::factory()->create(['guest_id' => $this->guest->id]);
        $redemption = $this->priced(['voucher_code' => $voucher->code]);

        $voucher->update(['expires_at' => now()->subSecond()]);

        $this->expectException(LoyaltyVoucherInvalidException::class);
        $this->apply($reservation, $redemption);
    }
}
