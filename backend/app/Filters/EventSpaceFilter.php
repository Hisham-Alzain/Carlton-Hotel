<?php

namespace App\Filters;

class EventSpaceFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'capacity' => ['eq', 'gte', 'lte'],
    ];

    protected array $casts = [
        'capacity' => 'int',
    ];

    protected array $translatable = ['name'];

    protected array $sortable = ['sort_order', 'capacity', 'created_at', 'updated_at'];
}
