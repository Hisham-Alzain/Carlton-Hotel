<?php

namespace App\Actions\Loyalty;

use App\Enums\GuestAccountStatus;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for Phase 10 gap LOY-23: forfeits the points and closes
 * the vouchers still held by accounts deleted before the deletion forfeit
 * shipped (10-16). Run through `php artisan loyalty:forfeit-deleted`.
 *
 * Each candidate goes through ForfeitLoyaltyBalanceAction in its own
 * transaction, so each lock stays short and a failure on one guest leaves the
 * others done. Lock order per guest is guest -> batches -> vouchers (M-6). The
 * guest is re-checked with isDeleted() under its lock, so an account that is
 * not deleted is never touched.
 *
 * Idempotent: a second run finds no candidate and returns zeros. It writes no
 * activity entry of its own; the `expire` ledger rows and the voucher model's
 * status log are the record (no code, no PII).
 */
class ForfeitDeletedGuestBalancesAction
{
    public function __construct(private readonly ForfeitLoyaltyBalanceAction $forfeit) {}

    /**
     * @return array{data: array{accounts: int, forfeited_points: int, expired_batches: int, closed_vouchers: int}, code: int}
     */
    public function handle(): array
    {
        $totals = ['accounts' => 0, 'forfeited_points' => 0, 'expired_batches' => 0, 'closed_vouchers' => 0];

        foreach ($this->candidateIds() as $guestId) {
            $result = DB::transaction(function () use ($guestId): ?array {
                $locked = Guest::query()->whereKey($guestId)->lockForUpdate()->first();
                if ($locked === null || ! $locked->isDeleted()) {
                    return null;
                }

                return $this->forfeit->handle($locked)['data'];
            });

            if ($result === null) {
                continue;
            }

            $totals['forfeited_points'] += $result['forfeited_points'];
            $totals['expired_batches'] += $result['expired_batches'];
            $totals['closed_vouchers'] += $result['closed_vouchers'];
            if ($result['forfeited_points'] > 0 || $result['closed_vouchers'] > 0) {
                $totals['accounts']++;
            }
        }

        return ['data' => $totals, 'code' => 200];
    }

    /**
     * Deleted accounts that still hold an active batch with points or an
     * active voucher, by id.
     *
     * @return list<int>
     */
    private function candidateIds(): array
    {
        return Guest::query()
            ->where('account_status', GuestAccountStatus::DELETED->value)
            ->where(fn (Builder $query) => $query
                ->whereHas('loyaltyBatches', fn (Builder $batch) => $batch
                    ->where('status', LoyaltyBatchStatus::ACTIVE->value)
                    ->where('points_remaining', '>', 0))
                ->orWhereHas('loyaltyVouchers', fn (Builder $voucher) => $voucher
                    ->where('status', LoyaltyVoucherStatus::ACTIVE->value)))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
