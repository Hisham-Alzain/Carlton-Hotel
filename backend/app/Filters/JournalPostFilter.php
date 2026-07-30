<?php

namespace App\Filters;

class JournalPostFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'slug'         => ['eq', 'like'],
        // Date-range narrowing for the CMS list screen only. This is an editor
        // browsing an archive, NOT the public visibility rule — the public
        // endpoints never touch `published_on` in a WHERE clause.
        'published_on' => ['eq', 'gte', 'lte'],
    ];

    protected array $searchable = ['slug'];

    /** `category` is translatable copy, so the search box scans it as JSON. */
    protected array $translatable = ['title', 'excerpt', 'body', 'category'];

    protected array $sortable = ['published_on', 'sort_order', 'created_at', 'updated_at'];
}
