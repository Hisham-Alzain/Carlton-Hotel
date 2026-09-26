<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
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
            // Only the check-out response loads the folio (D-14).
            'folio'          => $this->whenLoaded('folio', fn () => $this->folio ? [
                'uuid'      => $this->folio->uuid,
                'status'    => $this->folio->status,
                'total_usd' => $this->folio->total_usd,
            ] : null),
        ];
    }
}
