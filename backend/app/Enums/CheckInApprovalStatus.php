<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CheckInApprovalStatus: string
{
    use HasValues;

    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** Decisions staff may submit; `pending` is the initial state only. */
    public static function decisions(): array
    {
        return [self::APPROVED, self::REJECTED];
    }
}
