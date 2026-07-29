<?php

namespace App\Filters;

class ServiceCategoryFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'code'       => ['eq', 'like', 'in'],
        'kind'       => ['eq', 'in'],
        'department' => ['eq', 'in'],
    ];

    protected array $searchable = ['code'];

    protected array $translatable = ['name', 'description'];
}
