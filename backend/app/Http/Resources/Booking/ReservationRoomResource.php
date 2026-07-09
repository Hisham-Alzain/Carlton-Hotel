<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
use App\Http\Resources\Cms\RoomTypeResource;

class ReservationRoomResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'room_type'  => new RoomTypeResource($this->whenLoaded('roomType')),
            'room_uuid'  => $this->room?->uuid,
            'room_number'=> $this->room?->number,
            'price_usd'  => $this->price_usd,
        ];
    }
}
