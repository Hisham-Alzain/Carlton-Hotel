<?php

namespace App\Http\Resources\Loyalty;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * A guest's voucher (LOY-14). `type`, `reward_name` and `value_usd` are the
 * snapshot taken at redeem time. `reservation` is null until the voucher is
 * applied to a booking and is guarded by `whenLoaded`; both services that
 * produce vouchers load it, so a row costs no query here.
 */
class LoyaltyVoucherResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            // Whole locale map, as in the rewards catalog: clients switch language off one fetch.
            'reward_name' => $this->reward_name,
            'value_usd' => $this->value_usd,
            'points_spent' => $this->points_spent,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'reservation' => $this->whenLoaded('reservation', fn () => $this->reservation === null ? null : [
                'uuid' => $this->reservation->uuid,
                'booking_code' => $this->reservation->booking_code,
            ]),
        ];
    }
}
