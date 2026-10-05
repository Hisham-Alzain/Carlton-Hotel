<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyVoucherStatus;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyVoucher;
use App\Support\LoyaltyLedger;
use Illuminate\Support\Facades\DB;

/**
 * The daily loyalty expiry sweep (Phase 10, LOY-09, Pitfall 5).
 *
 * Bookkeeping only: availability already filters `expires_at > now()` (see
 * LoyaltyLedger), so a batch past its expiry is unspendable before this runs.
 * The sweep writes the `expire` ledger entry, empties the batch and flips the
 * status, and it flips expired vouchers.
 *
 * Idempotent and safe to overlap: each batch gets its own transaction that
 * locks the guest first and the batch second (M-6), and LoyaltyLedger::expire
 * re-reads the batch FOR UPDATE and writes nothing unless it is still active.
 * The unique `expire:batch:{id}` ledger key is the backstop.
 */
class ExpireLoyaltyBatchesAction
{
    private const CHUNK = 200;

    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * @return array{data: array{batches_expired: int, vouchers_expired: int}, code: int}
     */
    public function handle(): array
    {
        return [
            'data' => [
                'batches_expired' => $this->expireBatches(),
                'vouchers_expired' => $this->expireVouchers(),
            ],
            'code' => 200,
        ];
    }

    private function expireBatches(): int
    {
        $expired = 0;

        LoyaltyEarnBatch::query()
            ->where('status', LoyaltyBatchStatus::ACTIVE->value)
            ->where('expires_at', '<=', now())
            ->chunkById(self::CHUNK, function ($batches) use (&$expired): void {
                foreach ($batches as $batch) {
                    $entry = DB::transaction(function () use ($batch) {
                        Guest::query()->whereKey($batch->guest_id)->lockForUpdate()->first();

                        return $this->ledger->expire($batch);
                    });

                    if ($entry !== null) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    private function expireVouchers(): int
    {
        $expired = 0;

        LoyaltyVoucher::query()
            ->where('status', LoyaltyVoucherStatus::ACTIVE->value)
            ->where('expires_at', '<=', now())
            ->chunkById(self::CHUNK, function ($vouchers) use (&$expired): void {
                foreach ($vouchers as $candidate) {
                    $expired += DB::transaction(function () use ($candidate): int {
                        Guest::query()->whereKey($candidate->guest_id)->lockForUpdate()->first();

                        // Re-read under lock: a booking may have used it since the chunk was read.
                        $voucher = LoyaltyVoucher::query()->whereKey($candidate->id)->lockForUpdate()->first();
                        if ($voucher === null
                            || $voucher->status !== LoyaltyVoucherStatus::ACTIVE
                            || $voucher->expires_at->greaterThan(now())) {
                            return 0;
                        }

                        $voucher->status = LoyaltyVoucherStatus::EXPIRED;
                        $voucher->save();

                        return 1;
                    });
                }
            });

        return $expired;
    }
}
