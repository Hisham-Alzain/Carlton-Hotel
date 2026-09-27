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

    /**
     * Advisory list for the dashboard's buttons (Phase 6, D-14): every other
     * value, in declaration order. The server enforces no transition table for
     * a request — the operations queue accepts any value of this enum (unchanged
     * contract) — so this is simply "every other value it accepts".
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case !== $this));
    }
}
