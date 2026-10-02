<?php

namespace App\Http\Resources\Tickets;

use App\Base\BaseResource;
use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Support\FolioLedger;
use Illuminate\Http\Request;

/**
 * A support ticket (Phase 7, D-13). Every relation and total is loaded by
 * TicketService — no queries here.
 *
 * `folio_credit_total_usd` sums linked folio credits (absolute values; the
 * only ledger-backed recovery type) and `recorded_value_usd` sums every
 * recovery's recorded value, any type (council A7). `actions`,
 * `actions_truncated` and `latest_escalation` appear on the detail only;
 * `latest_escalation` is read from the loaded timeline (A8, PR-2), so it is
 * null when the newest 200 rows hold no escalation.
 */
class TicketResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $withActions = $this->relationLoaded('actions');

        return [
            'uuid'                   => $this->uuid,
            'subject'                => $this->subject,
            'description'            => $this->description,
            'category'               => $this->category?->value,
            'status'                 => $this->status->value,
            'priority'               => $this->priorityLabel()->value,
            'department'             => $this->department?->value,
            'source'                 => $this->source?->value,
            'escalation_level'       => (int) $this->escalation_level,
            'allowed_statuses'       => array_map(static fn (TicketStatus $s) => $s->value, $this->status->allowedTargets()),
            'guest'                  => $this->linked('guest', fn ($g) => ['uuid' => $g->uuid, 'name' => $g->name]),
            'reservation'            => $this->linked('reservation', fn ($r) => ['uuid' => $r->uuid, 'booking_code' => $r->booking_code]),
            'room'                   => $this->linked('room', fn ($r) => ['uuid' => $r->uuid, 'number' => $r->number]),
            'conversation_uuid'      => $this->getAttribute('conversation_uuid'),
            'assigned_user'          => $this->linked('assignedUser', fn ($u) => ['uuid' => $u->uuid, 'name' => $u->name]),
            'created_by'             => $this->linked('createdBy', fn ($u) => ['uuid' => $u->uuid, 'name' => $u->name]),
            'folio_credit_total_usd' => FolioLedger::fromNumeric($this->getAttribute('folio_credit_total_usd') ?? 0),
            'recorded_value_usd'     => FolioLedger::fromNumeric($this->getAttribute('recorded_value_usd') ?? 0),
            'resolved_at'            => $this->resolved_at?->toIso8601String(),
            'closed_at'              => $this->closed_at?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
            $this->mergeWhen($withActions, fn () => [
                'actions'           => TicketActionResource::collection($this->actions)->resolve(),
                'actions_truncated' => $this->resource->actionsTruncated,
                'latest_escalation' => $this->latestEscalation(),
            ]),
        ];
    }

    private function linked(string $relation, callable $shape): ?array
    {
        $model = $this->relationLoaded($relation) ? $this->getRelation($relation) : null;

        return $model ? $shape($model) : null;
    }

    private function latestEscalation(): ?array
    {
        $escalation = $this->actions->last(fn ($a) => $a->type === TicketActionType::ESCALATION);

        if ($escalation === null) {
            return null;
        }

        $target = $escalation->relationLoaded('targetUser') ? $escalation->targetUser : null;

        return [
            'level'       => $escalation->meta['level'] ?? null,
            'target_user' => $target ? ['uuid' => $target->uuid, 'name' => $target->name] : null,
            'reason'      => $escalation->body,
            'created_at'  => $escalation->created_at?->toIso8601String(),
        ];
    }
}
