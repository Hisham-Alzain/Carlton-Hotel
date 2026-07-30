<?php

namespace App\Filters;

use App\Base\BaseFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The media library's list screen: narrow by file type, by which entity an asset
 * is placed on, or to the assets nobody has placed yet.
 *
 * Extends `BaseFilter` rather than `CmsContentFilter` — `media` has no
 * `is_active` column, and inheriting one would advertise a filter that cannot
 * work.
 */
class MediaFilter extends BaseFilter
{
    protected array $safeParms = [
        'mime_type'     => ['eq', 'like', 'in'],
        'mediable_type' => ['eq', 'in'],
    ];

    protected array $searchable = ['file_name', 'title'];

    protected array $translatable = ['alt_text'];

    protected array $sortable = ['sort_order', 'created_at', 'updated_at', 'size', 'file_name'];

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(array $params = [])
    {
        // `?mime=image/webp` is the CMS-facing spelling of the `mime_type`
        // column, and the only one the dashboard sends. Aliased here rather than
        // renamed in `$safeParms` so `applyCondition` still receives a real
        // column name to build SQL from. An explicit `?mime_type=` wins.
        if (array_key_exists('mime', $params) && ! array_key_exists('mime_type', $params)) {
            $params['mime_type'] = $params['mime'];
        }

        parent::__construct($params);
    }

    public function apply(Builder $query): Builder
    {
        $this->applyAttachment($query);

        return parent::apply($query);
    }

    /**
     * `?unattached=true` is the library's "unused assets" view. Not expressible
     * through `$safeParms`, which compares a column to a value — this is an
     * `IS NULL` test on two columns at once.
     *
     * Follows the same three rules as every other filter param: absent or empty
     * means no filter, an uninterpretable value is a 422 via `castBool`, never a
     * silent guess.
     */
    protected function applyAttachment(Builder $query): void
    {
        if (! array_key_exists('unattached', $this->params)) {
            return;
        }

        $value = $this->params['unattached'];

        if ($this->isBlank($value)) {
            return;
        }

        $this->castBool($this->scalar($value, 'unattached'), 'unattached')
            ? $query->whereNull('mediable_type')
            : $query->whereNotNull('mediable_type');
    }
}
