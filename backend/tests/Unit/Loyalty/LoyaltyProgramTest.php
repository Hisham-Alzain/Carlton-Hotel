<?php

namespace Tests\Unit\Loyalty;

use App\Exceptions\LoyaltyProgramInactiveException;
use App\Support\LoyaltyProgram;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 (Q4, M-1, M-8): the settings reader. A null rate or cap only ever
 * means "that capability is off" - never a default of 100% or a rate of one.
 */
class LoyaltyProgramTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    public function test_with_no_row_the_column_defaults_apply(): void
    {
        $program = LoyaltyProgram::current();

        $this->assertSame(
            ['earning' => false, 'points_discount' => false, 'rewards' => true],
            $program->capabilities(),
        );
        $this->assertSame(24, $program->expiryMonths());
        $this->assertSame(30, $program->expiryWarningDays());
        $this->assertSame(1, $program->minRedeemPoints());
        $this->assertFalse($program->earningEnabled());
        $this->assertFalse($program->pointsDiscountEnabled());
        $this->assertTrue($program->rewardsEnabled());
    }

    public function test_each_getter_throws_when_its_capability_is_off(): void
    {
        $program = LoyaltyProgram::current();

        foreach ([
            'earning' => fn () => $program->earnRate(),
            'points_discount' => fn () => $program->redeemValueUsd(),
        ] as $capability => $call) {
            try {
                $call();
                $this->fail("Expected an inactive exception for {$capability}");
            } catch (LoyaltyProgramInactiveException $e) {
                $this->assertSame('loyalty_program_inactive', $e->errorCode());
                $this->assertSame(422, $e->statusCode());
                $this->assertSame(['capability' => $capability], $e->context());
            }
        }

        try {
            $program->maxDiscountPercent();
            $this->fail('Expected an inactive exception for the cap');
        } catch (LoyaltyProgramInactiveException $e) {
            $this->assertSame('loyalty_program_inactive', $e->errorCode());
            $this->assertSame(['capability' => 'points_discount'], $e->context());
        }
    }

    public function test_a_zero_earn_rate_switches_earning_off(): void
    {
        $this->configureLoyalty(['earn_rate' => '0.0000']);

        $program = LoyaltyProgram::current();

        $this->assertFalse($program->earningEnabled());
        $this->assertFalse($program->capabilities()['earning']);
        $this->expectException(LoyaltyProgramInactiveException::class);
        $program->earnRate();
    }

    public function test_a_redeem_value_without_a_cap_is_not_a_discount_capability(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => '0.0100', 'max_redeem_percent' => null]);

        $program = LoyaltyProgram::current();

        $this->assertFalse($program->pointsDiscountEnabled());
        $this->assertFalse($program->capabilities()['points_discount']);
        $this->expectException(LoyaltyProgramInactiveException::class);
        $program->maxDiscountPercent();
    }

    public function test_a_cap_without_a_redeem_value_is_not_a_discount_capability(): void
    {
        $this->configureLoyalty(['redeem_value_usd' => null, 'max_redeem_percent' => '50.00']);

        $program = LoyaltyProgram::current();

        $this->assertFalse($program->pointsDiscountEnabled());
        $this->expectException(LoyaltyProgramInactiveException::class);
        $program->redeemValueUsd();
    }

    public function test_a_zero_cap_is_not_a_discount_capability(): void
    {
        $this->configureLoyalty(['max_redeem_percent' => '0.00']);

        $this->assertFalse(LoyaltyProgram::current()->pointsDiscountEnabled());
    }

    public function test_a_fully_configured_program_exposes_the_stored_strings(): void
    {
        $this->configureLoyalty([
            'earn_rate' => '1.2500',
            'redeem_value_usd' => '0.0150',
            'max_redeem_percent' => '33.33',
            'min_redeem_points' => 250,
            'expiry_months' => 12,
            'expiry_warning_days' => 14,
        ]);

        $program = LoyaltyProgram::current();

        $this->assertSame(
            ['earning' => true, 'points_discount' => true, 'rewards' => true],
            $program->capabilities(),
        );
        $this->assertSame('1.2500', $program->earnRate());
        $this->assertSame('0.0150', $program->redeemValueUsd());
        $this->assertSame('33.33', $program->maxDiscountPercent());
        $this->assertSame(250, $program->minRedeemPoints());
        $this->assertSame(12, $program->expiryMonths());
        $this->assertSame(14, $program->expiryWarningDays());
    }

    public function test_a_null_minimum_means_one_point(): void
    {
        $this->configureLoyalty(['min_redeem_points' => null]);

        $this->assertSame(1, LoyaltyProgram::current()->minRedeemPoints());
    }

    public function test_current_is_read_fresh_on_every_call(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        $this->assertSame('1.0000', LoyaltyProgram::current()->earnRate());

        DB::table('loyalty_settings')->where('singleton', 1)->update(['earn_rate' => '2.0000']);

        $this->assertSame('2.0000', LoyaltyProgram::current()->earnRate());
    }

    public function test_expiry_clamps_a_month_end_and_returns_utc(): void
    {
        config(['hotel.timezone' => 'Europe/London']);
        $this->configureLoyalty(['expiry_months' => 1]);

        $expires = LoyaltyProgram::current()->expiresAtFrom(CarbonImmutable::parse('2027-01-31 10:00:00', 'UTC'));

        $this->assertSame('UTC', $expires->getTimezone()->getName());
        $this->assertSame('2027-02-28 23:59:59', $expires->setTimezone('Europe/London')->format('Y-m-d H:i:s'));
        $this->assertSame('2027-02-28 23:59:59', $expires->format('Y-m-d H:i:s'));
    }

    public function test_expiry_is_correct_across_a_dst_change(): void
    {
        config(['hotel.timezone' => 'Europe/London']);
        $this->configureLoyalty(['expiry_months' => 24]);

        // 2027-03-28 00:30 UTC is the morning London clocks go forward.
        $expires = LoyaltyProgram::current()->expiresAtFrom(CarbonImmutable::parse('2027-03-28 00:30:00', 'UTC'));

        $this->assertSame('2029-03-28 23:59:59', $expires->setTimezone('Europe/London')->format('Y-m-d H:i:s'));
        // BST (+01:00) is in force on 2029-03-28, so end of the local day is 22:59:59 UTC.
        $this->assertSame('2029-03-28 22:59:59', $expires->format('Y-m-d H:i:s'));
    }

    public function test_expiry_uses_the_hotel_local_date_in_a_positive_offset_zone(): void
    {
        config(['hotel.timezone' => 'Asia/Dubai']);
        $this->configureLoyalty(['expiry_months' => 1]);

        // 21:00 UTC on 10 May is already 01:00 on 11 May in Dubai.
        $expires = LoyaltyProgram::current()->expiresAtFrom(CarbonImmutable::parse('2027-05-10 21:00:00', 'UTC'));

        $this->assertSame('2027-06-11 23:59:59', $expires->setTimezone('Asia/Dubai')->format('Y-m-d H:i:s'));
        $this->assertSame('2027-06-11 19:59:59', $expires->format('Y-m-d H:i:s'));
    }
}
