<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class JournalPostResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'slug'         => $this->slug,
            // Whole locale maps, not the negotiated locale: the website
            // switches language client-side off a single fetch.
            'title'        => $this->getTranslations('title'),
            'excerpt'      => $this->getTranslations('excerpt'),
            'body'         => $this->getTranslations('body'),
            'category'     => $this->getTranslations('category'),
            // Date-only string, no time and no timezone: this is the line
            // printed under the headline, and a `T00:00:00Z` would let a client
            // in a negative-offset zone render the previous day.
            'published_on' => $this->published_on?->toDateString(),
            'is_active'    => $this->is_active,
            'sort_order'   => $this->sort_order,
            'cover_image'  => $this->whenLoaded('images', fn () => $this->images->first()?->url),
            'images'       => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
