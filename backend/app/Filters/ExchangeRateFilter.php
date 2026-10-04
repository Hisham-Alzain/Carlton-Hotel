<?php

namespace App\Filters;

use App\Base\BaseFilter;

/** Rate history (Phase 9.1, D-15): `?currency=SYP` or `?currency[in]=SYP,TRY`. */
class ExchangeRateFilter extends BaseFilter
{
    protected array $safeParms = [
        'currency' => ['eq', 'in'],
    ];
}
