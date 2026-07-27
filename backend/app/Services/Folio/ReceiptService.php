<?php

namespace App\Services\Folio;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Collection;

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
        $folio = Folio::with('items')->where('reservation_id', $reservation->id)->first();

        // No folio means nothing was ever billed (e.g. a cancelled stay).
        if (! $folio) {
            return null;
        }

        $payments = $this->paymentsFor($reservation, $folio);

        $paid = $payments
            ->where('status', 'completed')
            ->sum(fn (Payment $payment) => (float) $payment->amount_usd);

        $reservation->loadMissing('guest');

        return [
            'reservation'     => $reservation,
            'folio'           => $folio,
            'payments'        => $payments,
            'balance_due_usd' => round((float) $folio->total_usd - $paid, 2),
        ];
    }

    /**
     * Payments are polymorphic and, depending on the settle path taken, are
     * recorded against either the folio or the reservation. Both are collected
     * so a receipt never under-reports what the guest paid.
     */
    private function paymentsFor(Reservation $reservation, Folio $folio): Collection
    {
        return Payment::query()
            ->where(function ($query) use ($reservation, $folio) {
                $query->where(fn ($q) => $q->where('payable_type', Folio::class)->where('payable_id', $folio->id))
                    ->orWhere(fn ($q) => $q->where('payable_type', Reservation::class)->where('payable_id', $reservation->id));
            })
            ->orderBy('created_at')
            ->get();
    }
}
