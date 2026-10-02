<?php

namespace App\Http\Resources\Tickets;

use App\Base\BaseResource;
use App\Support\FolioLedger;
use Illuminate\Http\Request;

/**
 * One ticket timeline row (Phase 7, D-13). Relations (actor, target, recovery)
 * are pre-set by TicketService — no queries here. The reserved chat-message
 * link (TICKET-08) is never emitted.
 */
class TicketActionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $recovery = $this->relationLoaded('recovery') ? $this->recovery : null;

        return [
            'uuid'        => $this->uuid,
            'type'        => $this->type->value,
            'body'        => $this->body,
            'from_status' => $this->from_status,
            'to_status'   => $this->to_status,
            'actor'       => $this->person('user'),
            'target_user' => $this->person('targetUser'),
            'recovery'    => $recovery ? [
                'uuid'            => $recovery->uuid,
                'type'            => $recovery->type->value,
                'amount_usd'      => $recovery->amount_usd !== null ? FolioLedger::fromNumeric($recovery->amount_usd) : null,
                'description'     => $recovery->description,
                'folio_item_uuid' => $recovery->getAttribute('folio_item_uuid'),
            ] : null,
            'meta'        => $this->meta,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }

    private function person(string $relation): ?array
    {
        $user = $this->relationLoaded($relation) ? $this->getRelation($relation) : null;

        return $user ? ['uuid' => $user->uuid, 'name' => $user->name] : null;
    }
}
