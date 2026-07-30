<?php

namespace App\Filters;

class FaqFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'category' => ['eq', 'like', 'in'],
    ];

    /** `question` and `answer` are the only text here, and both are translated. */
    protected array $searchable = [];

    protected array $translatable = ['question', 'answer'];

    protected array $sortable = ['sort_order', 'created_at', 'updated_at'];
}
