<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Housekeeping status of a room (Phase 2, D-01). Occupancy is not a status: it
 * is derived from checked-in reservations at read time and never stored.
 */
enum RoomStatus: string
{
    use HasValues;

    case AVAILABLE   = 'available';
    case DIRTY       = 'dirty';
    case MAINTENANCE = 'maintenance';

    /**
     * The D-04 transition table. Leaving maintenance always lands on `dirty`,
     * so the room is cleaned before it is sold. No state lists itself, so a
     * same-state change is rejected. Phase 6 (housekeeping tasks) reuses it.
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::AVAILABLE   => [self::DIRTY, self::MAINTENANCE],
            self::DIRTY       => [self::AVAILABLE, self::MAINTENANCE],
            self::MAINTENANCE => [self::DIRTY],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTargets(), true);
    }
}
