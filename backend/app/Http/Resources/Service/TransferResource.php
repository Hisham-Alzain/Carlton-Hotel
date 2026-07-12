<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class TransferResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'      => $this->uuid,
            'name'      => $this->getTranslations('name'),
            'price_usd' => $this->price_usd,
            'is_active' => $this->is_active,
        ];
    }
}
