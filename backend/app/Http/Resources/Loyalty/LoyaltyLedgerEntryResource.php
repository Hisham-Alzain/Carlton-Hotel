<?php

namespace App\Http\Resources\Loyalty;

use App\Base\BaseResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * One loyalty ledger row (Q16). Only uuids and booking codes are exposed.
 * `reason` and `performed_by` are staff notes: emitted for a staff `User`
 * caller and absent for a guest (Pitfall 14). Every relation is guarded by
 * `whenLoaded`; LoyaltyAccountService::ledger() loads them all, so a row costs
 * no query here.
 */
class LoyaltyLedgerEntryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $isStaff = $request->user() instanceof User;

        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'points' => $this->points,
            'shortfall_points' => $this->shortfall_points,
            'discount_usd' => $this->discount_usd,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            // Only a credit created a batch with an expiry; an `expire` row points at the batch it closed.
            'expires_at' => $this->whenLoaded(
                'batch',
                fn () => $this->points > 0 ? $this->batch?->expires_at?->toIso8601String() : null,
            ),
            'reservation' => $this->whenLoaded('reservation', fn () => $this->reservation === null ? null : [
                'uuid' => $this->reservation->uuid,
                'booking_code' => $this->reservation->booking_code,
            ]),
            'folio' => $this->whenLoaded('folio', fn () => $this->folio === null ? null : [
                'uuid' => $this->folio->uuid,
            ]),
            'voucher' => $this->whenLoaded('voucher', fn () => $this->voucher === null ? null : [
                'uuid' => $this->voucher->uuid,
                'code' => $this->voucher->code,
            ]),
            'reason' => $this->when($isStaff, fn () => $this->reason),
            'performed_by' => $this->when($isStaff, fn () => $this->whenLoaded(
                'performer',
                fn () => $this->performer === null ? null : [
                    'uuid' => $this->performer->uuid,
                    'name' => $this->performer->name,
                ],
            )),
        ];
    }
}
