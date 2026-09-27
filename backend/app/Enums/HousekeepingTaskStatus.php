<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Lifecycle of a housekeeping task (Phase 6, D-05). */
enum HousekeepingTaskStatus: string
{
    use HasValues;

    case PENDING     = 'pending';
    case ASSIGNED    = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case DONE        = 'done';
    case CANCELLED   = 'cancelled';

    /**
     * The D-05 transition table. `done` and `cancelled` are terminal; no state
     * lists itself, so a same-state change is rejected.
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::PENDING     => [self::ASSIGNED, self::IN_PROGRESS, self::CANCELLED],
            self::ASSIGNED    => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::DONE, self::CANCELLED],
            self::DONE, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTargets(), true);
    }

    /**
     * Statuses of a task that still needs work.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::PENDING, self::ASSIGNED, self::IN_PROGRESS];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }
}
