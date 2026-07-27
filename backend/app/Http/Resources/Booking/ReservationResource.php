<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
use App\Http\Resources\GuestResource;

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
            // Surfaced for staff too, so housekeeping can see the flag the guest set.
            'dnd'            => [
                'enabled' => $this->isDndActive(),
                'until'   => $this->dnd_until?->toIso8601String(),
            ],
            'nights'         => $this->nights(),
            'source'         => $this->source,
            'payment_method' => $this->payment_method,
            'total_usd'      => $this->total_usd,
            'hold_expires_at'=> $this->hold_expires_at?->toISOString(),
            'rooms'          => ReservationRoomResource::collection($this->whenLoaded('rooms')),
            'guest'          => new GuestResource($this->whenLoaded('guest')),
            'promo_code'     => $this->whenLoaded('promoCode', fn () => $this->promoCode?->code),
        ];
    }
}
