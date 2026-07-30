<?php

namespace App\Filters;

class ExperienceFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'slug'             => ['eq', 'in'],
        'category'         => ['eq', 'like', 'in'],
        'duration_minutes' => ['eq', 'gte', 'lte'],
        // No cast: `price_usd` is DECIMAL, and casting it to int here would
        // silently floor `?price_usd[lte]=99.99` to 99.
        'price_usd'        => ['eq', 'gte', 'lte'],
    ];

    protected array $casts = [
        'duration_minutes' => 'int',
    ];

    /** `slug` is the only untranslated text an editor searches by. */
    protected array $searchable = ['slug'];

    protected array $translatable = ['title', 'description'];

    protected array $sortable = ['sort_order', 'duration_minutes', 'price_usd', 'created_at', 'updated_at'];
}
