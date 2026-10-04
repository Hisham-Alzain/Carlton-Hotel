<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Phase 10 (Q16): whether a reservation's loyalty discount is still in effect. */
enum LoyaltyApplicationStatus: string
{
    use HasValues;

    case APPLIED = 'applied';
    case REVERSED = 'reversed';
}
