<?php

namespace App\Filters;

class PoolCabanaFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'capacity' => ['eq', 'gte', 'lte'],
    ];

    protected array $casts = [
        'capacity' => 'int',
    ];

    protected array $translatable = ['name'];

    protected array $sortable = ['capacity', 'price_usd', 'created_at', 'updated_at'];
}
