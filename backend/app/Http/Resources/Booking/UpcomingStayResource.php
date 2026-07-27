<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class UpcomingStayResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $stayRoom = $this->whenLoaded('rooms', fn () => $this->rooms->first());

        return [
            'uuid'         => $this->uuid,
            'booking_code' => $this->booking_code,
            'status'       => $this->status,
            // Populated from booking time — a specific room is reserved when the
            // booking is made, not at check-in.
            'room_number'  => $stayRoom?->room?->number,
            'room_name'    => $stayRoom?->roomType?->getTranslations('name'),
            'price_usd'    => $this->total_usd,
            'check_in'     => $this->check_in?->toDateString(),
            'check_out'    => $this->check_out?->toDateString(),
            'nights'       => $this->nights(),
            'is_cancellable' => $this->status->isCancellable(),
        ];
    }
}
