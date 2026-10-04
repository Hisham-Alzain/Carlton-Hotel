<?php

namespace App\Http\Resources\NightAudit;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * One night-audit check (Phase 9, D-06, D-13): the snapshot (type, blocking,
 * issue_count, evidence) plus the attestation (status, note, actor, time).
 * `blocker_uuid` is set only for a non-empty blocking check.
 */
class NightAuditCheckResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'type'               => $this->type->value,
            'label'              => $this->type->label(),
            'blocking'           => $this->blocking,
            'status'             => $this->status->value,
            'issue_count'        => $this->issue_count,
            'evidence'           => $this->evidence,
            'evidence_truncated' => $this->evidence_truncated,
            'note'               => $this->note,
            'acted_by'           => $this->whenLoaded('actor', fn () => NightAuditResource::userRef($this->actor)),
            'acted_at'           => $this->acted_at?->toIso8601ZuluString(),
            'blocker_uuid'       => $this->whenLoaded('blocker', fn () => $this->blocker?->uuid),
        ];
    }
}
