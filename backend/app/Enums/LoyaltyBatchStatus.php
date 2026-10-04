<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Phase 10 (Q3, Q16): lifecycle of an earn batch. `expired` and `reversed`
 * batches never revive; `depleted` is fully spent but may take a refund back
 * while its expiry is still in the future.
 */
enum LoyaltyBatchStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case DEPLETED = 'depleted';
    case EXPIRED = 'expired';
    case REVERSED = 'reversed';
}
