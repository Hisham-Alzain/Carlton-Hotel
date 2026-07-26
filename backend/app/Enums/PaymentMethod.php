<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentMethod: string
{
    use HasValues;

    case CASH       = 'cash';
    case ON_ARRIVAL = 'on_arrival';
}
