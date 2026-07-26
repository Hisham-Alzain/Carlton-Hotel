<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RoomStatus: string
{
    use HasValues;

    case AVAILABLE   = 'available';
    case OCCUPIED    = 'occupied';
    case MAINTENANCE = 'maintenance';
}
