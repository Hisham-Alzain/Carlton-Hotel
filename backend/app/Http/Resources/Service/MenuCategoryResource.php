<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class MenuCategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'name'       => $this->getTranslations('name'),
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
            'items'      => MenuItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
