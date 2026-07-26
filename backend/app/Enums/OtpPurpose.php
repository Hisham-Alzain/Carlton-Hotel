<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum OtpPurpose: string
{
    use HasValues;

    case LOGIN                = 'login';
    case REGISTER             = 'register';
    case BOOKING_LINK         = 'booking_link';
    case BOOKING_VERIFICATION = 'booking_verification';
}
