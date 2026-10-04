<?php

namespace App\Support;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Exceptions\LoyaltyInsufficientPointsException;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only class that moves loyalty points between earn batches and the
 * ledger (Phase 10, M-6, M-9): balance reads, FIFO consume, credit, record,
 * refund (Q3), clawback (Q2) and expire.
 *
 * CALLER CONTRACT: call every mutating method inside an open DB transaction
 * with the guest row already locked (`Guest::...->lockForUpdate()`). This class
 * then locks the batch rows it reads, so the lock order is always
 * guest -> batches (M-6) and two concurrent spends cannot drain one batch
 * twice. The methods also open their own (nested, savepoint) transaction so a
 * half-written reversal can never be left behind, but that is a safety net and
 * not a substitute for the caller's guest lock.
 *
 * FIFO is `ORDER BY expires_at, id` (Q14). Availability ALWAYS filters
 * `expires_at > now()` itself: the daily expiry sweep is bookkeeping only and
 * is never the gate, so an expired-but-unswept batch is neither counted nor
 * consumable (at `expires_at` exactly it is already gone). There is no stored
 * balance column; a balance is a SUM over batches.
 */
final class LoyaltyLedger
{
    /** Spendable points of one guest: SUM(points_remaining) over active, unexpired batches. */
    public function available(int $guestId): int
    {
        return (int) $this->spendable($guestId)->sum('points_remaining');
    }

    /**
     * Available points, points expiring within `$warningDays`, and the earliest
     * expiry, from one query over the guest's active unexpired batches.
     *
     * @return array{available: int, expiring_soon: int, next_expiry_at: ?CarbonImmutable}
     */
    public function balances(int $guestId, int $warningDays): array
    {
        $horizon = now()->addDays($warningDays);

        $row = $this->spendable($guestId)->toBase()->selectRaw(
            'COALESCE(SUM(points_remaining), 0) as available, '
            .'COALESCE(SUM(CASE WHEN expires_at <= ? THEN points_remaining ELSE 0 END), 0) as expiring_soon, '
            .'MIN(expires_at) as next_expiry_at',
            [$horizon],
        )->first();

        return [
            'available' => (int) $row->available,
            'expiring_soon' => (int) $row->expiring_soon,
            'next_expiry_at' => $row->next_expiry_at !== null ? CarbonImmutable::parse($row->next_expiry_at) : null,
        ];
    }

    /**
     * Create an active batch of `$points`. `$attributes` may set `folio_id`,
     * `awarded_by` and `reason`; nothing else is accepted.
     *
     * @param  array{folio_id?: ?int, awarded_by?: ?int, reason?: ?string}  $attributes
     */
    public function credit(
        int $guestId,
        int $points,
        LoyaltyBatchSource $source,
        CarbonImmutable $expiresAt,
        array $attributes = [],
    ): LoyaltyEarnBatch {
        $this->requirePositive($points);

        return LoyaltyEarnBatch::query()->create([
            'guest_id' => $guestId,
            'source' => $source,
            'points' => $points,
            'points_remaining' => $points,
            'earned_at' => now(),
            'expires_at' => $expiresAt,
            'status' => LoyaltyBatchStatus::ACTIVE,
            ...Arr::only($attributes, ['folio_id', 'awarded_by', 'reason']),
        ]);
    }

    /**
     * Take `$points` from the guest's unexpired batches, earliest expiry first
     * (Q14), locking them. Returns what was taken from each batch; the caller
     * records the ledger entry with these as its allocations.
     *
     * @return list<array{batch_id: int, points: int}>
     *
     * @throws LoyaltyInsufficientPointsException when the unexpired balance is smaller than `$points`
     */
    public function consume(int $guestId, int $points): array
    {
        $this->requirePositive($points);

        return DB::transaction(function () use ($guestId, $points): array {
            $batches = $this->spendable($guestId)
                ->where('points_remaining', '>', 0)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = (int) $batches->sum('points_remaining');
            if ($available < $points) {
                throw new LoyaltyInsufficientPointsException(
                    __('custom.errors.loyalty_insufficient_points'),
                    ['available_points' => $available, 'requested_points' => $points],
                );
            }

            $allocations = [];
            $need = $points;
            foreach ($batches as $batch) {
                if ($need === 0) {
                    break;
                }

                $take = min($need, $batch->points_remaining);
                $this->drain($batch, $take, LoyaltyBatchStatus::DEPLETED);
                $allocations[] = ['batch_id' => $batch->id, 'points' => $take];
                $need -= $take;
            }

            return $allocations;
        });
    }

    /**
     * Write one immutable ledger entry (`occurred_at` defaults to now) and its
     * allocation rows.
     *
     * @param  array<string, mixed>  $attributes  LoyaltyLedgerEntry attributes (points is signed)
     * @param  list<array{batch_id: int, points: int}>  $allocations  unsigned points per batch moved
     *
     * @throws LogicException when allocations are given and do not sum to abs(points)
     */
    public function record(array $attributes, array $allocations = []): LoyaltyLedgerEntry
    {
        if ($allocations !== [] && array_sum(array_column($allocations, 'points')) !== abs((int) $attributes['points'])) {
            throw new LogicException('Allocations must sum to the absolute points of the ledger entry.');
        }

        return DB::transaction(function () use ($attributes, $allocations): LoyaltyLedgerEntry {
            $entry = LoyaltyLedgerEntry::query()->create(['occurred_at' => now(), ...$attributes]);

            foreach ($allocations as $allocation) {
                $entry->allocations()->create([
                    'batch_id' => $allocation['batch_id'],
                    'points' => $allocation['points'],
                ]);
            }

            return $entry;
        });
    }

    /**
     * Give a redeem's points back (Q3). Each allocated batch that is still
     * active or depleted and unexpired takes its points back and becomes
     * active; everything else goes into ONE new `refund` batch with a full new
     * term. Expired and reversed batches never revive. Idempotent: an
     * already-refunded redeem returns its existing refund entry.
     */
    public function refund(LoyaltyLedgerEntry $redeemEntry): LoyaltyLedgerEntry
    {
        if ($redeemEntry->type !== LoyaltyEntryType::REDEEM) {
            throw new LogicException('Only a redeem entry can be refunded.');
        }

        return DB::transaction(function () use ($redeemEntry): LoyaltyLedgerEntry {
            if ($existing = $this->reversalOf($redeemEntry)) {
                return $existing;
            }

            $allocations = $redeemEntry->allocations()->orderBy('batch_id')->get();
            $batches = LoyaltyEarnBatch::query()
                ->whereIn('id', $allocations->pluck('batch_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $now = now();
            $credited = [];
            $leftover = 0;
            foreach ($allocations as $allocation) {
                $batch = $batches->get($allocation->batch_id);

                if ($batch !== null && $this->canRevive($batch, $now)) {
                    $batch->points_remaining += $allocation->points;
                    $batch->status = LoyaltyBatchStatus::ACTIVE;
                    $batch->save();
                    $credited[] = ['batch_id' => $batch->id, 'points' => $allocation->points];
                } else {
                    $leftover += $allocation->points;
                }
            }

            $refundBatch = null;
            if ($leftover > 0) {
                $refundBatch = $this->credit(
                    $redeemEntry->guest_id,
                    $leftover,
                    LoyaltyBatchSource::REFUND,
                    LoyaltyProgram::current()->expiresAtFrom($now),
                );
                $credited[] = ['batch_id' => $refundBatch->id, 'points' => $leftover];
            }

            return $this->record([
                'guest_id' => $redeemEntry->guest_id,
                'type' => LoyaltyEntryType::REFUND,
                'source' => LoyaltyBatchSource::REFUND,
                'points' => array_sum(array_column($credited, 'points')),
                'batch_id' => $refundBatch?->id,
                'voucher_id' => $redeemEntry->voucher_id,
                'reservation_id' => $redeemEntry->reservation_id,
                'reverses_entry_id' => $redeemEntry->id,
                'idempotency_key' => 'refund:'.$redeemEntry->id,
            ], $credited);
        });
    }

    /**
     * Take back what an earn granted (Q2): up to the earned points from the
     * originating batch, then from the guest's other active unexpired batches
     * FIFO, never below zero. Whatever was already spent is recorded as
     * `shortfall_points` instead of failing, so a cancellation is never
     * blocked and no balance goes negative. Idempotent.
     *
     * An origin that is active or depleted ends `reversed` (even when
     * unswept past its expiry, so its points are removed once, here, rather
     * than again by the sweep); an expired or reversed origin is left as it is
     * and only the other batches are drawn on (FA-10.03-2).
     */
    public function clawback(LoyaltyLedgerEntry $earnEntry): LoyaltyLedgerEntry
    {
        if ($earnEntry->type !== LoyaltyEntryType::EARN) {
            throw new LogicException('Only an earn entry can be clawed back.');
        }

        return DB::transaction(function () use ($earnEntry): LoyaltyLedgerEntry {
            if ($existing = $this->reversalOf($earnEntry)) {
                return $existing;
            }

            $earned = $earnEntry->points;
            $remaining = $earned;
            $allocations = [];

            $origin = $earnEntry->batch_id === null ? null : LoyaltyEarnBatch::query()
                ->whereKey($earnEntry->batch_id)
                ->lockForUpdate()
                ->first();

            if ($origin !== null && in_array($origin->status, [LoyaltyBatchStatus::ACTIVE, LoyaltyBatchStatus::DEPLETED], true)) {
                $take = min($remaining, $origin->points_remaining);
                $origin->points_remaining -= $take;
                $origin->status = LoyaltyBatchStatus::REVERSED;
                $origin->save();

                if ($take > 0) {
                    $allocations[] = ['batch_id' => $origin->id, 'points' => $take];
                    $remaining -= $take;
                }
            }

            if ($remaining > 0) {
                $others = $this->spendable($earnEntry->guest_id)
                    ->where('points_remaining', '>', 0)
                    ->when($origin, fn (Builder $query) => $query->where('id', '!=', $origin->id))
                    ->orderBy('expires_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($others as $batch) {
                    if ($remaining === 0) {
                        break;
                    }

                    $take = min($remaining, $batch->points_remaining);
                    $this->drain($batch, $take, LoyaltyBatchStatus::DEPLETED);
                    $allocations[] = ['batch_id' => $batch->id, 'points' => $take];
                    $remaining -= $take;
                }
            }

            $taken = $earned - $remaining;

            return $this->record([
                'guest_id' => $earnEntry->guest_id,
                'type' => LoyaltyEntryType::CLAWBACK,
                'source' => $earnEntry->source,
                'points' => -$taken,
                'shortfall_points' => $remaining,
                'folio_id' => $earnEntry->folio_id,
                'reservation_id' => $earnEntry->reservation_id,
                'reverses_entry_id' => $earnEntry->id,
                'idempotency_key' => 'clawback:'.$earnEntry->id,
            ], $allocations);
        });
    }

    /**
     * Expire a batch: write an `expire` entry for all that is left and empty
     * it. The batch is re-read under lock, so a stale model, a batch that was
     * already swept, spent or reversed, or one with nothing left writes nothing
     * and returns null.
     */
    public function expire(LoyaltyEarnBatch $lockedBatch): ?LoyaltyLedgerEntry
    {
        return DB::transaction(function () use ($lockedBatch): ?LoyaltyLedgerEntry {
            $batch = LoyaltyEarnBatch::query()->whereKey($lockedBatch->getKey())->lockForUpdate()->first();

            if ($batch === null || $batch->status !== LoyaltyBatchStatus::ACTIVE || $batch->points_remaining === 0) {
                return null;
            }

            $points = $batch->points_remaining;

            $entry = $this->record([
                'guest_id' => $batch->guest_id,
                'type' => LoyaltyEntryType::EXPIRE,
                'source' => $batch->source,
                'points' => -$points,
                'batch_id' => $batch->id,
                'idempotency_key' => 'expire:batch:'.$batch->id,
            ], [['batch_id' => $batch->id, 'points' => $points]]);

            $batch->points_remaining = 0;
            $batch->status = LoyaltyBatchStatus::EXPIRED;
            $batch->save();

            return $entry;
        });
    }

    /** The guest's active batches that have not yet expired, judged against the clock and never the sweep. */
    private function spendable(int $guestId): Builder
    {
        return LoyaltyEarnBatch::query()
            ->where('guest_id', $guestId)
            ->where('status', LoyaltyBatchStatus::ACTIVE->value)
            ->where('expires_at', '>', now());
    }

    private function reversalOf(LoyaltyLedgerEntry $entry): ?LoyaltyLedgerEntry
    {
        return LoyaltyLedgerEntry::query()->where('reverses_entry_id', $entry->id)->first();
    }

    /** Q3: only an active or depleted batch that has not yet expired takes refunded points back. */
    private function canRevive(LoyaltyEarnBatch $batch, CarbonImmutable|CarbonInterface $now): bool
    {
        return in_array($batch->status, [LoyaltyBatchStatus::ACTIVE, LoyaltyBatchStatus::DEPLETED], true)
            && $batch->expires_at->greaterThan($now);
    }

    /** Remove `$take` points from a locked batch (never below zero); `$emptied` is the status once it hits zero. */
    private function drain(LoyaltyEarnBatch $batch, int $take, LoyaltyBatchStatus $emptied): void
    {
        $batch->points_remaining -= $take;
        if ($batch->points_remaining === 0) {
            $batch->status = $emptied;
        }
        $batch->save();
    }

    private function requirePositive(int $points): void
    {
        if ($points <= 0) {
            throw new LogicException('Loyalty points must be positive.');
        }
    }
}
