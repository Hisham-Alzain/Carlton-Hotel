<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ServiceItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'name'             => $this->getTranslations('name'),
            'description'      => $this->getTranslations('description'),
            // Minutes, not a formatted string — the client localizes it.
            'expected_minutes' => $this->expected_minutes,
            // Null means complimentary; a value is charged to the folio.
            'price_usd'        => $this->price_usd,
        ];
    }
}
