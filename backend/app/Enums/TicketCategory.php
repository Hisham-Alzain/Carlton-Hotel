<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TicketCategory: string
{
    use HasValues;

    case INQUIRY      = 'inquiry';
    case COMPLAINT    = 'complaint';
    case BOOKING_HELP = 'booking_help';
    case MAINTENANCE  = 'maintenance';
    case OTHER        = 'other';

    /** Department this category routes to, or null when it needs manual triage. */
    public function department(): ?Department
    {
        return match ($this) {
            self::COMPLAINT    => Department::CONCIERGE,
            self::MAINTENANCE  => Department::HOUSEKEEPING,
            self::BOOKING_HELP => Department::RECEPTION,
            default            => null,
        };
    }
}
