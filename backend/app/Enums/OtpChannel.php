<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum OtpChannel: string
{
    use HasValues;

    case SMS      = 'sms';
    case WHATSAPP = 'whatsapp';
    case EMAIL    = 'email';
}
