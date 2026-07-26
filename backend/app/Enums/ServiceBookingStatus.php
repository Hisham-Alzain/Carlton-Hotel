<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ServiceBookingStatus: string
{
    use HasValues;

    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}
