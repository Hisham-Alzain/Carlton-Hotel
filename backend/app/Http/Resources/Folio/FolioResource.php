<?php

namespace App\Http\Resources\Folio;

use App\Base\BaseResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Support\FolioLedger;
use Illuminate\Http\Request;

/**
 * One folio shape for every folio response, staff and guest (D-02). Money
 * fields are exact 2dp strings; paid/balance come from the loaded
 * `ledgerPayments` relation (FolioService::display()), never from a query here.
 */
class FolioResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'reservation_uuid'      => $this->whenLoaded('reservation', fn () => $this->reservation->uuid),
            'status'                => $this->status,
            'subtotal_usd'          => $this->subtotal_usd,
            'total_usd'             => $this->total_usd,
            'approved_by_guest_at'  => $this->approved_by_guest_at?->toIso8601String(),
            'settled_at'            => $this->settled_at?->toIso8601String(),
            'items'                 => FolioItemResource::collection($this->whenLoaded('items')),
            'payments'              => PaymentResource::collection($this->whenLoaded('ledgerPayments')),
            'paid_usd'              => $this->whenLoaded('ledgerPayments', fn () => FolioLedger::paid($this->ledgerPayments)),
            // Signed, never clamped: negative means the hotel owes the guest (D-03).
            'balance_due_usd'       => $this->whenLoaded('ledgerPayments', fn () => FolioLedger::balance(
                (string) $this->total_usd,
                FolioLedger::paid($this->ledgerPayments),
            )),
            // A flag, never a check-out gate (D-12).
            'open_disputes_count'   => $this->whenCounted('openDisputes'),
        ];
    }
}
