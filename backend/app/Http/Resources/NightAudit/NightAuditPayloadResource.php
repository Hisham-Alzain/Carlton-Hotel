<?php

namespace App\Http\Resources\NightAudit;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * The night-audit envelope payload (Phase 9, D-13): the business-date state
 * plus the audit or null. Every audit endpoint answers with this shape.
 * Wraps `['state' => NightAuditState, 'audit' => ?NightAudit]`.
 */
class NightAuditPayloadResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $state = $this->resource['state'];
        $audit = $this->resource['audit'];

        return [
            'state' => [
                'current_business_date' => $state->current_business_date->toDateString(),
                'last_closed_date'      => $state->last_closed_date?->toDateString(),
            ],
            'audit' => $audit ? (new NightAuditResource($audit))->resolve($request) : null,
        ];
    }
}
