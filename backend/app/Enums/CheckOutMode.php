<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * How a check-out treats the folio gate (D-06..D-08).
 *
 * NONE          — the folio must be settled.
 * STAFF_FORCE   — a folios.settle holder overrides an open folio with a logged reason.
 * GUEST_EXPRESS — the guest's express checkout: unguarded by the folio balance
 *                 until an online gateway exists, logged as a guest marker.
 */
enum CheckOutMode: string
{
    use HasValues;

    case NONE          = 'none';
    case STAFF_FORCE   = 'staff_force';
    case GUEST_EXPRESS = 'guest_express';
}
