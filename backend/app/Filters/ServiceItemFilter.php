<?php

namespace App\Filters;

class ServiceItemFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'is_default' => ['eq'],
    ];

    protected array $casts = [
        'is_default' => 'bool',
    ];

    protected array $translatable = ['name', 'description'];

    protected array $sortable = ['sort_order', 'price_usd', 'created_at', 'updated_at'];
}
