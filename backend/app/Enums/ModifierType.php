<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Shared by PricingRule::modifier_type and PromoCode::type. */
enum ModifierType: string
{
    use HasValues;

    case PERCENTAGE = 'percentage';
    case FLAT       = 'flat';
}
