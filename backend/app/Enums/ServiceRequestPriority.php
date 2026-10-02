<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ServiceRequestPriority: string
{
    use HasValues;

    case LOW    = 'low';
    case NORMAL = 'normal';
    case HIGH   = 'high';

    /**
     * Tickets store priority as a 1-3 integer scale; the ops queue merges both
     * models, so this maps the integer onto the shared vocabulary.
     */
    public static function fromTicketScale(int $priority): self
    {
        return match (true) {
            $priority <= 1 => self::LOW,
            $priority >= 3 => self::HIGH,
            default        => self::NORMAL,
        };
    }

    /**
     * Inverse of fromTicketScale() (Phase 7, D-12): ticket bodies and filters
     * take the label, the `tickets.priority` column stores 1-3.
     */
    public function toTicketScale(): int
    {
        return match ($this) {
            self::LOW    => 1,
            self::NORMAL => 2,
            self::HIGH   => 3,
        };
    }
}
