<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ServiceBookingStatus: string
{
    use HasValues;

    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    /**
     * Statuses that still hold a restaurant table for its seating window.
     * A cancelled or completed seating frees the table immediately.
     */
    public static function blockingSeating(): array
    {
        return [self::PENDING->value, self::CONFIRMED->value];
    }
}
