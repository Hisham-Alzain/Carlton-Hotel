<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class GalleryItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            // Whole locale map, not the negotiated locale: the website switches
            // language client-side off a single fetch.
            'caption'       => $this->getTranslations('caption'),
            'is_active'     => $this->is_active,
            'sort_order'    => $this->sort_order,
            // The slug is what the site's chips filter on, so it is surfaced flat
            // as well as inside the nested chip.
            'category_slug' => $this->whenLoaded('category', fn () => $this->category?->slug),
            'category'      => new GalleryCategoryResource($this->whenLoaded('category')),
            'image'         => $this->whenLoaded('images', fn () => $this->images->first()?->url),
            'images'        => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
