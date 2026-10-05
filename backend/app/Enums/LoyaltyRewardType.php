<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Phase 10 (Q12, Q16): the kind of a catalog reward. Also the snapshot `type`
 * stored on the voucher it produces.
 */
enum LoyaltyRewardType: string
{
    use HasValues;

    case DISCOUNT_VOUCHER = 'discount_voucher';
    case FREE_NIGHT = 'free_night';
    case ROOM_UPGRADE = 'room_upgrade';

    /** Display name in the request locale; the API value itself never translates. */
    public function label(): string
    {
        return __('custom.loyalty.reward_types.'.$this->value);
    }
}
