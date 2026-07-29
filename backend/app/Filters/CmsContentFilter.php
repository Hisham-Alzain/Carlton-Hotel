<?php

namespace App\Filters;

use App\Base\BaseFilter;

/**
 * Shared shape of every CMS list screen: a published/draft toggle, a free-text
 * search box, and column sorting.
 *
 * Subclasses declare only what differs — which columns the search box scans
 * (`$searchable` for plain columns, `$translatable` for Spatie JSON columns),
 * plus any *extra* `$safeParms`. The `is_active` whitelist entry and its
 * boolean cast are merged in here so a subclass can never lose them by
 * redeclaring the property. `$sortable` is declared in full per entity —
 * entities without a `sort_order` column must not advertise it.
 */
abstract class CmsContentFilter extends BaseFilter
{
    protected array $sortable = ['sort_order', 'created_at', 'updated_at'];

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(array $params = [])
    {
        // Union, not array_merge: a subclass that deliberately widens
        // `is_active` (or recasts it) keeps its own definition.
        $this->safeParms = $this->safeParms + ['is_active' => ['eq', 'in']];
        $this->casts     = $this->casts + ['is_active' => 'bool'];

        parent::__construct($params);
    }
}
