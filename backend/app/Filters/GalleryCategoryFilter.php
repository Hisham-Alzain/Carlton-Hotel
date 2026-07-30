<?php

namespace App\Filters;

class GalleryCategoryFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'slug' => ['eq', 'like', 'in'],
    ];

    protected array $searchable = ['slug'];

    protected array $translatable = ['name'];

    protected array $sortable = ['sort_order', 'created_at', 'updated_at'];
}
