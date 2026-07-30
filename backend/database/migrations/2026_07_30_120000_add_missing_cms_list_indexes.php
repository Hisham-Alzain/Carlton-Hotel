<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every CMS list screen — admin and public alike — filters on `is_active` and
 * orders by `sort_order`, so both columns carry an index on `facilities`,
 * `room_types`, `dining_venues`, `event_spaces`, `promotions`, `pages`,
 * `amenities`, `home_sliders`, `testimonials` and `faqs`.
 *
 * These tables were created without them. `menu_categories` missed both;
 * the rest missed one. The gap is invisible on seed data and turns into a full
 * table scan per list request as content grows.
 *
 * Additive by design: the original `create` migrations are left untouched
 * because they have already run in production. Each index is also guarded by
 * `hasIndex()` so this is safe to run against a database where someone has
 * since added one by hand.
 *
 * Deliberately excluded:
 *
 * - `media.sort_order` and `amenity_room_type.sort_order` are ordinals *within*
 *   one parent row (`mediable_type`+`mediable_id`, `room_type_id`). They are
 *   never scanned globally, and the leading FK index already narrows the read,
 *   so a standalone index would cost writes and buy nothing.
 */
return new class extends Migration
{
    /**
     * `table => [single-column indexes to add]`.
     *
     * @var array<string, list<string>>
     */
    private const INDEXES = [
        'menu_categories'    => ['is_active', 'sort_order'],
        'menu_items'         => ['is_active'],
        'pages'              => ['sort_order'],
        'pool_cabanas'       => ['is_active'],
        'restaurant_tables'  => ['is_active'],
        'spa_services'       => ['is_active'],
        'transfers'          => ['is_active'],
        'service_items'      => ['is_active', 'sort_order'],
        // service_categories already has a composite (is_active, sort_order).
        // Leftmost-prefix means it cannot serve `order by sort_order` on its
        // own, which is exactly what the admin list screen issues when no
        // status filter is applied.
        'service_categories' => ['sort_order'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column) || Schema::hasIndex($table, [$column])) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->index($column);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasIndex($table, [$column])) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropIndex([$column]);
                });
            }
        }
    }
};
