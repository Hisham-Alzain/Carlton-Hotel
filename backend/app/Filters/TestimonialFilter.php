<?php

namespace App\Filters;

class TestimonialFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'author_name' => ['eq', 'like', 'in'],
        'rating'      => ['eq', 'gte', 'lte'],
    ];

    protected array $casts = [
        'rating' => 'int',
    ];

    protected array $searchable = ['author_name'];

    protected array $translatable = ['quote', 'author_title'];

    protected array $sortable = ['sort_order', 'rating', 'created_at', 'updated_at'];
}
