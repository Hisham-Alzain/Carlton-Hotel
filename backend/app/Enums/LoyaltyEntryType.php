<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Phase 10 (Q16): the kind of a loyalty ledger entry. The strings are an API
 * contract (guest and staff ledger `type`); additive changes only.
 */
enum LoyaltyEntryType: string
{
    use HasValues;

    case EARN = 'earn';
    case REDEEM = 'redeem';
    case EXPIRE = 'expire';
    case ADJUST = 'adjust';
    case CLAWBACK = 'clawback';
    case REFUND = 'refund';
}
