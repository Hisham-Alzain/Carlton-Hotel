<?php

namespace App\Http\Resources\Folio;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * A stay's bill: the folio, its line items and the payments recorded against
 * it. Read-only over a closed folio — it never triggers regeneration.
 *
 * The resource wraps an array assembled by ReceiptService, not a model.
 */
class ReceiptResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $reservation = $this->resource['reservation'];
        $folio       = $this->resource['folio'];

        return [
            'reservation' => [
                'uuid'         => $reservation->uuid,
                'booking_code' => $reservation->booking_code,
                'check_in'     => $reservation->check_in?->toDateString(),
                'check_out'    => $reservation->check_out?->toDateString(),
                'nights'       => $reservation->nights(),
                'guest_name'   => trim("{$reservation->guest?->first_name} {$reservation->guest?->last_name}")
                    ?: $reservation->guest?->name,
            ],
            'folio' => [
                'uuid'                 => $folio->uuid,
                'status'               => $folio->status,
                'subtotal_usd'         => $folio->subtotal_usd,
                'total_usd'            => $folio->total_usd,
                'approved_by_guest_at' => $folio->approved_by_guest_at?->toIso8601String(),
                'settled_at'           => $folio->settled_at?->toIso8601String(),
            ],
            'items' => $folio->items->map(fn ($item) => [
                // Stored as a plain string, not {en, ar} — AR receipts show the
                // description as recorded. Flagged for a future migration.
                'description' => $item->description,
                'amount_usd'  => $item->amount_usd,
                'source_type' => $item->source_type,
            ])->all(),
            'payments' => $this->resource['payments']->map(fn ($payment) => [
                'method'     => $payment->method,
                'amount_usd' => $payment->amount_usd,
                'status'     => $payment->status,
                'created_at' => $payment->created_at?->toIso8601String(),
            ])->all(),
            'balance_due_usd' => $this->resource['balance_due_usd'],
        ];
    }
}
