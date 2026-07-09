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
            'title'       => $this->getTranslations('title'),
            'description' => $this->getTranslations('description'),
            'terms'       => $this->getTranslations('terms'),
            'valid_from'  => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
            'images'      => MediaResource::collection($this->whenLoaded('images')),
        ];
    }
}
