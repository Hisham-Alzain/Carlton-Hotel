<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class FacilityResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'name'        => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            'location'    => $this->getTranslations('location'),
            'hours'       => $this->getTranslations('hours'),
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
            'images'      => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
