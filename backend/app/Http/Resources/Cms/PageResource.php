<?php

namespace App\Http\Resources\Cms;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class PageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'slug'        => $this->slug,
            'title'       => $this->getTranslations('title'),
            'content'     => $this->getTranslations('content'),
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
        ];
    }
}
