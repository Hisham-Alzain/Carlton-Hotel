<?php

namespace App\Http\Resources\Currency;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/** One history entry (Phase 9.1, D-16). */
class ExchangeRateResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'currency' => $this->currency,
            'rate' => $this->rate,
            'note' => $this->note,
            'set_by' => $this->whenLoaded('setBy', fn () => $this->setBy
                ? ['uuid' => $this->setBy->uuid, 'name' => $this->setBy->name]
                : null),
            'created_at' => $this->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
