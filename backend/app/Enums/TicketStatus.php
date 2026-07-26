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
}
