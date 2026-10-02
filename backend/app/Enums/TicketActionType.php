<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * One kind of `ticket_actions` timeline row (Phase 7, D-01).
 */
enum TicketActionType: string
{
    use HasValues;

    case CREATED       = 'created';
    case STATUS_CHANGE = 'status_change';
    case ASSIGNMENT    = 'assignment';
    case ESCALATION    = 'escalation';
    case REPLY         = 'reply';
    case RECOVERY      = 'recovery';

    /**
     * The only `meta` keys a row of this type may carry (council A8); any
     * other key is rejected by `TicketAction` rather than silently stored.
     * Money never goes in meta — it lives in `ticket_recoveries`.
     *
     * @return list<string>
     */
    public function allowedMetaKeys(): array
    {
        return match ($this) {
            self::ASSIGNMENT => ['claim'],
            self::ESCALATION => ['level', 'previous_assignee_uuid'],
            default          => [],
        };
    }
}
