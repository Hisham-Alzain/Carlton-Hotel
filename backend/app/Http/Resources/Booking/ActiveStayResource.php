<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ActiveStayResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $stayRoom = $this->whenLoaded('rooms', fn () => $this->rooms->first());

        return [
            'uuid'         => $this->uuid,
            'booking_code' => $this->booking_code,
            'status'       => $this->status,
            // Assigned at check-in, so non-null for an active stay.
            'room_number'  => $stayRoom?->room?->number,
            'room_name'    => $stayRoom?->roomType?->getTranslations('name'),
            'check_in'     => $this->check_in?->toDateString(),
            'check_out'    => $this->check_out?->toDateString(),
            // Null for stays that predate the checked_in_at column — the app
            // falls back to check_in.
            'checked_in_at'    => $this->checked_in_at?->toIso8601String(),
            'nights'           => $this->nights(),
            'nights_remaining' => $this->nightsRemaining(),
            'dnd' => [
                'enabled' => $this->isDndActive(),
                'until'   => $this->isDndActive() ? $this->dnd_until->toIso8601String() : null,
            ],
            // Null until staff generate the folio.
            'folio_total_usd' => $this->whenLoaded('folio', fn () => $this->folio?->total_usd),
        ];
    }
}
