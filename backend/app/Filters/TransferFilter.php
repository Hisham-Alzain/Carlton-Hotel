<?php

namespace App\Filters;

class TransferFilter extends CmsContentFilter
{
    protected array $translatable = ['name'];

    protected array $sortable = ['price_usd', 'created_at', 'updated_at'];
}
