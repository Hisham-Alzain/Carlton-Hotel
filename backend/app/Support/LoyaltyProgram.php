<?php

namespace App\Support;

use App\Exceptions\LoyaltyProgramInactiveException;
use App\Models\LoyaltySetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The loyalty program settings reader (Phase 10, Q4).
 *
 * Q4/M-8: read fresh per use; settings live only in loyalty_settings. A null
 * rate or cap never defaults to anything - it means that capability is off,
 * and the matching getter throws (M-1, LOY-22). Earning, paying with points and
 * rewards are independent capabilities.
 */
final class LoyaltyProgram
{
    private function __construct(private readonly LoyaltySetting $setting) {}

    /** The singleton row, or the unsaved column defaults when staff have not configured anything. */
    public static function current(): self
    {
        $row = LoyaltySetting::query()->where('singleton', 1)->first();

        return new self($row instanceof LoyaltySetting ? $row : new LoyaltySetting);
    }

    public function settings(): LoyaltySetting
    {
        return $this->setting;
    }

    public function earningEnabled(): bool
    {
        return $this->setting->earn_rate !== null
            && bccomp($this->setting->earn_rate, '0', 4) === 1;
    }

    public function pointsDiscountEnabled(): bool
    {
        return $this->setting->redeem_value_usd !== null
            && bccomp($this->setting->redeem_value_usd, '0', 4) === 1
            && $this->setting->max_redeem_percent !== null
            && bccomp($this->setting->max_redeem_percent, '0', 2) === 1;
    }

    public function rewardsEnabled(): bool
    {
        return true;
    }

    /** @return array{earning: bool, points_discount: bool, rewards: bool} */
    public function capabilities(): array
    {
        return [
            'earning' => $this->earningEnabled(),
            'points_discount' => $this->pointsDiscountEnabled(),
            'rewards' => $this->rewardsEnabled(),
        ];
    }

    /** @throws LoyaltyProgramInactiveException when earning is off */
    public function earnRate(): string
    {
        $this->requireCapability($this->earningEnabled(), 'earning');

        return $this->setting->earn_rate;
    }

    /** @throws LoyaltyProgramInactiveException when paying with points is off */
    public function redeemValueUsd(): string
    {
        $this->requireCapability($this->pointsDiscountEnabled(), 'points_discount');

        return $this->setting->redeem_value_usd;
    }

    /** @throws LoyaltyProgramInactiveException when paying with points is off */
    public function maxDiscountPercent(): string
    {
        $this->requireCapability($this->pointsDiscountEnabled(), 'points_discount');

        return $this->setting->max_redeem_percent;
    }

    /** Null means "no minimum", which is one point (Q4): the only permitted null fallback. */
    public function minRedeemPoints(): int
    {
        return $this->setting->min_redeem_points ?? 1;
    }

    public function expiryMonths(): int
    {
        return $this->setting->expiry_months;
    }

    public function expiryWarningDays(): int
    {
        return $this->setting->expiry_warning_days;
    }

    /**
     * When points earned at `$instant` expire: the hotel-local date of the
     * instant plus the expiry months (no month overflow), at the end of that
     * local day, returned in UTC.
     */
    public function expiresAtFrom(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)
            ->setTimezone(HotelClock::timezone())
            ->addMonthsNoOverflow($this->expiryMonths())
            ->endOfDay()
            ->utc();
    }

    private function requireCapability(bool $enabled, string $capability): void
    {
        if (! $enabled) {
            throw new LoyaltyProgramInactiveException(
                __('custom.errors.loyalty_program_inactive'),
                ['capability' => $capability],
            );
        }
    }
}
