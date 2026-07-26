<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ReservationSource: string
{
    use HasValues;

    case DIRECT   = 'direct';
    case WALK_IN  = 'walk_in';
}
