<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * A guest's stay status on the hotel-local date (Phase 4, D-02), derived from
 * their reservations and never stored. As a row value it follows the
 * precedence departing > in_house > arriving > upcoming > past > none; as a
 * directory filter each value is its own (non-exclusive) predicate.
 */
enum GuestStayStatus: string
{
    use HasValues;

    case IN_HOUSE  = 'in_house';
    case DEPARTING = 'departing';
    case ARRIVING  = 'arriving';
    case UPCOMING  = 'upcoming';
    case PAST      = 'past';
    case NONE      = 'none';
}
