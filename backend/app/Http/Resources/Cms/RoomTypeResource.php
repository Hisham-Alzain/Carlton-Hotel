<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class RoomTypeResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'            => $this->uuid,
            'name'            => $this->getTranslations('name'),
            'description'     => $this->getTranslations('description'),
            'amenities'       => $this->amenities,
            'base_occupancy'  => $this->base_occupancy,
            'max_occupancy'   => $this->max_occupancy,
            'size_sqm'        => $this->size_sqm,
            'base_price_usd'  => $this->base_price_usd,
            'is_active'       => $this->is_active,
            'sort_order'      => $this->sort_order,
            'images'          => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
