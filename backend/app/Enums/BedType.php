<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Bed configurations a room type can offer.
 *
 * A room type carries a *list* of these (`room_types.bed_types`), not a single
 * value — a suite may be sold as "1 king OR 2 twins".
 *
 * `EXTRA` is an inventory-only bed (a room type can offer it) and is rejected
 * as a guest preference (Phase 4, D-08).
 */
enum BedType: string
{
    use HasValues;

    case KING   = 'king';
    case QUEEN  = 'queen';
    case DOUBLE = 'double';
    case TWIN   = 'twin';
    case SINGLE = 'single';
    case EXTRA  = 'extra';
}
