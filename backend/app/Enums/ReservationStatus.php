<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ReservationStatus: string
{
    use HasValues;

    case PENDING_VERIFICATION = 'pending_verification';
    case PENDING              = 'pending';
    case CONFIRMED            = 'confirmed';
    case CHECKED_IN           = 'checked_in';
    case CHECKED_OUT          = 'checked_out';
    case CANCELLED            = 'cancelled';

    /** Statuses a guest or staff member may still cancel from. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING_VERIFICATION, self::PENDING, self::CONFIRMED], true);
    }

    /** Statuses that still hold inventory and therefore block availability. */
    public static function blockingAvailability(): array
    {
        return [self::PENDING_VERIFICATION, self::PENDING, self::CONFIRMED, self::CHECKED_IN];
    }
}
