<?php

namespace App\Filters;

class PromotionFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'valid_from'  => ['gte', 'lte'],
        'valid_until' => ['gte', 'lte'],
    ];

    protected array $translatable = ['title', 'description'];

    protected array $sortable = ['sort_order', 'valid_from', 'valid_until', 'created_at', 'updated_at'];
}
