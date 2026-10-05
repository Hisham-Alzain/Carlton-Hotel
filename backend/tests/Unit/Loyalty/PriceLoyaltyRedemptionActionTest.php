<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Loyalty\PriceLoyaltyRedemptionAction;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyVoucherStatus;
use App\Exceptions\LoyaltyBelowMinimumException;
use App\Exceptions\LoyaltyDiscountConflictException;
use App\Exceptions\LoyaltyInsufficientPointsException;
use App\Exceptions\LoyaltyOverCapException;
use App\Exceptions\LoyaltyProgramInactiveException;
use App\Exceptions\LoyaltyVoucherInvalidException;
use App\Models\Guest;
use App\Models\LoyaltyVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-15 / LOY-16 / LOY-22 (Q4, Q5, Q10, Q12, Pitfall 6): the one
 * read-only discount calculator that preview and booking share.
 */
class PriceLoyaltyRedemptionActionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLoyalty([
            'redeem_value_usd' => '0.0100',
            'max_redeem_percent' => '50.00',
            'min_redeem_points' => 100,
            'earn_rate' => '1.0000',
        ]);
    }

    private function action(): PriceLoyaltyRedemptionAction
    {
        return app(PriceLoyaltyRedemptionAction::class);
    }

    /** A quote shaped like QuoteReservationAction's result (floats). */
    private function quote(float $total, float $daily = 100.0): array
    {
        return [
            'nights' => 3,
            'daily_rate_usd' => $daily,
            'subtotal_usd' => $total,
            'discount_usd' => 0.0,
            'total_usd' => $total,
            'promo_code_id' => null,
            'rules_applied' => 0,
        ];
    }

    private function price(Guest $guest, array $quote, array $input = []): array
    {
        return $this->action()->handle($guest, $quote, $input)['data'];
    }

    private function voucher(Guest $guest, array $overrides = []): LoyaltyVoucher
    {
        return LoyaltyVoucher::factory()->create(array_merge(['guest_id' => $guest->id], $overrides));
    }

    public function test_points_happy_path_prices_the_discount_and_the_estimate(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 20000);

        $data = $this->price($guest, $this->quote(300.00), ['loyalty_points' => 10000]);

        $this->assertSame(10000, $data['points_redeemed']);
        $this->assertSame('100.00', $data['points_discount_usd']);
        $this->assertSame('200.00', $data['net_total_usd']);
        $this->assertSame('0.00', $data['voucher_discount_usd']);
        $this->assertNull($data['voucher']);
        $this->assertFalse($data['upgrade_requested']);
        $this->assertSame(200, $data['points_earnable_estimate']);
        $this->assertSame(20000, $data['available_points']);
    }

    public function test_the_envelope_is_data_and_200(): void
    {
        $guest = Guest::factory()->create();

        $result = $this->action()->handle($guest, $this->quote(300.00), []);

        $this->assertSame(200, $result['code']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_the_cap_edge_is_exact_to_the_point(): void
    {
        $this->configureLoyalty(['max_redeem_percent' => '33.33']);
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 20000);

        $ok = $this->price($guest, $this->quote(333.33), ['loyalty_points' => 11109]);
        $this->assertSame('111.09', $ok['points_discount_usd']);
        $this->assertSame('222.24', $ok['net_total_usd']);

        try {
            $this->price($guest, $this->quote(333.33), ['loyalty_points' => 11110]);
            $this->fail('11110 points is one point over the cap.');
        } catch (LoyaltyOverCapException $e) {
            $this->assertSame(['max_points' => 11109], $e->context());
        }
    }

    public function test_points_below_the_minimum_are_refused_and_a_null_minimum_means_one(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 5000);

        try {
            $this->price($guest, $this->quote(300.00), ['loyalty_points' => 99]);
            $this->fail('99 is below the minimum of 100.');
        } catch (LoyaltyBelowMinimumException $e) {
            $this->assertSame(['min_redeem_points' => 100], $e->context());
        }

        $this->configureLoyalty(['min_redeem_points' => null]);
        $data = $this->price($guest, $this->quote(300.00), ['loyalty_points' => 1]);
        $this->assertSame(1, $data['points_redeemed']);
        $this->assertSame('0.01', $data['points_discount_usd']);
    }

    public function test_points_above_the_balance_are_refused_with_both_figures(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 500);

        try {
            $this->price($guest, $this->quote(300.00), ['loyalty_points' => 600]);
            $this->fail('600 points exceed the balance of 500.');
        } catch (LoyaltyInsufficientPointsException $e) {
            $this->assertSame(['available_points' => 500, 'requested_points' => 600], $e->context());
        }
    }

    public function test_points_while_the_points_discount_is_off_are_inactive_and_none_means_a_null_max(): void
    {
        $this->configureLoyalty(['max_redeem_percent' => null]);
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 5000);

        try {
            $this->price($guest, $this->quote(300.00), ['loyalty_points' => 500]);
            $this->fail('The points discount is switched off.');
        } catch (LoyaltyProgramInactiveException $e) {
            $this->assertSame(['capability' => 'points_discount'], $e->context());
        }

        $data = $this->price($guest, $this->quote(300.00));
        $this->assertNull($data['max_points']);
        $this->assertSame(0, $data['points_redeemed']);
        $this->assertSame('300.00', $data['net_total_usd']);
    }

    public function test_points_and_a_voucher_together_conflict_before_any_other_check(): void
    {
        // No balance, no voucher, below the minimum: the conflict still wins.
        $guest = Guest::factory()->create();

        $this->expectException(LoyaltyDiscountConflictException::class);

        $this->price($guest, $this->quote(300.00), ['loyalty_points' => 1, 'voucher_code' => 'LOY-NOPE']);
    }

    public function test_a_discount_voucher_takes_its_value_off_the_gross(): void
    {
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['value_usd' => '25.00']);

        $data = $this->price($guest, $this->quote(300.00), ['voucher_code' => $voucher->code]);

        $this->assertSame('25.00', $data['voucher_discount_usd']);
        $this->assertSame('275.00', $data['net_total_usd']);
        $this->assertSame('0.00', $data['points_discount_usd']);
        $this->assertSame(0, $data['points_redeemed']);
        $this->assertTrue($data['voucher']->is($voucher));
        $this->assertFalse($data['upgrade_requested']);
    }

    public function test_a_discount_voucher_larger_than_the_total_stops_at_the_total(): void
    {
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['value_usd' => '500.00']);

        $data = $this->price($guest, $this->quote(300.00), ['voucher_code' => $voucher->code]);

        $this->assertSame('300.00', $data['voucher_discount_usd']);
        $this->assertSame('0.00', $data['net_total_usd']);
        $this->assertSame(0, $data['points_earnable_estimate']);
    }

    public function test_a_free_night_voucher_is_worth_one_night_capped_at_the_total(): void
    {
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['type' => LoyaltyRewardType::FREE_NIGHT, 'value_usd' => null]);

        $data = $this->price($guest, $this->quote(300.00, 120.00), ['voucher_code' => $voucher->code]);
        $this->assertSame('120.00', $data['voucher_discount_usd']);
        $this->assertSame('180.00', $data['net_total_usd']);

        $capped = $this->price($guest, $this->quote(80.00, 120.00), ['voucher_code' => $voucher->code]);
        $this->assertSame('80.00', $capped['voucher_discount_usd']);
        $this->assertSame('0.00', $capped['net_total_usd']);
    }

    public function test_a_room_upgrade_voucher_discounts_nothing_and_asks_for_the_upgrade(): void
    {
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['type' => LoyaltyRewardType::ROOM_UPGRADE, 'value_usd' => null]);

        $data = $this->price($guest, $this->quote(300.00), ['voucher_code' => $voucher->code]);

        $this->assertSame('0.00', $data['voucher_discount_usd']);
        $this->assertSame('300.00', $data['net_total_usd']);
        $this->assertTrue($data['upgrade_requested']);
        $this->assertTrue($data['voucher']->is($voucher));
    }

    public function test_the_minimum_and_the_cap_do_not_apply_to_vouchers(): void
    {
        // 90% of the total is far beyond the 50% points cap, and the program has
        // a minimum of 100 points: neither concerns a voucher.
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['value_usd' => '270.00']);

        $data = $this->price($guest, $this->quote(300.00), ['voucher_code' => $voucher->code]);

        $this->assertSame('270.00', $data['voucher_discount_usd']);
        $this->assertSame('30.00', $data['net_total_usd']);
    }

    public function test_a_voucher_works_while_the_points_discount_is_off(): void
    {
        $this->configureLoyalty(['max_redeem_percent' => null]);
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest);

        $data = $this->price($guest, $this->quote(300.00), ['voucher_code' => $voucher->code]);

        $this->assertSame('25.00', $data['voucher_discount_usd']);
        $this->assertNull($data['max_points']);
    }

    public function test_an_unknown_voucher_code_is_invalid_without_context(): void
    {
        $this->assertVoucherInvalid(Guest::factory()->create(), 'LOY-DOESNOTEXIST');
    }

    public function test_another_guests_voucher_is_invalid_without_context(): void
    {
        $owner = Guest::factory()->create();
        $voucher = $this->voucher($owner);

        $this->assertVoucherInvalid(Guest::factory()->create(), $voucher->code);
    }

    public function test_a_used_voucher_is_invalid_without_context(): void
    {
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['status' => LoyaltyVoucherStatus::USED, 'used_at' => now()]);

        $this->assertVoucherInvalid($guest, $voucher->code);
    }

    public function test_a_void_voucher_is_invalid_without_context(): void
    {
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['status' => LoyaltyVoucherStatus::VOID]);

        $this->assertVoucherInvalid($guest, $voucher->code);
    }

    public function test_a_voucher_expiring_exactly_now_is_invalid_without_context(): void
    {
        $guest = Guest::factory()->create();
        $this->travelTo(now()->startOfSecond());
        $voucher = $this->voucher($guest, ['expires_at' => now()]);

        $this->assertVoucherInvalid($guest, $voucher->code);
    }

    public function test_a_voucher_one_second_from_expiry_is_still_valid(): void
    {
        $guest = Guest::factory()->create();
        $this->travelTo(now()->startOfSecond());
        $voucher = $this->voucher($guest, ['expires_at' => now()->addSecond()]);

        $data = $this->price($guest, $this->quote(300.00), ['voucher_code' => $voucher->code]);

        $this->assertSame('25.00', $data['voucher_discount_usd']);
    }

    public function test_a_voucher_code_is_normalised_before_the_lookup(): void
    {
        $guest = Guest::factory()->create();
        $voucher = $this->voucher($guest, ['code' => 'LOY-ABCD2345']);

        $data = $this->price($guest, $this->quote(300.00), ['voucher_code' => '  loy-abcd2345 ']);

        $this->assertTrue($data['voucher']->is($voucher));
    }

    public function test_the_earn_estimate_is_null_when_earning_is_off(): void
    {
        $this->configureLoyalty(['earn_rate' => null]);
        $guest = Guest::factory()->create();

        $data = $this->price($guest, $this->quote(300.00));

        $this->assertNull($data['points_earnable_estimate']);
    }

    public function test_the_usable_points_are_the_smaller_of_the_balance_and_the_cap(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 4000);

        // 50% of 300.00 = 150.00 = 15000 points; the balance of 4000 is lower.
        $this->assertSame(4000, $this->price($guest, $this->quote(300.00))['max_points']);

        $this->grantPoints($guest, 20000);
        $this->assertSame(15000, $this->price($guest, $this->quote(300.00))['max_points']);
    }

    public function test_the_program_capabilities_are_reported(): void
    {
        $guest = Guest::factory()->create();

        $data = $this->price($guest, $this->quote(300.00));

        $this->assertSame(['earning' => true, 'points_discount' => true, 'rewards' => true], $data['program']);
    }

    public function test_pricing_writes_nothing_and_locks_nothing(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 20000);
        $voucher = $this->voucher($guest);
        $before = $this->loyaltyRowCounts();

        $locked = $this->lockedSelects(function () use ($guest, $voucher): void {
            $this->price($guest, $this->quote(300.00), ['loyalty_points' => 1000]);
            $this->price($guest, $this->quote(300.00), ['voucher_code' => $voucher->code]);
            $this->price($guest, $this->quote(300.00));
        });

        $this->assertSame([], $locked);
        $this->assertSame($before, $this->loyaltyRowCounts());
        $this->assertSame(LoyaltyVoucherStatus::ACTIVE, $voucher->fresh()->status);
    }

    private function assertVoucherInvalid(Guest $guest, string $code): void
    {
        try {
            $this->price($guest, $this->quote(300.00), ['voucher_code' => $code]);
            $this->fail('The voucher should have been refused as loyalty_voucher_invalid.');
        } catch (LoyaltyVoucherInvalidException $e) {
            $this->assertSame('loyalty_voucher_invalid', $e->errorCode());
            $this->assertSame([], $e->context());
        }
    }
}
