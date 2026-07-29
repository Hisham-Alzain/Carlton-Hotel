<?php

namespace App\Base;

use App\Support\TranslatableRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Declarative query-string filtering for index endpoints.
 *
 * A filter is constructed by the **controller** from the request's query array
 * and handed to the service, so no service ever has to read `request()`. Only
 * columns listed in `$safeParms` are reachable, and only with the operators
 * that column opted into.
 *
 * Supported query syntax:
 *
 *   ?is_active[eq]=false        canonical DSL — column[operator]=value
 *   ?is_active=false            shorthand, equivalent to [eq] when `eq` is allowed
 *   ?status[in]=clean,dirty     comma list …
 *   ?status[]=clean&status[]=dirty   … or repeated array params (same as [in])
 *   ?search=deluxe              case-insensitive scan of $searchable + $translatable
 *   ?sort=sort_order&sort_dir=desc
 *
 * Three rules govern what happens to input the filter cannot honour, and they
 * are deliberately different from one another:
 *
 * 1. **Unknown column or operator → ignored.** `?icon=safe` on an entity that
 *    never whitelisted `icon` is dropped, so a stale dashboard build cannot
 *    break a list screen by sending a param the API no longer knows.
 * 2. **Empty value → no filter.** `?is_active=` is the "Status: All" option of
 *    a `<select>` that submits an empty option; it must mean *unfiltered*, not
 *    `where is_active = 0`.
 * 3. **Uninterpretable value → 422.** `?is_active=trve` is a typo, and a typo
 *    that silently returns the published list is indistinguishable from a real
 *    answer. Bad input is rejected with `error_code: validation_failed` and an
 *    `errors` entry keyed by the offending param, never reinterpreted.
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

    /** Truthy tokens accepted by the `bool` cast (lower-cased before matching). */
    protected const BOOL_TRUE = ['1', 'true', 'yes', 'on'];

    /** Falsy tokens accepted by the `bool` cast (lower-cased before matching). */
    protected const BOOL_FALSE = ['0', 'false', 'no', 'off'];

    /**
     * Character that neutralises a LIKE wildcard. Not a backslash: SQLite has
     * no default escape character (so `ESCAPE` must be stated explicitly) while
     * MySQL treats a backslash specially inside the string literal that would
     * carry it, leaving no spelling of `ESCAPE '\'` that both drivers accept.
     */
    protected const LIKE_ESCAPE = '!';

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

            foreach ($this->normalizeConditions($raw) as $operator => $value) {
                if (! in_array($operator, $operators, true)) {
                    continue;
                }

                // Rule 2: a present-but-empty param is the "no filter" option.
                if ($this->isBlank($value)) {
                    continue;
                }

                // `?is_active=x` reports as `is_active`; `?is_active[eq]=x` as
                // `is_active.eq`, so the client can find the param it fumbled.
                $key = is_array($raw) ? $field . '.' . $operator : $field;

                $this->applyCondition($query, $field, $operator, $value, $key);
            }
        }
    }

    /**
     * Reduce whatever PHP decoded out of the query string into `operator => value`.
     *
     * @return array<string, mixed>
     */
    protected function normalizeConditions(mixed $raw): array
    {
        // `?is_active=false` is treated as `?is_active[eq]=false`.
        if (! is_array($raw)) {
            return ['eq' => $raw];
        }

        $named      = [];
        $positional = [];

        foreach ($raw as $key => $value) {
            if (is_string($key)) {
                $named[$key] = $value;
            } else {
                $positional[] = $value;
            }
        }

        // `?status[]=clean&status[]=dirty` — repeated params are the `in`
        // operator. Dropping them (as this used to) contradicted the docblock
        // and lost the filter without telling anyone.
        if ($positional !== []) {
            $named['in'] = array_merge($this->toList($named['in'] ?? null), $positional);
        }

        return $named;
    }

    protected function applyCondition(
        Builder $query,
        string $field,
        string $operator,
        mixed $value,
        string $key
    ): void {
        match ($operator) {
            'eq'   => $query->where($field, '=', $this->cast($field, $this->scalar($value, $key), $key)),
            'gte'  => $query->where($field, '>=', $this->cast($field, $this->scalar($value, $key), $key)),
            'lte'  => $query->where($field, '<=', $this->cast($field, $this->scalar($value, $key), $key)),
            'like' => $query->where(
                fn (Builder $inner) => $this->orWhereLikeInsensitive($inner, $field, (string) $this->scalar($value, $key))
            ),
            'in'   => $this->applyIn($query, $field, $value, $key),
            default => null,
        };
    }

    protected function applyIn(Builder $query, string $field, mixed $value, string $key): void
    {
        $items = [];

        foreach ($this->toList($value) as $item) {
            $items[] = $this->cast($field, $this->scalar($item, $key), $key);
        }

        // `?status[in]=` (or a list of nothing but empties) is rule 2 again:
        // no filter. `whereIn($field, [])` would instead match no rows at all.
        if ($items === []) {
            return;
        }

        $query->whereIn($field, $items);
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
     * The CMS locale set, read through the one accessor that owns it.
     *
     * `TranslatableRules::locales()` is the single source of truth: it reads
     * `config('cms.locales')`, and throws when that is missing, empty or
     * contains a code that is not a plain locale tag. This class deliberately
     * adds nothing on top —
     *
     * - **no fallback list.** A hardcoded `['en','ar']` here would make search
     *   cover a *different* locale set than validation the moment the config is
     *   poisoned, and nothing would report it.
     * - **no local re-filtering.** Dropping the codes it dislikes would narrow
     *   the list silently, which is the same failure in a smaller disguise. The
     *   accessor already rejects anything unfit for a JSON path in raw SQL —
     *   `.` and `*` included — by refusing the whole list rather than pruning it.
     *
     * @return list<string>
     *
     * @throws RuntimeException when `cms.locales` is missing, empty or malformed
     */
    protected function locales(): array
    {
        return TranslatableRules::locales();
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
        $escape  = self::LIKE_ESCAPE;

        $query->orWhereRaw(
            "lower({$wrapped}) like ? escape '{$escape}'",
            ['%' . $this->escapeLike(mb_strtolower($term)) . '%'],
        );
    }

    /**
     * Neutralise LIKE wildcards in user input. Without this, `?search=100%`
     * matches every row and `?search=a_b` matches `axb` — a wrong answer the
     * caller has no way to spot.
     */
    protected function escapeLike(string $term): string
    {
        $e = self::LIKE_ESCAPE;

        // The escape character itself must be doubled first, otherwise the one
        // introduced by `%` → `!%` would be re-escaped.
        return str_replace([$e, '%', '_'], [$e . $e, $e . '%', $e . '_'], $term);
    }

    /**
     * Split a comma list — or pass an already-decoded array through — into the
     * list `in` compares against. Empty segments are dropped, not compared.
     *
     * @return list<mixed>
     */
    protected function toList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(
                $value,
                fn (mixed $item): bool => ! $this->isBlank($item),
            ));
        }

        if ($this->isBlank($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string) $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }

    protected function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Guard the scalar operators against array input. `?name[like][]=x` used to
     * be stringified to the literal `"Array"` (with a PHP warning) and searched
     * for — a query the caller never asked for.
     */
    protected function scalar(mixed $value, string $key): mixed
    {
        if (! is_scalar($value)) {
            throw $this->reject($key, __('validation.string', ['attribute' => $key]));
        }

        return $value;
    }

    protected function cast(string $field, mixed $value, string $key): mixed
    {
        return match ($this->casts[$field] ?? null) {
            'bool'  => $this->castBool($value, $key),
            'int'   => $this->castInt($value, $key),
            default => $value,
        };
    }

    protected function castBool(mixed $value, string $key): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $token = is_string($value) ? strtolower(trim($value)) : $value;

        if ($token === 1 || (is_string($token) && in_array($token, self::BOOL_TRUE, true))) {
            return true;
        }

        if ($token === 0 || (is_string($token) && in_array($token, self::BOOL_FALSE, true))) {
            return false;
        }

        throw $this->reject($key, __('validation.boolean', ['attribute' => $key]));
    }

    protected function castInt(mixed $value, string $key): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[+-]?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        throw $this->reject($key, __('validation.integer', ['attribute' => $key]));
    }

    /**
     * A filter value the DSL cannot interpret is a client error, not a hint.
     * Surfaced through the standard `validation_failed` envelope so callers
     * branch on the same `error_code` they already handle for request bodies.
     */
    protected function reject(string $key, string $message): ValidationException
    {
        return ValidationException::withMessages([$key => [$message]]);
    }
}
