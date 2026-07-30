<?php

namespace App\Filters;

use App\Base\BaseFilter;

/**
 * The moderation queue's filters for `GET /cms/reviews`.
 *
 * `BaseFilter` rather than `CmsContentFilter`: a review has no `is_active`
 * column, and the shared parent merges one in — a whitelisted param pointing at
 * a column that does not exist is a 500 waiting for the first client to send it.
 *
 * The published flag is the whole point of the screen, so it goes through the
 * same DSL as every other list: `?is_published=` (the "Status: All" option of a
 * select) means no filter, `?is_published=trve` is a 422, and a moderator's
 * `?per_page=` is honoured by the service.
 *
 * `comment` is not searchable and `guest` is not filterable here. Both are real
 * gaps, not oversights: `?search=` would need the guest join, and adding it
 * without an index on the join column is how a moderation screen starts timing
 * out. Left for whoever needs it, with the index.
 */
class ReviewFilter extends BaseFilter
{
    protected array $safeParms = [
        'is_published'     => ['eq', 'in'],
        'is_verified_stay' => ['eq', 'in'],
        'rating'           => ['eq', 'gte', 'lte', 'in'],
    ];

    protected array $casts = [
        'is_published'     => 'bool',
        'is_verified_stay' => 'bool',
        'rating'           => 'int',
    ];

    protected array $sortable = ['rating', 'created_at', 'updated_at'];
}
