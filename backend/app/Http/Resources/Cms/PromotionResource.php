<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class PromotionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            // Offer card mapping: title = package name, description = the copy
            // above the banner, secondary_description = the copy below it.
            'title'       => $this->getTranslations('title'),
            'description' => $this->getTranslations('description'),
            'secondary_description' => $this->getTranslations('secondary_description'),
            'terms'       => $this->getTranslations('terms'),
            'valid_from'  => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
            'banner'      => $this->whenLoaded('images', fn () => $this->images->first()?->url),
            'images'      => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
