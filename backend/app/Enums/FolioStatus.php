<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum FolioStatus: string
{
    use HasValues;

    case OPEN    = 'open';
    case SETTLED = 'settled';
}
