<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Phase 10 (Q16): lifecycle of a redeemed voucher. */
enum LoyaltyVoucherStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case USED = 'used';
    case EXPIRED = 'expired';
    case VOID = 'void';
}
