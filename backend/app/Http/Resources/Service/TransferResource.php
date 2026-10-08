<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class TransferResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $description = array_filter($this->getTranslations('description'), fn ($v) => $v !== null && $v !== '');

        return [
            'uuid'           => $this->uuid,
            'name'           => $this->getTranslations('name'),
            'description'    => $description === [] ? null : $this->getTranslations('description'),
            'max_passengers' => $this->max_passengers,
            'price_usd'      => $this->price_usd,
            'is_active'      => $this->is_active,
        ];
    }
}
