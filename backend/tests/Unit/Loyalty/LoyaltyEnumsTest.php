<?php

namespace Tests\Unit\Loyalty;

use App\Enums\LoyaltyApplicationStatus;
use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyVoucherStatus;
use PHPUnit\Framework\TestCase;

/**
 * Phase 10 (Q16): the loyalty API contract strings are encoded in the enums.
 * Values and their order are a contract with the Flutter and React clients.
 */
class LoyaltyEnumsTest extends TestCase
{
    public function test_entry_type_values(): void
    {
        $this->assertSame(
            ['earn', 'redeem', 'expire', 'adjust', 'clawback', 'refund'],
            LoyaltyEntryType::values(),
        );
    }

    public function test_batch_source_values(): void
    {
        $this->assertSame(['stay', 'service', 'manual', 'refund'], LoyaltyBatchSource::values());
    }

    public function test_batch_status_values(): void
    {
        $this->assertSame(['active', 'depleted', 'expired', 'reversed'], LoyaltyBatchStatus::values());
    }

    public function test_reward_type_values(): void
    {
        $this->assertSame(['discount_voucher', 'free_night', 'room_upgrade'], LoyaltyRewardType::values());
    }

    public function test_voucher_status_values(): void
    {
        $this->assertSame(['active', 'used', 'expired', 'void'], LoyaltyVoucherStatus::values());
    }

    public function test_application_status_values(): void
    {
        $this->assertSame(['applied', 'reversed'], LoyaltyApplicationStatus::values());
    }

    public function test_case_names_are_upper_snake_of_the_value(): void
    {
        $this->assertSame(LoyaltyEntryType::CLAWBACK, LoyaltyEntryType::from('clawback'));
        $this->assertSame(LoyaltyRewardType::FREE_NIGHT, LoyaltyRewardType::from('free_night'));
        $this->assertSame(LoyaltyVoucherStatus::VOID, LoyaltyVoucherStatus::from('void'));
        $this->assertSame(LoyaltyBatchStatus::DEPLETED, LoyaltyBatchStatus::from('depleted'));
        $this->assertSame(LoyaltyBatchSource::MANUAL, LoyaltyBatchSource::from('manual'));
        $this->assertSame(LoyaltyApplicationStatus::REVERSED, LoyaltyApplicationStatus::from('reversed'));
    }
}
