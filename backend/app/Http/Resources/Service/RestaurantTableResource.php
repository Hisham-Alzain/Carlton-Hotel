<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class RestaurantTableResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'dining_venue_uuid' => $this->whenLoaded('diningVenue', fn () => $this->diningVenue?->uuid),
            'table_number'      => $this->table_number,
            'capacity'          => $this->capacity,
            'is_active'         => $this->is_active,
        ];
    }
}
