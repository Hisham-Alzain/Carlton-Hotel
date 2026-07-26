<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PricingScope: string
{
    use HasValues;

    case SEASONAL = 'seasonal';
    case WEEKEND  = 'weekend';
    case HOLIDAY  = 'holiday';
}
