<?php

namespace App\Services\Folio;

use App\Models\Folio;
use App\Models\Reservation;
use App\Support\FolioLedger;

/**
 * Assembles a stay's receipt.
 *
 * Deliberately separate from FolioService: `GET /folio` serves the *current*
 * stay behind `is_checked_in` and can trigger regeneration. A receipt is a
 * read-only view over a closed folio and must never rewrite line items, so a
 * past stay can be reprinted years later and match what the guest paid.
 */
class ReceiptService
{
    public function forReservation(Reservation $reservation): ?array
    {
        $folio = Folio::with(['items' => fn ($query) => $query->orderBy('id')])
            ->where('reservation_id', $reservation->id)
            ->first();

        // No folio means nothing was ever billed (e.g. a cancelled stay).
        if (! $folio) {
            return null;
        }

        // Payments are polymorphic and, depending on the settle path taken, are
        // recorded against either the folio or the reservation. The shared
        // ledger query collects both so a receipt never under-reports what the
        // guest paid, and agrees with FolioResource.balance_due_usd (D-03).
        $payments = $folio->ledgerPayments()->orderBy('created_at')->orderBy('id')->get();

        $reservation->loadMissing('guest');

        return [
            'reservation'     => $reservation,
            'folio'           => $folio,
            'payments'        => $payments,
            'balance_due_usd' => FolioLedger::balance((string) $folio->total_usd, FolioLedger::paid($payments)),
        ];
    }
}
