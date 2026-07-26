<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The outlook a room type is sold on ("City View", "Garden View", ...).
 *
 * Kept as an enum rather than free text so the mobile app can filter and
 * icon-map on it; the display string is a translation key, not the value.
 */
enum RoomView: string
{
    use HasValues;

    case CITY      = 'city';
    case GARDEN    = 'garden';
    case POOL      = 'pool';
    case COURTYARD = 'courtyard';
    case MOUNTAIN  = 'mountain';
    case INTERIOR  = 'interior';
}
