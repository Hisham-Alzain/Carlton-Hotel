<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Exceptions\GuestAccountDeletedException;
use App\Exceptions\LoyaltyAdjustmentInvalidException;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\User;
use App\Support\IdempotentWrite;
use App\Support\LoyaltyLedger;
use App\Support\LoyaltyProgram;
use Illuminate\Support\Facades\DB;

/**
 * A staff member awards or deducts points by hand (Phase 10, LOY-05, Q7, Q20).
 *
 * Lock order is guest -> batches (M-6): the guest row is locked here, then
 * LoyaltyLedger locks the batches it reads. The ledger key is
 * `adjust:{guest_id}:{client_key}` (the guest is part of it so one client key
 * can be reused for another guest); a replay (same key, points, reason and
 * actor) answers the stored entry with 200, anything else under the key is an
 * IdempotencyConflictException (409). The actor is part of the payload, as in
 * RecordFolioPaymentAction.
 *
 * A positive adjustment opens a `manual` batch with the program's expiry; a
 * negative one consumes FIFO through LoyaltyLedger::consume() and so can never
 * drive a batch or the balance below zero (LoyaltyInsufficientPointsException).
 * The magnitude guard runs first and is a domain error so non-HTTP callers get
 * it too.
 *
 * A deleted account (9.1) is refused with `guest_account_deleted` (LOY-23)
 * under the guest lock, before the replay lookup, so neither a fresh write nor
 * a replay reaches the ledger.
 */
class AdjustLoyaltyPointsAction
{
    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * @return array{data: LoyaltyLedgerEntry, code: int}
     *
     * @throws LoyaltyAdjustmentInvalidException
     * @throws GuestAccountDeletedException
     */
    public function handle(Guest $guest, int $points, string $reason, User $actor, string $key): array
    {
        $max = (int) config('loyalty.max_adjust_points');

        if ($points === 0 || abs($points) > $max) {
            throw new LoyaltyAdjustmentInvalidException(
                __('custom.errors.loyalty_adjustment_invalid'),
                ['max_adjust_points' => $max],
            );
        }

        return DB::transaction(function () use ($guest, $points, $reason, $actor, $key) {
            $locked = Guest::whereKey($guest->id)->lockForUpdate()->firstOrFail();
            if ($locked->isDeleted()) {
                throw new GuestAccountDeletedException(__('custom.errors.guest_account_deleted'));
            }

            $ledgerKey = 'adjust:'.$locked->id.':'.$key;

            [$entry, $replayed] = IdempotentWrite::run(
                $ledgerKey,
                fn () => LoyaltyLedgerEntry::where('idempotency_key', $ledgerKey)->first(),
                fn (LoyaltyLedgerEntry $stored) => $stored->points === $points
                    && $stored->reason === $reason
                    && (int) $stored->performed_by === (int) $actor->id,
                fn () => $this->write($locked, $points, $reason, $actor, $ledgerKey),
            );

            if (! $replayed) {
                activity()
                    ->performedOn($locked)
                    ->causedBy($actor)
                    ->withProperties(['guest_uuid' => $locked->uuid, 'points' => $points, 'entry_uuid' => $entry->uuid])
                    ->log('loyalty.points_adjusted');
            }

            return ['data' => $entry->load('batch', 'performer'), 'code' => $replayed ? 200 : 201];
        });
    }

    private function write(Guest $locked, int $points, string $reason, User $actor, string $ledgerKey): LoyaltyLedgerEntry
    {
        $entry = [
            'guest_id' => $locked->id,
            'type' => LoyaltyEntryType::ADJUST,
            'source' => LoyaltyBatchSource::MANUAL,
            'performed_by' => $actor->id,
            'reason' => $reason,
            'idempotency_key' => $ledgerKey,
        ];

        if ($points > 0) {
            $batch = $this->ledger->credit(
                $locked->id,
                $points,
                LoyaltyBatchSource::MANUAL,
                LoyaltyProgram::current()->expiresAtFrom(now()),
                ['awarded_by' => $actor->id, 'reason' => $reason],
            );

            return $this->ledger->record([...$entry, 'points' => $points, 'batch_id' => $batch->id]);
        }

        $allocations = $this->ledger->consume($locked->id, abs($points));

        return $this->ledger->record([...$entry, 'points' => $points], $allocations);
    }
}
