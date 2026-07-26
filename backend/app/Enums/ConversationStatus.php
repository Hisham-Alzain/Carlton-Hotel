<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ConversationStatus: string
{
    use HasValues;

    case OPEN   = 'open';
    case CLOSED = 'closed';
}
