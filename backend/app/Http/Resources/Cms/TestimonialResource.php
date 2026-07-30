<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class TestimonialResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'author_name'  => $this->author_name,
            // Whole locale maps, not the negotiated locale: the website
            // switches language client-side off a single fetch.
            'author_title' => $this->getTranslations('author_title'),
            'quote'        => $this->getTranslations('quote'),
            'rating'       => $this->rating,
            'is_active'    => $this->is_active,
            'sort_order'   => $this->sort_order,
            'avatar'       => $this->whenLoaded('images', fn () => $this->images->first()?->url),
            'images'       => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
