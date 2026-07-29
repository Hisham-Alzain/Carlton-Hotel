<?php

namespace App\Filters;

class SpaServiceFilter extends CmsContentFilter
{
    protected array $translatable = ['name'];

    protected array $sortable = ['duration_minutes', 'price_usd', 'created_at', 'updated_at'];
}
