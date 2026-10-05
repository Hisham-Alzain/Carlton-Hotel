<?php

namespace App\Actions\Loyalty;

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
use App\Support\LoyaltyLedger;
use App\Support\LoyaltyMath;
use App\Support\LoyaltyProgram;

/**
 * Read-only. The only discount calculator; PreviewLoyaltyAction and
 * CreateReservationAction both call it (Pitfall 6). Never accepts a discount
 * from the client (M-7): the only inputs are `loyalty_points` and
 * `voucher_code`. Takes no lock; the booking caller already holds the guest lock.
 */
class PriceLoyaltyRedemptionAction
{
    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * Price a prospective booking with points or one voucher (Q5, Q10, Q12).
     *
     * `$quote` is QuoteReservationAction's result: `total_usd` is the post-promo
     * total (the cap base) and `daily_rate_usd` prices a free night.
     *
     * @param  array{total_usd: int|float|string, daily_rate_usd: int|float|string}  $quote
     * @param  array{loyalty_points?: ?int, voucher_code?: ?string}  $input
     * @return array{data: array<string, mixed>, code: int}
     *
     * @throws LoyaltyDiscountConflictException points and a voucher together
     * @throws LoyaltyProgramInactiveException points while paying with points is off
     * @throws LoyaltyBelowMinimumException
     * @throws LoyaltyOverCapException
     * @throws LoyaltyInsufficientPointsException
     * @throws LoyaltyVoucherInvalidException
     */
    public function handle(Guest $guest, array $quote, array $input): array
    {
        $gross = LoyaltyMath::fromQuote($quote['total_usd']);
        $daily = LoyaltyMath::fromQuote($quote['daily_rate_usd']);
        $points = isset($input['loyalty_points']) ? (int) $input['loyalty_points'] : null;
        $code = $this->normaliseCode($input['voucher_code'] ?? null);
        $program = LoyaltyProgram::current();
        $available = $this->ledger->available($guest->id);

        if ($points !== null && $code !== null) {
            throw new LoyaltyDiscountConflictException(__('custom.errors.loyalty_discount_conflict'));
        }

        $pointsDiscount = '0.00';
        if ($points !== null) {
            $pointsDiscount = $this->priceFreeFormPoints($program, $gross, $points, $available);
        }

        $voucher = null;
        $voucherDiscount = '0.00';
        if ($code !== null) {
            $voucher = $this->findUsableVoucher($guest, $code);
            $voucherDiscount = $this->voucherDiscount($voucher, $gross, $daily);
        }

        $net = LoyaltyMath::netTotal($gross, bcadd($pointsDiscount, $voucherDiscount, 2));

        return ['data' => [
            'gross_total_usd' => $gross,
            'points_redeemed' => $points ?? 0,
            'points_discount_usd' => $pointsDiscount,
            'voucher' => $voucher,
            'voucher_discount_usd' => $voucherDiscount,
            'upgrade_requested' => $voucher?->type === LoyaltyRewardType::ROOM_UPGRADE,
            'net_total_usd' => $net,
            'available_points' => $available,
            'max_points' => $program->pointsDiscountEnabled() ? $this->maxPoints($program, $gross, $available) : null,
            'points_earnable_estimate' => $program->earningEnabled()
                ? LoyaltyMath::pointsForSpend($net, $program->earnRate())
                : null,
            'program' => $program->capabilities(),
        ], 'code' => 200];
    }

    /**
     * Free-form points: switched on, at least the minimum, within the cap and
     * within the balance, in that order (Q4, Q5). Returns the discount.
     */
    private function priceFreeFormPoints(LoyaltyProgram $program, string $gross, int $points, int $available): string
    {
        if (! $program->pointsDiscountEnabled()) {
            throw new LoyaltyProgramInactiveException(
                __('custom.errors.loyalty_program_inactive'),
                ['capability' => 'points_discount'],
            );
        }

        if ($points < $program->minRedeemPoints()) {
            throw new LoyaltyBelowMinimumException(
                __('custom.errors.loyalty_below_minimum'),
                ['min_redeem_points' => $program->minRedeemPoints()],
            );
        }

        $discount = LoyaltyMath::discountForPoints($points, $program->redeemValueUsd());
        $cap = LoyaltyMath::maxDiscount($gross, $program->maxDiscountPercent());
        if (bccomp($discount, $cap, 2) > 0) {
            throw new LoyaltyOverCapException(
                __('custom.errors.loyalty_over_cap'),
                ['max_points' => LoyaltyMath::maxPointsForCap($cap, $program->redeemValueUsd())],
            );
        }

        if ($points > $available) {
            throw new LoyaltyInsufficientPointsException(
                __('custom.errors.loyalty_insufficient_points'),
                ['available_points' => $available, 'requested_points' => $points],
            );
        }

        return $discount;
    }

    /** The most points usable on this booking: the cap's limit or the balance, whichever is lower. */
    private function maxPoints(LoyaltyProgram $program, string $gross, int $available): int
    {
        $cap = LoyaltyMath::maxDiscount($gross, $program->maxDiscountPercent());

        return min($available, LoyaltyMath::maxPointsForCap($cap, $program->redeemValueUsd()));
    }

    /** Upper-case, whitespace-free code, or null when none was sent. */
    private function normaliseCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalised = strtoupper((string) preg_replace('/\s+/', '', $code));

        return $normalised === '' ? null : $normalised;
    }

    /**
     * The caller's own active, unexpired voucher. Unknown, foreign, used, void
     * and expired all answer the same error with no context (T-10-09, T-10-38).
     */
    private function findUsableVoucher(Guest $guest, string $code): LoyaltyVoucher
    {
        $voucher = LoyaltyVoucher::query()
            ->where('guest_id', $guest->id)
            ->where('code', $code)
            ->first();

        if (! $voucher instanceof LoyaltyVoucher
            || $voucher->status !== LoyaltyVoucherStatus::ACTIVE
            || ! $voucher->expires_at->gt(now())) {
            throw new LoyaltyVoucherInvalidException(__('custom.errors.loyalty_voucher_invalid'));
        }

        return $voucher;
    }

    /** What a voucher takes off the gross (Q12): never more than the gross. */
    private function voucherDiscount(LoyaltyVoucher $voucher, string $gross, string $daily): string
    {
        return match ($voucher->type) {
            LoyaltyRewardType::DISCOUNT_VOUCHER => LoyaltyMath::minUsd((string) $voucher->value_usd, $gross),
            LoyaltyRewardType::FREE_NIGHT => LoyaltyMath::minUsd($daily, $gross),
            LoyaltyRewardType::ROOM_UPGRADE => '0.00',
        };
    }
}
