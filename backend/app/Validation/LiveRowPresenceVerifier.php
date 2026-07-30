<?php

namespace App\Validation;

use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Make `unique:` and `exists:` agree with the soft-delete scope.
 *
 * ## The bug this closes
 *
 * The validator's presence verifier reaches the database through the *query*
 * builder, not Eloquent, so it never sees a model's global scopes. Once the CMS
 * content models gained `SoftDeletes`, every uniqueness rule started counting
 * rows the application no longer shows:
 *
 * - Delete the page `/about-us`. It is gone from every index and every show
 *   route, public and CMS. Create it again and `unique:pages,slug` answers 422
 *   "slug has already been taken" — naming a row the editor cannot see, cannot
 *   reach, and was told was deleted.
 * - `exists:gallery_categories,uuid` would happily accept a trashed chip as the
 *   parent of a brand-new photograph, publishing an item under a category the
 *   site will never render.
 *
 * Both are the same mistake: the rules mean "among the records that exist", and
 * after a soft delete a trashed row does not.
 *
 * ## Why here rather than in each rule
 *
 * The alternative is `->whereNull('deleted_at')` on every `unique`/`exists` rule
 * in `app/Http/Requests` — eight uniques and several dozen `exists` today, and a
 * silent trap on every request written from now on, because the failure mode is a
 * 422 that only appears after something has been deleted. Overriding the
 * verifier fixes the rules that exist and the ones nobody has written yet, in one
 * place, using the extension point Laravel provides for exactly this
 * (`Factory::setPresenceVerifier()`).
 *
 * The scope is narrow by construction: only tables that actually carry a
 * `deleted_at` column change behaviour, which is precisely the set where
 * "exists" and "not trashed" are meant to be the same question. `users`,
 * `guests`, `reservations`, `media` and everything else are untouched.
 *
 * ## The other half
 *
 * Validation is only the top layer. The database's own unique indexes counted
 * trashed rows too, and would have turned a now-valid insert into a 500 —
 * `2026_07_31_100100_scope_cms_unique_indexes_to_live_rows` scopes those to live
 * rows as well. Neither half works without the other.
 */
class LiveRowPresenceVerifier extends DatabasePresenceVerifier
{
    /**
     * Whether `{connection}.{table}` carries `deleted_at`.
     *
     * Per instance, not static: the verifier is a container singleton, so the
     * cache lives exactly as long as the application does — long enough that a
     * form with a dozen `exists` rules does not pay a dozen `information_schema`
     * round-trips on MySQL, short enough that it cannot outlive a schema change
     * (a test suite rebuilding the database between cases, say).
     *
     * @var array<string, bool>
     */
    private array $softDeletable = [];

    /**
     * @param  string  $table
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table($table)
    {
        $query = parent::table($table);

        if ($this->isSoftDeletable($table)) {
            // Table-qualified: `getCount()` is also used for rules carrying extra
            // `where` conditions, and an unqualified column would be ambiguous if
            // one of those ever joined.
            $query->whereNull($table.'.deleted_at');
        }

        return $query;
    }

    private function isSoftDeletable(string $table): bool
    {
        $key = ($this->connection ?? 'default').'.'.$table;

        return $this->softDeletable[$key] ??= $this->db
            ->connection($this->connection)
            ->getSchemaBuilder()
            ->hasColumn($table, 'deleted_at');
    }
}
