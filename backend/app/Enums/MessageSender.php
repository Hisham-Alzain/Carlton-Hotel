<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MessageSender: string
{
    use HasValues;

    case GUEST = 'guest';
    case STAFF = 'staff';
}
