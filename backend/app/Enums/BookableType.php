<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Morph aliases a ServiceBooking can point at. */
enum BookableType: string
{
    use HasValues;

    case SPA_SERVICE      = 'spa_service';
    case RESTAURANT_TABLE = 'restaurant_table';
    case POOL_CABANA      = 'pool_cabana';
    case TRANSFER         = 'transfer';
}
