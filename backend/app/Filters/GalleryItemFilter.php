<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class GalleryItemFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'gallery_category_id' => ['eq', 'in'],
    ];

    protected array $casts = [
        'gallery_category_id' => 'int',
    ];

    /** The caption is the only text here, and it is translated. */
    protected array $searchable = [];

    protected array $translatable = ['caption'];

    protected array $sortable = ['sort_order', 'created_at', 'updated_at'];

    /**
     * `?category=rooms` narrows the CMS list by the chip's slug. The list screen
     * shows slugs, not internal ids, so filtering by the autoincrement id alone
     * would make the one filter an editor actually wants unreachable — but the id
     * column stays whitelisted for the dashboard, which already holds it.
     */
    public function apply(Builder $query): Builder
    {
        $slug = $this->params['category'] ?? null;

        if (is_string($slug) && trim($slug) !== '') {
            $query->whereHas('category', fn (Builder $inner) => $inner->where('slug', trim($slug)));
        }

        return parent::apply($query);
    }
}
