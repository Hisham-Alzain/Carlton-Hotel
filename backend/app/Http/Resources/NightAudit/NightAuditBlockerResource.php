<?php

namespace App\Http\Resources\NightAudit;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * One night-audit close blocker (Phase 9, D-06, D-13). Type, count and
 * evidence live on the check (`check_uuid`).
 */
class NightAuditBlockerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'check_uuid' => $this->whenLoaded('check', fn () => $this->check?->uuid),
            'type'       => $this->whenLoaded('check', fn () => $this->check?->type->value),
            'status'     => $this->status->value,
            'note'       => $this->note,
            'acted_by'   => $this->whenLoaded('actor', fn () => NightAuditResource::userRef($this->actor)),
            'acted_at'   => $this->acted_at?->toIso8601ZuluString(),
        ];
    }
}
