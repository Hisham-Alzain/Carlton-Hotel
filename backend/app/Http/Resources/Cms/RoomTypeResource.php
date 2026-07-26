<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class RoomTypeResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'name'               => $this->getTranslations('name'),
            'description'        => $this->getTranslations('description'),
            'view_type'          => $this->view_type,
            'bed_types'          => $this->bed_types ?? [],
            'base_occupancy'     => $this->base_occupancy,
            'max_occupancy'      => $this->max_occupancy,
            'size_sqm'           => $this->size_sqm,
            'base_price_usd'     => $this->base_price_usd,
            'cancellation_hours' => $this->cancellation_hours,
            'is_active'          => $this->is_active,
            'sort_order'         => $this->sort_order,
            // Room card thumbnail — the first image by sort_order.
            'banner'             => $this->whenLoaded('images', fn () => $this->images->first()?->url),
            'images'             => MediaResource::collection($this->whenLoaded('images')),
            'amenities'          => AmenityResource::collection($this->whenLoaded('amenityList')),
            'highlights'         => $this->whenLoaded(
                'amenityList',
                fn () => AmenityResource::collection($this->highlightAmenities()),
            ),
        ];
    }
}
