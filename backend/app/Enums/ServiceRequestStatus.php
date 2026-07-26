<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ServiceRequestStatus: string
{
    use HasValues;

    case NEW         = 'new';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED   = 'completed';
    case CANCELLED   = 'cancelled';

    /** Statuses that keep a request on the operations queue. */
    public static function active(): array
    {
        return [self::NEW, self::IN_PROGRESS];
    }
}
