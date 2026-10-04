<?php

namespace App\Http\Resources\NightAudit;

use App\Base\BaseResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * One night audit (Phase 9, D-13). Relations come from
 * `NightAuditService::payload()`; nothing here queries. Instants are UTC
 * ISO-8601 with a `Z`; the business date is a plain `Y-m-d`.
 */
class NightAuditResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'           => $this->uuid,
            'business_date'  => $this->business_date->toDateString(),
            'status'         => $this->status->value,
            'snapshot_basis' => $this->snapshot_basis,
            'evaluated_at'   => $this->evaluated_at?->toIso8601ZuluString(),
            'opened_by'      => $this->whenLoaded('opener', fn () => self::userRef($this->opener)),
            'closed_at'      => $this->closed_at?->toIso8601ZuluString(),
            'closed_by'      => $this->whenLoaded('closer', fn () => self::userRef($this->closer)),
            'readiness'      => $this->when(
                $this->relationLoaded('checks') && $this->relationLoaded('blockers'),
                fn () => $this->resource->readiness(),
            ),
            'checks'         => NightAuditCheckResource::collection($this->whenLoaded('checks')),
            'blockers'       => NightAuditBlockerResource::collection($this->whenLoaded('blockers')),
        ];
    }

    /** @return array{uuid: ?string, name: string}|null */
    public static function userRef(?User $user): ?array
    {
        return $user ? ['uuid' => $user->uuid, 'name' => $user->name] : null;
    }
}
