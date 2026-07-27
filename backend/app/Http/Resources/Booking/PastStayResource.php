<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class PastStayResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $stayRoom = $this->whenLoaded('rooms', fn () => $this->rooms->first());
        $folio    = $this->whenLoaded('folio', fn () => $this->folio);

        return [
            'uuid'         => $this->uuid,
            'booking_code' => $this->booking_code,
            'room_name'    => $stayRoom?->roomType?->getTranslations('name'),
            'total_nights' => $this->nights(),
            'check_in'     => $this->check_in?->toDateString(),
            'check_out'    => $this->check_out?->toDateString(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            // The folio total once one exists — it is frozen at settlement and
            // may differ from the room-only reservation total.
            'total_charge_usd' => $folio?->total_usd ?? $this->total_usd,
            // Raw status: there is no `complete` state server-side. The app
            // labels checked_out as "Completed"; renaming here would break the
            // published website and admin contracts.
            'status'       => $this->status,
            'has_receipt'  => $folio !== null,
            // Powers "book again": the app deep-links into the existing quote +
            // POST /reservations flow pre-filled with this room type, because a
            // new booking needs fresh dates, availability and price.
            'room_type_uuid' => $stayRoom?->roomType?->uuid,
        ];
    }
}
