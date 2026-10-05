<?php

namespace App\Filters;

use App\Base\BaseFilter;

/**
 * Phase 10 (LOY-14): a guest's voucher list. `points_spent` is cast to int so a
 * typo (`points_spent[gte]=abc`) answers a localized 422 instead of comparing
 * text; the guest scope is applied by the service, never by a param.
 */
class LoyaltyVoucherFilter extends BaseFilter
{
    protected array $safeParms = [
        'status' => ['eq', 'in'],
        'type' => ['eq', 'in'],
        'points_spent' => ['gte', 'lte'],
    ];

    protected array $casts = ['points_spent' => 'int'];

    protected array $sortable = ['created_at', 'expires_at'];
}
