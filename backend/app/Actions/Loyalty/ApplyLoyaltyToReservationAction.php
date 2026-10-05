<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyApplicationStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyVoucherStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\LoyaltyVoucherInvalidException;
use App\Exceptions\ReservationStateException;
use App\Models\Guest;
use App\Models\LoyaltyReservationApplication;
use App\Models\LoyaltyVoucher;
use App\Models\Reservation;
use App\Support\LoyaltyLedger;

/**
 * The persisting half of booking-time loyalty (Phase 10, LOY-16, Q6, M-5, M-6).
 * Pricing lives in PriceLoyaltyRedemptionAction; this class only writes what it
 * decided: spend the points or use the voucher, then record the application.
 *
 * M-5: a reservation still waiting on OTP verification or holding a hold expiry
 * never carries a discount - an unverified holder must not spend a guest's
 * points. CALLER CONTRACT: call inside the booking transaction with the guest
 * row already locked (`$lockedGuest`), after the room_type lock. Lock order is
 * room_type -> guest -> batches (points) or voucher (M-6).
 */
class ApplyLoyaltyToReservationAction
{
    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * @param  array<string, mixed>  $redemption  PriceLoyaltyRedemptionAction's `data`
     * @return array{data: LoyaltyReservationApplication, code: int}
     *
     * @throws ReservationStateException the reservation is pending verification or on a hold
     * @throws LoyaltyVoucherInvalidException the voucher stopped being usable since it was priced
     */
    public function handle(Reservation $reservation, Guest $lockedGuest, array $redemption, string $idempotencyKey): array
    {
        if ($reservation->status === ReservationStatus::PENDING_VERIFICATION || $reservation->hold_expires_at !== null) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        $redeemEntryId = $this->spendPoints($reservation, $lockedGuest, $redemption);
        $voucher = $redemption['voucher'] ?? null;

        if ($voucher instanceof LoyaltyVoucher) {
            $this->useVoucher($voucher, $lockedGuest, $reservation);
        }

        $application = LoyaltyReservationApplication::query()->create([
            'reservation_id' => $reservation->id,
            'guest_id' => $lockedGuest->id,
            'idempotency_key' => $idempotencyKey,
            'redeem_entry_id' => $redeemEntryId,
            'points_redeemed' => (int) $redemption['points_redeemed'],
            'points_discount_usd' => $redemption['points_discount_usd'],
            'voucher_id' => $voucher?->id,
            'voucher_discount_usd' => $redemption['voucher_discount_usd'],
            'status' => LoyaltyApplicationStatus::APPLIED,
        ]);

        return ['data' => $application, 'code' => 201];
    }

    /** FIFO consume plus one redeem entry; null when no points are being spent. */
    private function spendPoints(Reservation $reservation, Guest $lockedGuest, array $redemption): ?int
    {
        $points = (int) $redemption['points_redeemed'];

        if ($points <= 0) {
            return null;
        }

        $allocations = $this->ledger->consume($lockedGuest->id, $points);

        return $this->ledger->record([
            'guest_id' => $lockedGuest->id,
            'type' => LoyaltyEntryType::REDEEM,
            'points' => -$points,
            'reservation_id' => $reservation->id,
            'discount_usd' => $redemption['points_discount_usd'],
            'idempotency_key' => 'redeem:reservation:'.$reservation->id,
        ], $allocations)->id;
    }

    /**
     * Re-lock and re-check the voucher: it was priced without a lock, so it may
     * have been used, voided or expired since.
     */
    private function useVoucher(LoyaltyVoucher $priced, Guest $lockedGuest, Reservation $reservation): void
    {
        $voucher = LoyaltyVoucher::query()->whereKey($priced->id)->lockForUpdate()->first();

        if ($voucher === null
            || $voucher->status !== LoyaltyVoucherStatus::ACTIVE
            || ! $voucher->expires_at->gt(now())
            || (int) $voucher->guest_id !== (int) $lockedGuest->id) {
            throw new LoyaltyVoucherInvalidException(__('custom.errors.loyalty_voucher_invalid'));
        }

        $voucher->update([
            'status' => LoyaltyVoucherStatus::USED,
            'reservation_id' => $reservation->id,
            'used_at' => now(),
        ]);
    }
}
