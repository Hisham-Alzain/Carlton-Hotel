<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TicketStatus: string
{
    use HasValues;

    case OPEN     = 'open';
    case ASSIGNED = 'assigned';
    case RESOLVED = 'resolved';
    case CLOSED   = 'closed';

    /** Statuses that keep a ticket on the operations queue. */
    public static function active(): array
    {
        return [self::OPEN, self::ASSIGNED];
    }

    /**
     * Advisory list for the dashboard's buttons (Phase 6, D-14): every other
     * value, in declaration order. The server enforces no transition table for
     * a ticket — the operations queue accepts any value of this enum (unchanged
     * contract) — so this is simply "every other value it accepts".
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case !== $this));
    }
}
