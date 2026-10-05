<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\Reservation;
use App\Support\LoyaltyLedger;
use Illuminate\Support\Facades\DB;

/**
 * Claws back every point a folio earned (Phase 10, LOY-18, Q1, Q2, M-6).
 *
 * Q1 seam: this is the folio-level half of a reversal, kept standalone so the
 * future folio refund flow can call it without going through a reservation
 * cancel. Today only ReverseLoyaltyForReservationAction (the cancel path)
 * reaches it. It does not add a refund endpoint and never reopens a settled
 * folio.
 *
 * Q2: each earn entry is taken back through LoyaltyLedger::clawback (origin
 * batch first, then the guest's other active batches FIFO, floor zero, the
 * unrecoverable part recorded as `shortfall_points`). It never throws because
 * the points were already spent, and it is idempotent: a second call returns
 * the existing clawback entries and writes nothing.
 *
 * Lock order (M-6): folio -> guest -> earn entries -> batches. A caller that
 * already holds some of these locks (the cancel path holds the reservation and
 * re-locks the folio and guest) simply re-acquires them in the same order.
 *
 * // TODO(refund flow): call from the future folio refund action; today only CancelReservationAction reaches this (Q1).
 */
class ReverseLoyaltyForFolioAction
{
    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * @return array{data: list<LoyaltyLedgerEntry>, code: int} the clawback entries, oldest earn first
     */
    public function handle(Folio $folio): array
    {
        return DB::transaction(function () use ($folio): array {
            $locked = Folio::query()->whereKey($folio->id)->lockForUpdate()->firstOrFail();

            $guestId = Reservation::query()->whereKey($locked->reservation_id)->value('guest_id');
            if ($guestId === null) {
                return ['data' => [], 'code' => 200];
            }

            Guest::query()->whereKey($guestId)->lockForUpdate()->firstOrFail();

            $earns = LoyaltyLedgerEntry::query()
                ->where('folio_id', $locked->id)
                ->where('type', LoyaltyEntryType::EARN->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            return [
                'data' => $earns->map(fn (LoyaltyLedgerEntry $earn): LoyaltyLedgerEntry => $this->ledger->clawback($earn))->all(),
                'code' => 200,
            ];
        });
    }
}
