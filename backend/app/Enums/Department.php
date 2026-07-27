<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Every department a request, ticket or inquiry can be routed to.
 *
 * Previously spread across ServiceRequest::DEPARTMENT_*, Ticket::DEPARTMENT_*
 * and EventInquiry::DEPARTMENT_*, which all wrote the same column vocabulary.
 */
enum Department: string
{
    use HasValues;

    case KITCHEN      = 'kitchen';
    case HOUSEKEEPING = 'housekeeping';
    case CONCIERGE    = 'concierge';
    case RECEPTION    = 'reception';
    case EVENTS       = 'events';
    case SALES        = 'sales';
    case MAINTENANCE  = 'maintenance';

    /**
     * Service request `type` is an open-ended string, so routing stays a lookup
     * with a concierge fallback rather than an enum-to-enum map.
     *
     * Catalog-placed requests skip this: they carry the department declared on
     * their service category. This map only serves legacy free-string callers.
     */
    public static function forServiceType(string $type): self
    {
        return match ($type) {
            'room_service' => self::KITCHEN,
            'housekeeping' => self::HOUSEKEEPING,
            'laundry'      => self::HOUSEKEEPING,
            'maintenance'  => self::MAINTENANCE,
            default        => self::CONCIERGE,
        };
    }
}
