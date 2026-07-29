<?php

namespace App\Filters;

class MenuCategoryFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'slug' => ['eq', 'like', 'in'],
    ];

    protected array $searchable = ['slug'];

    protected array $translatable = ['name'];
}
