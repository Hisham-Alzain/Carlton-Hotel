<?php

namespace App\Filters;

use App\Base\BaseFilter;

/**
 * Phase 10 (Q16): the guest and staff loyalty ledger list. `points` is cast to
 * int so a typo (`points[gte]=abc`) answers a localized 422 instead of
 * comparing text; the guest scope is applied by the service, never by a param.
 */
class LoyaltyLedgerFilter extends BaseFilter
{
    protected array $safeParms = [
        'type' => ['eq', 'in'],
        'source' => ['eq', 'in'],
        'occurred_at' => ['gte', 'lte'],
        'points' => ['gte', 'lte'],
    ];

    protected array $casts = ['points' => 'int'];

    protected array $sortable = ['occurred_at'];
}
