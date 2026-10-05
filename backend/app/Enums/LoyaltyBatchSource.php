<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Phase 10 (Q10, Q16): where a batch of points came from. Dining and spa
 * charges collapse to `service`. Also the snapshot `source` on ledger entries.
 */
enum LoyaltyBatchSource: string
{
    use HasValues;

    case STAY = 'stay';
    case SERVICE = 'service';
    case MANUAL = 'manual';
    case REFUND = 'refund';

    /** Localized display name (`custom.loyalty.sources.<value>`); the value itself never translates. */
    public function label(): string
    {
        return __('custom.loyalty.sources.'.$this->value);
    }
}
