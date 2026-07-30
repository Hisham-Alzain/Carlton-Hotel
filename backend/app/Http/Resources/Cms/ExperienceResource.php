<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ExperienceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'slug'             => $this->slug,
            // Whole locale maps, not the negotiated locale: the website
            // switches language client-side off a single fetch.
            'title'            => $this->getTranslations('title'),
            'description'      => $this->getTranslations('description'),
            'category'         => $this->category,
            'duration_minutes' => $this->duration_minutes,
            'price_usd'        => $this->price_usd,
            'is_active'        => $this->is_active,
            'sort_order'       => $this->sort_order,
            // The card grid needs one hero image; `images` carries the rest.
            'image'            => $this->whenLoaded('images', fn () => $this->images->first()?->url),
            'images'           => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
