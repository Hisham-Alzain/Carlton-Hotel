<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventType: string
{
    use HasValues;

    case WEDDING        = 'wedding';
    case CORPORATE      = 'corporate';
    case CONFERENCE     = 'conference';
    case GALA           = 'gala';
    case BIRTHDAY       = 'birthday';
    case PRODUCT_LAUNCH = 'product_launch';
    case OTHER          = 'other';

    /** Business events go to sales; social events stay with the events team. */
    public function department(): Department
    {
        return match ($this) {
            self::CORPORATE, self::CONFERENCE, self::PRODUCT_LAUNCH => Department::SALES,
            default => Department::EVENTS,
        };
    }
}
