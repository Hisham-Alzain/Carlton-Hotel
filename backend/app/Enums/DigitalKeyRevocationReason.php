<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Why a digital key stopped working (Phase 4, D-11). The values are stored in
 * `reservations.digital_key_revoked_reason` (string(20)).
 */
enum DigitalKeyRevocationReason: string
{
    use HasValues;

    case CHECKED_OUT = 'checked_out';
    case CANCELLED   = 'cancelled';
    case REJECTED    = 'rejected';
    case EXPIRED     = 'expired';
}
