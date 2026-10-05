<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum NotificationType: string
{
    use HasValues;

    case WELCOME        = 'welcome';
    case ROOM_READY     = 'room_ready';
    case INQUIRY_ROUTED = 'inquiry_routed';
    // Phase 4 (D-14): the digital key is ready in the app. Never carries the code.
    case CHECK_IN_APPROVED = 'check_in_approved';
    // Phase 10 (LOY-10): loyalty points are about to expire. Carries only the point total and expiry instant.
    case LOYALTY_POINTS_EXPIRING = 'loyalty_points_expiring';
}
