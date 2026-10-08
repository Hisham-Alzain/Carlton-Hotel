<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyEntryType;
use App\Enums\ReservationStatus;
use App\Models\Folio;
use App\Models\LoyaltyLedgerEntry;
use App\Support\LoyaltyLedger;
use App\Support\LoyaltyMath;
use App\Support\LoyaltyProgram;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Credits loyalty points for a folio that has just been settled (Phase 10,
 * LOY-02/03/04).
 *
 * Q8, inline and atomic: the three settlement statements (SettleFolioAction
 * twice, RecordFolioPaymentAction once) call this immediately after the status
 * flips to settled, inside the same folio-locked transaction. It is never run
 * from an event listener or after commit, so a failure rolls the settlement back
 * (the folio stays open and the retry earns) and a settled folio always has its
 * points. Historical folios are never backfilled.
 *
 * Q9: earning off (no settings row, earn_rate null or 0) or a reservation with
 * no guest writes nothing; a cancelled reservation writes only an
 * `loyalty.earn_skipped_cancelled` activity entry on the folio. A reservation
 * whose guest deleted their account (9.1) earns nothing and writes only
 * `loyalty.earn_skipped_deleted` with the skipped point count (LOY-23). That
 * guest read takes no lock (M-6): 9.1 refuses a deletion while the folio is
 * open, so either the deletion sees the folio open and is refused, or it runs
 * after this settlement committed and forfeits the new batch.
 *
 * Q10/Q11, buckets: `stay` is the reservation lines; `service` is the
 * service_booking, service_request and manual lines (by sign). A credit reduces
 * the bucket of the line it reverses (reverses_item_id); a standalone credit
 * reduces `service`. Each bucket is summed in bcmath, clamped at 0.00, and earns
 * halfUp(spend x rate) points, so a folio has at most two batches. The room line
 * equals reservations.total_usd, already net of any points discount, so a
 * points-discounted amount never earns.
 *
 * Locks (M-6): the caller holds the folio lock. This action is insert-only and
 * takes no guest or batch lock.
 *
 * Once only (M-2): each bucket is written in its own savepoint under the unique
 * ledger key `earn:folio:{folio_id}:{bucket}` (and the unique
 * `(folio_id, source)` on batches). A UniqueConstraintViolationException is the
 * only error caught: it means "already earned", so that bucket is skipped.
 * Anything else propagates and rolls the whole settlement back.
 */
class EarnLoyaltyPointsAction
{
    private const STAY = 'stay';

    private const SERVICE = 'service';

    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * @param  Folio  $lockedFolio  a settled folio the caller holds the row lock on
     * @return array{data: list<LoyaltyLedgerEntry>, code: int} the entries created
     */
    public function handle(Folio $lockedFolio): array
    {
        $program = LoyaltyProgram::current();

        if (! $program->earningEnabled()) {
            return $this->none();
        }

        $reservation = $lockedFolio->reservation;

        if ($reservation === null || $reservation->guest_id === null) {
            return $this->none();
        }

        if ($reservation->status === ReservationStatus::CANCELLED) {
            activity()
                ->performedOn($lockedFolio)
                ->withProperties([
                    'folio_uuid' => $lockedFolio->uuid,
                    'reservation_uuid' => $reservation->uuid,
                ])
                ->log('loyalty.earn_skipped_cancelled');

            return $this->none();
        }

        $bucketPoints = array_map(
            fn (string $spend): int => LoyaltyMath::pointsForSpend($spend, $program->earnRate()),
            $this->spendByBucket($lockedFolio),
        );

        if ($reservation->guest?->isDeleted()) {
            activity()
                ->performedOn($lockedFolio)
                ->causedByAnonymous()
                ->withProperties(['skipped_points' => (int) array_sum($bucketPoints)])
                ->log('loyalty.earn_skipped_deleted');

            return $this->none();
        }

        $entries = [];

        foreach ($bucketPoints as $bucket => $points) {
            if ($points === 0) {
                continue;
            }

            try {
                $entries[] = DB::transaction(fn () => $this->earnBucket($lockedFolio, $reservation->guest_id, $reservation->id, $bucket, $points, $program));
            } catch (UniqueConstraintViolationException) {
                // Already earned for this folio and bucket: skip it, settlement still commits.
            }
        }

        return ['data' => $entries, 'code' => 200];
    }

    private function earnBucket(
        Folio $folio,
        int $guestId,
        int $reservationId,
        string $bucket,
        int $points,
        LoyaltyProgram $program,
    ): LoyaltyLedgerEntry {
        $source = $bucket === self::STAY ? LoyaltyBatchSource::STAY : LoyaltyBatchSource::SERVICE;

        $batch = $this->ledger->credit($guestId, $points, $source, $program->expiresAtFrom(now()), ['folio_id' => $folio->id]);

        return $this->ledger->record([
            'guest_id' => $guestId,
            'type' => LoyaltyEntryType::EARN,
            'source' => $source,
            'points' => $points,
            'batch_id' => $batch->id,
            'folio_id' => $folio->id,
            'reservation_id' => $reservationId,
            'idempotency_key' => "earn:folio:{$folio->id}:{$bucket}",
        ]);
    }

    /**
     * Spend per bucket as two-decimal strings, each clamped at 0.00.
     *
     * @return array{stay: string, service: string}
     */
    private function spendByBucket(Folio $folio): array
    {
        $items = $folio->items()->get(['id', 'amount_usd', 'source_type', 'reverses_item_id']);

        $bucketOf = [];
        $totals = [self::STAY => '0.00', self::SERVICE => '0.00'];

        foreach ($items->where('source_type', '!=', 'credit') as $item) {
            $bucket = $item->source_type === 'reservation' ? self::STAY : self::SERVICE;
            $bucketOf[$item->id] = $bucket;
            $totals[$bucket] = bcadd($totals[$bucket], (string) $item->amount_usd, 2);
        }

        foreach ($items->where('source_type', 'credit') as $credit) {
            $bucket = $bucketOf[$credit->reverses_item_id] ?? self::SERVICE;
            $totals[$bucket] = bcadd($totals[$bucket], (string) $credit->amount_usd, 2);
        }

        return array_map(
            fn (string $total) => bccomp($total, '0', 2) < 0 ? '0.00' : $total,
            $totals,
        );
    }

    /** @return array{data: list<LoyaltyLedgerEntry>, code: int} */
    private function none(): array
    {
        return ['data' => [], 'code' => 200];
    }
}
