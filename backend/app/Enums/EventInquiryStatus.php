<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventInquiryStatus: string
{
    use HasValues;

    case NEW       = 'new';
    case IN_REVIEW = 'in_review';
    case QUOTED    = 'quoted';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    /**
     * Statuses staff may set directly. `new` is assigned on submission only,
     * so it is excluded from the update endpoint's allowed set.
     */
    public static function staffAssignable(): array
    {
        return [self::IN_REVIEW, self::QUOTED, self::CONFIRMED, self::CANCELLED];
    }
}
