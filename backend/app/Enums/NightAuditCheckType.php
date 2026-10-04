<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The five night-audit check categories (Phase 9, D-06/D-10), in the fixed
 * order every audit response lists them.
 *
 * Only the two date-scoped categories are **blocking**: a non-empty one also
 * gets a blocker that must be resolved before close. The other three are
 * advisory current-state reviews (FOLIO-03: disputes never block).
 */
enum NightAuditCheckType: string
{
    use HasValues;

    case UNSETTLED_DEPARTURES       = 'unsettled_departures';
    case UNASSIGNED_ARRIVALS        = 'unassigned_arrivals';
    case DIRTY_ROOMS                = 'dirty_rooms';
    case OPEN_HIGH_PRIORITY_TICKETS = 'open_high_priority_tickets';
    case OPEN_FOLIO_DISPUTES        = 'open_folio_disputes';

    public function isBlocking(): bool
    {
        return match ($this) {
            self::UNSETTLED_DEPARTURES, self::UNASSIGNED_ARRIVALS => true,
            default                                               => false,
        };
    }

    public function label(): string
    {
        return __('custom.night_audit.checks.' . $this->value);
    }
}
