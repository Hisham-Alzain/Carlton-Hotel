<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
use App\Enums\LoyaltyRewardType;
use App\Http\Resources\GuestResource;
use App\Models\User;

class ReservationResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'uuid'           => $this->uuid,
            'booking_code'   => $this->booking_code,
            'status'         => $this->status,
            'check_in'       => $this->check_in?->toDateString(),
            'check_out'      => $this->check_out?->toDateString(),
            'checked_in_at'  => $this->checked_in_at?->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            // Staff-internal (D-11): emitted only when the caller is a staff
            // User. Guest routes resolve a Guest and the public verify route
            // nobody, so the key is absent there.
            'notes'          => $this->when($request->user() instanceof User, $this->notes),
            // Phase 6 (D-20): staff-only, null for stays checked out before the column existed.
            'check_out_mode' => $this->when($request->user() instanceof User, fn () => $this->check_out_mode?->value),
            // Surfaced for staff too, so housekeeping can see the flag the guest set.
            'dnd'            => [
                'enabled' => $this->isDndActive(),
                'until'   => $this->dnd_until?->toIso8601String(),
            ],
            'nights'         => $this->nights(),
            'adults'         => $this->adults,
            'children'       => $this->children,
            'source'         => $this->source,
            'payment_method' => $this->payment_method,
            'total_usd'      => $this->total_usd,
            'hold_expires_at'=> $this->hold_expires_at?->toISOString(),
            'rooms'          => ReservationRoomResource::collection($this->whenLoaded('rooms')),
            'guest'          => new GuestResource($this->whenLoaded('guest')),
            'promo_code'     => $this->whenLoaded('promoCode', fn () => $this->promoCode?->code),
            // Phase 10 (Q16, Q12): what loyalty took off this booking; null when nothing was applied.
            'loyalty'        => $this->whenLoaded('loyaltyApplication', fn () => $this->loyaltyApplication ? [
                'points_redeemed'      => $this->loyaltyApplication->points_redeemed,
                'points_discount_usd'  => $this->loyaltyApplication->points_discount_usd,
                'voucher'              => $this->loyaltyApplication->relationLoaded('voucher') && $this->loyaltyApplication->voucher
                    ? ['code' => $this->loyaltyApplication->voucher->code, 'type' => $this->loyaltyApplication->voucher->type->value]
                    : null,
                'voucher_discount_usd' => $this->loyaltyApplication->voucher_discount_usd,
                'upgrade_requested'    => $this->loyaltyApplication->relationLoaded('voucher')
                    && $this->loyaltyApplication->voucher?->type === LoyaltyRewardType::ROOM_UPGRADE,
                'status'               => $this->loyaltyApplication->status->value,
            ] : null),
            // Only the check-out response loads the folio (D-14).
            // Phase 5 (D-12): open_disputes_count only when the service counted it (no query here).
            'folio'          => $this->whenLoaded('folio', fn () => $this->folio ? array_merge([
                'uuid'      => $this->folio->uuid,
                'status'    => $this->folio->status,
                'total_usd' => $this->folio->total_usd,
            ], array_key_exists('open_disputes_count', $this->folio->getAttributes())
                ? ['open_disputes_count' => (int) $this->folio->open_disputes_count]
                : []) : null),
        ];
    }
}
