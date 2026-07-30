<?php

namespace App\Filters;

use App\Base\BaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

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

    /** Namespace a short type token is resolved against. */
    private const MODEL_NAMESPACE = 'App\\Models\\';

    public function apply(Builder $query): Builder
    {
        $this->applyAttachment($query);

        return parent::apply($query);
    }

    /**
     * Resolve `?mediable_type=` before it is compared to the column.
     *
     * Hooked on `cast()` rather than rewritten in the constructor so `eq` and
     * `in` get it from one place — the DSL has already unwrapped
     * `?mediable_type[in]=a,b` into individual scalars by the time this runs.
     */
    protected function cast(string $field, mixed $value, string $key): mixed
    {
        $value = parent::cast($field, $value, $key);

        return $field === 'mediable_type' ? $this->resolveMediableType($value) : $value;
    }

    /**
     * Accept `room_type` as well as `App\Models\RoomType`.
     *
     * The column stores whatever `getMorphClass()` returned when the row was
     * written: a short alias for the models in `Relation::morphMap()`, the FQCN
     * for everybody else. Demanding the FQCN made the filter the one place in
     * the API that leaked this application's namespace, made it wrong for any
     * model that later joins the morph map, and disagreed with `MediaResource`,
     * which publishes the short token — so a client could read `room_type` off a
     * row and not filter by it.
     *
     * Resolution goes token → class → stored form, which is the same answer
     * `getMorphClass()` gave when the row was written, so both spellings land on
     * the same rows and the dashboard build that sends
     * `?mediable_type=App\Models\RoomType` today keeps working untouched. That
     * compatibility is the reason this normalises rather than replaces: the
     * FQCN input is not deprecated, it is one of two accepted spellings.
     *
     * An unresolvable value is passed through verbatim rather than rejected. It
     * matches nothing, which is the honest answer to "assets on a kind of record
     * that does not exist", and it keeps a stale dashboard build from turning a
     * list screen into a 422 — the same reasoning as `BaseFilter`'s rule 1 for
     * unknown columns.
     */
    private function resolveMediableType(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        // A registered alias *is* the stored value — nothing to resolve.
        if (Relation::getMorphedModel($value) !== null) {
            return $value;
        }

        if (class_exists($value)) {
            // An alias when the class is mapped, the FQCN itself when it is not.
            return Relation::getMorphAlias($value);
        }

        // A bare token. Constrained to a plain identifier before it reaches the
        // autoloader, so no separator or traversal from the query string can be
        // spliced into a class name.
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) !== 1) {
            return $value;
        }

        $class = self::MODEL_NAMESPACE.Str::studly($value);

        return class_exists($class) ? Relation::getMorphAlias($class) : $value;
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
