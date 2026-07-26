<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum NotificationType: string
{
    use HasValues;

    case WELCOME        = 'welcome';
    case ROOM_READY     = 'room_ready';
    case INQUIRY_ROUTED = 'inquiry_routed';
}
