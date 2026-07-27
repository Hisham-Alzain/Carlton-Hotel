<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class MenuCategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            // Stable filter key the app sends back as ?type= — display names can
            // be renamed without breaking the client.
            'slug'              => $this->slug,
            'dining_venue_uuid' => $this->whenLoaded('venue', fn () => $this->venue?->uuid),
            'name'              => $this->getTranslations('name'),
            'sort_order'        => $this->sort_order,
            'is_active'         => $this->is_active,
            'items'             => MenuItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
