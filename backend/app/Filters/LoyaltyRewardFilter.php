<?php

namespace App\Filters;

class LoyaltyRewardFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'type' => ['eq', 'in'],
    ];

    /** `name` is the only searchable text and it is translated. */
    protected array $searchable = [];

    protected array $translatable = ['name'];

    protected array $sortable = ['sort_order', 'points_cost', 'created_at', 'updated_at'];
}
