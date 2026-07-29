<?php

namespace App\Filters;

/** Menu items order by insertion, not `sort_order` — the column does not exist. */
class MenuItemFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'is_vegan' => ['eq'],
    ];

    protected array $casts = [
        'is_vegan' => 'bool',
    ];

    protected array $translatable = ['name', 'description'];

    protected array $sortable = ['price_usd', 'created_at', 'updated_at'];
}
