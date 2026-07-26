<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class AmenityResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'slug'       => $this->slug,
            'name'       => $this->getTranslations('name'),
            'icon'       => $this->icon,
            'is_active'  => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
