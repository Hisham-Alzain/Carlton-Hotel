<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TicketSource: string
{
    use HasValues;

    case CHATBOT = 'chatbot';
}
