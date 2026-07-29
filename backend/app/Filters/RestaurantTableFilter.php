<?php

namespace App\Filters;

/** Tables carry no translatable text — `table_number` is the editor's handle. */
class RestaurantTableFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'capacity' => ['eq', 'gte', 'lte'],
    ];

    protected array $casts = [
        'capacity' => 'int',
    ];

    protected array $searchable = ['table_number'];

    protected array $sortable = ['table_number', 'capacity', 'created_at', 'updated_at'];
}
