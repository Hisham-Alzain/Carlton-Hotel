<?php

namespace App\Filters;

class RoomTypeFilter extends CmsContentFilter
{
    protected array $translatable = ['name', 'description'];

    protected array $sortable = ['sort_order', 'base_price_usd', 'created_at', 'updated_at'];
}
