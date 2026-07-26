<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DevicePlatform: string
{
    use HasValues;

    case IOS     = 'ios';
    case ANDROID = 'android';
    case WEB     = 'web';
}
