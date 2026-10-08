<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use App\Models\LoyaltyVoucher;
use App\Support\LoyaltyLedger;
use Illuminate\Support\Facades\DB;

/**
 * Forfeits a deleted account's loyalty balance (Phase 10 gap, LOY-23): every
 * active batch is expired through LoyaltyLedger::forfeit() (the ledger stays
 * the only mover of points, M-9) and every active voucher is closed, `expired`
 * when its expires_at is at or before now, otherwise `void`.
 *
 * CALLER CONTRACT: call inside an open transaction with the guest row already
 * locked. Callers: DeleteGuestAccountAction, and ReverseLoyaltyForReservationAction
 * for a guest deleted after the booking. Lock order is guest (caller) ->
 * batches -> vouchers (M-6).
 *
 * Idempotent (a second call finds nothing active and returns zeros), reads no
 * program setting, and changes only the status of a voucher row. Each voucher
 * is saved on its own, so a caller with model logging on keeps the usual
 * voucher audit. Not final, so a test can substitute it.
 */
class ForfeitLoyaltyBalanceAction
{
    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * @return array{data: array{forfeited_points: int, expired_batches: int, closed_vouchers: int}, code: int}
     */
    public function handle(Guest $lockedGuest): array
    {
        return DB::transaction(function () use ($lockedGuest): array {
            $forfeit = $this->ledger->forfeit($lockedGuest->id);

            $vouchers = LoyaltyVoucher::query()
                ->where('guest_id', $lockedGuest->id)
                ->where('status', LoyaltyVoucherStatus::ACTIVE->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $now = now();
            foreach ($vouchers as $voucher) {
                $voucher->status = $voucher->expires_at->greaterThan($now)
                    ? LoyaltyVoucherStatus::VOID
                    : LoyaltyVoucherStatus::EXPIRED;
                $voucher->save();
            }

            return [
                'data' => [
                    'forfeited_points' => $forfeit['points'],
                    'expired_batches' => $forfeit['batches'],
                    'closed_vouchers' => $vouchers->count(),
                ],
                'code' => 200,
            ];
        });
    }
}
