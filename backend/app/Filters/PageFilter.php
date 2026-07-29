<?php

namespace App\Filters;

class PageFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'slug' => ['eq', 'like', 'in'],
    ];

    protected array $searchable = ['slug'];

    protected array $translatable = ['title'];
}
