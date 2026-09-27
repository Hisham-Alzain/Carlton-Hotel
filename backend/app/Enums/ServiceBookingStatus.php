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

    /**
     * The staff transition table (Phase 6, D-22), enforced by
     * UpdateServiceBookingStatusAction: pending → confirmed|cancelled,
     * confirmed → completed|cancelled; cancelled and completed are terminal.
     * No state lists itself, so a same-state change is rejected.
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::PENDING   => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::COMPLETED, self::CANCELLED],
            self::CANCELLED, self::COMPLETED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTargets(), true);
    }
}
