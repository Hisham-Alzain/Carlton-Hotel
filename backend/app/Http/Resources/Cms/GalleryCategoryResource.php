<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class GalleryCategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'slug'       => $this->slug,
            // Whole locale map, not the negotiated locale: the website switches
            // language client-side off a single fetch.
            'name'       => $this->getTranslations('name'),
            'is_active'  => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
