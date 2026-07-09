<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class EventSpaceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'name'        => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            'capacity'    => $this->capacity,
            'location'    => $this->getTranslations('location'),
            'amenities'   => $this->getTranslations('amenities'),
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
            'images'      => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
