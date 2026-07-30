<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `is_active` and `sort_order` are the WHERE and ORDER BY of every CMS list
 * screen, admin and public alike, so the database rules require an index on
 * each. Most CMS tables declared one inline at create time; a handful shipped
 * without, which is invisible on seed data and becomes a full table scan per
 * list request as content grows.
 *
 * This is a sweep rather than a fixed list: a new content type that adds either
 * column without an index fails here, which is the only way the convention
 * stays true as modules land. If a genuinely scoped ordinal needs to opt out,
 * add it to `EXEMPT` with the reason — silence is not an option.
 */
class CmsListIndexTest extends TestCase
{
    use RefreshDatabase;

    /** Columns that must be individually indexed wherever they appear. */
    private const LIST_COLUMNS = ['is_active', 'sort_order'];

    /**
     * `table.column` entries deliberately left unindexed, with the reason.
     *
     * Both are ordinals *within* one parent row rather than table-wide sort
     * keys — they are only ever read after the leading foreign key has already
     * narrowed the result to a handful of rows, so an index would cost writes
     * and buy nothing.
     *
     * @var array<string, string>
     */
    private const EXEMPT = [
        'media.sort_order'             => 'ordered within one mediable_type+mediable_id',
        'amenity_room_type.sort_order' => 'ordered within one room_type_id',
    ];

    /**
     * The table whose missing indexes prompted this test. Pinned by name so the
     * regression cannot be lost if the generic sweep is ever narrowed.
     */
    public function test_menu_categories_indexes_its_list_columns(): void
    {
        $this->assertTrue(Schema::hasIndex('menu_categories', ['is_active']));
        $this->assertTrue(Schema::hasIndex('menu_categories', ['sort_order']));
    }

    #[DataProvider('listColumnProvider')]
    public function test_every_list_column_is_indexed(string $column): void
    {
        $unindexed = [];

        foreach ($this->tables() as $table) {
            if (! Schema::hasColumn($table, $column) || isset(self::EXEMPT["{$table}.{$column}"])) {
                continue;
            }

            if (! $this->hasLeadingIndexOn($table, $column)) {
                $unindexed[] = "{$table}.{$column}";
            }
        }

        $this->assertSame(
            [],
            $unindexed,
            "These columns drive a list screen's WHERE/ORDER BY with no index behind them: "
            . implode(', ', $unindexed)
            . '. Add `->index()` in the create migration for a new table, or an additive '
            . 'migration for one that has already shipped.',
        );
    }

    public static function listColumnProvider(): array
    {
        return array_combine(self::LIST_COLUMNS, array_map(
            static fn (string $column): array => [$column],
            self::LIST_COLUMNS,
        ));
    }

    /**
     * Whether any index on `$table` *leads* with `$column`.
     *
     * Leftmost prefix, not exact match: `service_categories` covers `is_active`
     * with a composite `(is_active, sort_order)`, which serves
     * `where is_active = ?` perfectly well. The same composite does nothing for
     * `order by sort_order` on its own, which is why that half still needs an
     * index of its own.
     */
    private function hasLeadingIndexOn(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'][0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * Framework-owned tables are excluded: their schema is not ours to change,
     * and none of them is a CMS list.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $vendor = [
            'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches',
            'failed_jobs', 'sessions', 'password_reset_tokens',
            'personal_access_tokens', 'permissions', 'roles',
            'model_has_permissions', 'model_has_roles', 'role_has_permissions',
        ];

        return array_values(array_diff(
            array_column(Schema::getTables(), 'name'),
            $vendor,
        ));
    }
}
