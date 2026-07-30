<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes broke every natural-key unique index on the CMS tables.
 *
 * A plain `UNIQUE (slug)` counts rows the application no longer shows. Delete
 * the page `/about-us` and the editor can never create `/about-us` again: the
 * row is invisible in every index and every show route, but the index still owns
 * the slug. The same holds for `rooms.number` ("101" can never be re-used),
 * `menu_categories (dining_venue_id, slug)`, and — worst of the set —
 * `site_settings (group, key)`, where `UpsertSiteSettingsAction`'s
 * `updateOrCreate` would fail to *find* the trashed row (the soft-delete scope
 * hides it) and then fail to *insert* past the unique. A 500 on a settings save.
 *
 * The fix is to make the constraint mean what the application means: unique
 * **among live rows**. Trashed rows stop participating.
 *
 * ## Per-engine, because there is no portable partial unique index
 *
 * - **SQLite / PostgreSQL** — real partial index: `... WHERE deleted_at IS NULL`.
 *   The planner also uses it for lookups, because every Eloquent query on a
 *   soft-deletable model already carries `deleted_at is null`.
 * - **MySQL 8.0.13+** — no partial indexes, but functional key parts exist. A
 *   unique index over `CASE WHEN deleted_at IS NULL THEN col END` yields NULL for
 *   every trashed row, and SQL treats NULLs in a unique index as distinct, so
 *   trashed rows never collide — with each other or with a live row.
 * - Anything else (MariaDB has no functional indexes) fails loudly here rather
 *   than deploying a schema where a re-used slug is a 500. Port it with a stored
 *   generated column before switching engines.
 *
 * A plain, non-unique index on the same columns is ensured alongside, because
 * MySQL cannot use a functional index for a `where slug = ?` lookup and the
 * public site reads these tables by slug on every page.
 *
 * The validation half of the same problem lives in
 * `App\Validation\LiveRowPresenceVerifier` — the `unique:` and `exists:` rules
 * would otherwise still count trashed rows and answer 422 for a slug that is
 * free.
 */
return new class extends Migration
{
    /**
     * table => natural-key columns whose uniqueness must hold for live rows only.
     *
     * @var array<string, list<string>>
     */
    private array $targets = [
        'pages'              => ['slug'],
        'amenities'          => ['slug'],
        'experiences'        => ['slug'],
        'journal_posts'      => ['slug'],
        'gallery_categories' => ['slug'],
        'menu_categories'    => ['dining_venue_id', 'slug'],
        'rooms'              => ['number'],
        'site_settings'      => ['group', 'key'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $columns) {
            $this->ensureLookupIndex($table, $columns);
            $this->dropUniqueOn($table, $columns);
            $this->createLiveUnique($table, $columns);
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $columns) {
            $name = $this->liveUniqueName($table, $columns);

            if ($this->indexExists($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($name));
            }

            if ($this->findUnique($table, $columns) === null) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns));
            }
        }
    }

    /**
     * A non-unique index on the natural key, so slug/number lookups stay indexed
     * once the unique index becomes an expression MySQL cannot match against.
     * Skipped when one already covers the same leftmost prefix.
     *
     * @param  list<string>  $columns
     */
    private function ensureLookupIndex(string $table, array $columns): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (! ($index['unique'] ?? false) && array_slice($index['columns'], 0, count($columns)) === $columns) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns));
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropUniqueOn(string $table, array $columns): void
    {
        $name = $this->findUnique($table, $columns);

        if ($name !== null) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($name));
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function createLiveUnique(string $table, array $columns): void
    {
        $name   = $this->liveUniqueName($table, $columns);
        $driver = Schema::getConnection()->getDriverName();

        if ($this->indexExists($table, $name)) {
            return;
        }

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            $cols = implode(', ', array_map(fn (string $column): string => "\"{$column}\"", $columns));

            DB::statement("create unique index \"{$name}\" on \"{$table}\" ({$cols}) where \"deleted_at\" is null");

            return;
        }

        if ($driver === 'mysql') {
            // Functional key parts: each expression is wrapped in its own pair of
            // parentheses inside the key-part list, which is what tells MySQL it
            // is an expression rather than a column name.
            $parts = implode(', ', array_map(
                fn (string $column): string => "(case when `deleted_at` is null then `{$column}` end)",
                $columns,
            ));

            DB::statement("alter table `{$table}` add unique index `{$name}` ({$parts})");

            return;
        }

        throw new RuntimeException(
            "Cannot scope the unique index on {$table} to live rows on driver [{$driver}]: "
            .'it supports neither partial indexes (sqlite/pgsql) nor functional key parts (mysql 8.0.13+). '
            .'Add a stored generated column for the live natural key before migrating on this engine.',
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function liveUniqueName(string $table, array $columns): string
    {
        return $table.'_'.implode('_', $columns).'_live_unique';
    }

    /**
     * Name of the existing unique index covering exactly these columns, or null.
     *
     * Read from the schema rather than hardcoded: `menu_categories` names its
     * composite explicitly and the rest rely on Laravel's convention.
     *
     * @param  list<string>  $columns
     */
    private function findUnique(string $table, array $columns): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ! ($index['primary'] ?? false) && $index['columns'] === $columns) {
                return $index['name'];
            }
        }

        return null;
    }

    private function indexExists(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (strcasecmp((string) $index['name'], $name) === 0) {
                return true;
            }
        }

        return false;
    }
};
