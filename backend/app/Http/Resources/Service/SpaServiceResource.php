<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class SpaServiceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'name'             => $this->getTranslations('name'),
            'duration_minutes' => $this->duration_minutes,
            'price_usd'        => $this->price_usd,
            'is_active'        => $this->is_active,
        ];
    }
}
