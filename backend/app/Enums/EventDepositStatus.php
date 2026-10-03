<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Current deposit state of an event inquiry over the `payments` ledger
 * (Phase 8, D-05). Written only by `RecordEventDepositAction`. Room is left
 * for `refunded` / `forfeited` (deferred).
 */
enum EventDepositStatus: string
{
    use HasValues;

    case UNPAID = 'unpaid';
    case PAID   = 'paid';
}
