<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class MenuItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'menu_category_uuid'=> $this->whenLoaded('category', fn () => $this->category->uuid),
            'name'              => $this->getTranslations('name'),
            'description'       => $this->getTranslations('description'),
            'price_usd'         => $this->price_usd,
            'is_active'         => $this->is_active,
        ];
    }
}
