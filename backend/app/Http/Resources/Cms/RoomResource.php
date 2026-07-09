<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class RoomResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'number'      => $this->number,
            'floor'       => $this->floor,
            'status'      => $this->status,
            'is_active'   => $this->is_active,
            'room_type'   => new RoomTypeResource($this->whenLoaded('roomType')),
            'images'      => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
