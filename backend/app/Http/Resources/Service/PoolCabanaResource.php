<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class PoolCabanaResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'      => $this->uuid,
            'name'      => $this->getTranslations('name'),
            'capacity'  => $this->capacity,
            'price_usd' => $this->price_usd,
            'is_active' => $this->is_active,
        ];
    }
}
