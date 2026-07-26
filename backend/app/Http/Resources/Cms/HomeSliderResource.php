<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class HomeSliderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'header_text'      => $this->getTranslations('header_text'),
            'location'         => $this->getTranslations('location'),
            'description_text' => $this->getTranslations('description_text'),
            'photo'            => $this->whenLoaded('images', fn () => $this->images->first()?->url),
            'is_active'        => $this->is_active,
            'sort_order'       => $this->sort_order,
        ];
    }
}
