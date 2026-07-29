<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Builder;

/**
 * Declarative query-string filtering for index endpoints.
 *
 * A filter is constructed by the **controller** from the request's query array
 * and handed to the service, so no service ever has to read `request()`. Only
 * columns listed in `$safeParms` are reachable, and only with the operators
 * that column opted into — an unknown column or operator is silently ignored
 * rather than erroring, so a stale dashboard build never breaks a list screen.
 *
 * Supported query syntax:
 *
 *   ?is_active[eq]=false        canonical DSL — column[operator]=value
 *   ?is_active=false            shorthand, equivalent to [eq] when `eq` is allowed
 *   ?status[in]=clean,dirty     comma list or repeated array params
 *   ?search=deluxe              case-insensitive scan of $searchable + $translatable
 *   ?sort=sort_order&sort_dir=desc
 */
class BaseFilter
{
    /**
     * Operator whitelist — `column => [operators]`. Operators are `eq`, `like`,
     * `gte`, `lte`, `in`.
     *
     * @var array<string, list<string>>
     */
    protected array $safeParms = [];

    /**
     * Scalar coercion for whitelisted columns: `bool` or `int`. Query strings
     * are always strings and `where('is_active', '=', 'false')` matches nothing
     * on either MySQL or SQLite, so booleans must be coerced before comparison.
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * Plain (non-JSON) columns scanned by `?search=`.
     *
     * @var list<string>
     */
    protected array $searchable = [];

    /**
     * Spatie `HasTranslations` JSON columns scanned by `?search=`. Each entry is
     * expanded to one `column->locale` JSON path per configured CMS locale — a
     * plain `where('name', 'like', …)` would match against the raw JSON blob,
     * including the locale keys themselves.
     *
     * @var list<string>
     */
    protected array $translatable = [];

    /**
     * Columns the client may order by via `?sort=`. Anything else keeps the
     * service's own ordering.
     *
     * @var list<string>
     */
    protected array $sortable = [];

    /** Every operator the DSL understands. */
    public const OPERATORS = ['eq', 'like', 'gte', 'lte', 'in'];

    /**
     * @param  array<string, mixed>  $params   The request's query string, already
     *                                         decoded. Never the Request itself.
     * @param  list<string>          $allowed  Ad-hoc whitelist for callers that
     *                                         instantiate `BaseFilter` directly
     *                                         instead of declaring a subclass —
     *                                         each field gets every operator.
     */
    public function __construct(protected readonly array $params = [], array $allowed = [])
    {
        foreach ($allowed as $field) {
            $this->safeParms[$field] ??= self::OPERATORS;
        }
    }

    public function apply(Builder $query): Builder
    {
        $this->applyConditions($query);
        $this->applySearch($query);
        $this->applySort($query);

        return $query;
    }

    protected function applyConditions(Builder $query): void
    {
        foreach ($this->safeParms as $field => $operators) {
            if (! array_key_exists($field, $this->params)) {
                continue;
            }

            $raw = $this->params[$field];

            // `?is_active=false` is treated as `?is_active[eq]=false`.
            $conditions = is_array($raw) ? $raw : ['eq' => $raw];

            foreach ($conditions as $operator => $value) {
                if (! is_string($operator) || ! in_array($operator, $operators, true)) {
                    continue;
                }
                $this->applyCondition($query, $field, $operator, $value);
            }
        }
    }

    protected function applyCondition(Builder $query, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            'eq'   => $query->where($field, '=', $this->cast($field, $value)),
            'gte'  => $query->where($field, '>=', $this->cast($field, $value)),
            'lte'  => $query->where($field, '<=', $this->cast($field, $value)),
            'like' => $query->where(
                fn (Builder $inner) => $this->orWhereLikeInsensitive($inner, $field, (string) $value)
            ),
            'in'   => $query->whereIn($field, array_map(
                fn (mixed $item): mixed => $this->cast($field, $item),
                is_array($value) ? $value : explode(',', (string) $value),
            )),
            default => null,
        };
    }

    protected function applySearch(Builder $query): void
    {
        $term = $this->params['search'] ?? null;

        if (! is_string($term) || trim($term) === '') {
            return;
        }

        $columns = array_merge($this->searchable, $this->translatablePaths());

        if ($columns === []) {
            return;
        }

        $term = trim($term);

        // Grouped so the OR-chain cannot leak past an existing AND condition
        // (e.g. `?is_active=false&search=spa` must stay an AND of the two).
        $query->where(function (Builder $inner) use ($columns, $term): void {
            foreach ($columns as $column) {
                $this->orWhereLikeInsensitive($inner, $column, $term);
            }
        });
    }

    protected function applySort(Builder $query): void
    {
        $column = $this->params['sort'] ?? null;

        if (! is_string($column) || ! in_array($column, $this->sortable, true)) {
            return;
        }

        $direction = strtolower((string) ($this->params['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        // reorder() drops the service's default ordering — an explicit client
        // sort must win, not act as a tiebreaker. The primary key is appended
        // so pagination stays stable across pages when the sort column ties.
        $query->reorder()
            ->orderBy($column, $direction)
            ->orderBy($query->getModel()->getKeyName());
    }

    /**
     * Expand each translatable column into one JSON path per configured locale.
     *
     * @return list<string>
     */
    protected function translatablePaths(): array
    {
        if ($this->translatable === []) {
            return [];
        }

        $paths = [];

        foreach ($this->locales() as $locale) {
            foreach ($this->translatable as $column) {
                $paths[] = $column . '->' . $locale;
            }
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    protected function locales(): array
    {
        $configured = config('cms.locales');
        $locales    = is_array($configured) && $configured !== [] ? $configured : ['en', 'ar'];

        // The locale ends up inside a JSON path in raw SQL, so anything that is
        // not a plain locale tag is dropped rather than trusted.
        return array_values(array_filter(
            $locales,
            static fn (mixed $locale): bool => is_string($locale) && preg_match('/^[A-Za-z0-9_-]+$/', $locale) === 1,
        ));
    }

    /**
     * Case-insensitive LIKE that behaves the same on MySQL and SQLite.
     *
     * `whereLike(..., caseSensitive: false)` is not enough: MySQL's
     * `JSON_UNQUOTE(JSON_EXTRACT(...))` yields a binary-collated string, so a
     * plain LIKE against a translatable column is case-*sensitive* there while
     * SQLite's LIKE is case-insensitive. Lowering both sides makes the two
     * engines agree. `wrap()` compiles `name->en` to the driver's own JSON
     * accessor, so this stays one code path for JSON and plain columns alike.
     */
    protected function orWhereLikeInsensitive(Builder $query, string $column, string $term): void
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        $query->orWhereRaw("lower({$wrapped}) like ?", ['%' . mb_strtolower($term) . '%']);
    }

    protected function cast(string $field, mixed $value): mixed
    {
        return match ($this->casts[$field] ?? null) {
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'int'   => (int) $value,
            default => $value,
        };
    }
}
