<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make an editorial delete recoverable.
 *
 * ## Which tables, and why only these
 *
 * Every table here holds *content* — copy and photography an editor authors and
 * can destroy from the CMS with one request. A mis-click on "delete venue" used
 * to be unrecoverable and, worse, took the venue's whole menu with it through
 * `ON DELETE CASCADE`. `deleted_at` turns that into a recycle bin.
 *
 * `site_settings` has no delete route today, but its identity is `(group, key)`
 * and `UpsertSiteSettingsAction` reaches it with `updateOrCreate` — an Eloquent
 * call that honours the soft-delete scope. It is included so that a future
 * single-setting delete endpoint cannot produce a row the upsert can neither
 * find nor insert past the composite unique. The companion migration scopes that
 * unique to live rows for exactly that reason.
 *
 * ## What is deliberately NOT here
 *
 * - `reservations`, `folios`, `folio_items`, `payments`, `refunds`, `guests`:
 *   transactional and financial. "Deleted but still on the ledger" is an audit
 *   and reconciliation decision, not a UX one, and it needs a retention policy
 *   before a column.
 * - `media`: its delete hook is what unlinks the stored file. A recoverable
 *   media row would either leak files forever or unlink a file a restore still
 *   needs. `PurgesMedia` keeps a soft-deleted parent's media rows intact
 *   instead, which is the recovery this table needs.
 * - The bookable service catalog (`spa_services`, `restaurant_tables`,
 *   `pool_cabanas`, `transfers`, `service_categories`, `service_items`) and the
 *   pricing config (`rate_plans`, `pricing_rules`, `promo_codes`): rows here are
 *   referenced by `service_bookings` and by reservation price snapshots, so
 *   their delete semantics belong with the reservation work, not with CMS copy.
 *   Known gap, deliberately deferred.
 *
 * ## No index on `deleted_at`
 *
 * Every query now carries `deleted_at is null`, but a boolean-ish column that is
 * NULL for ~100% of rows is not selective enough for the planner to use, and a
 * leading `deleted_at` would defeat the leftmost-prefix on the list indexes
 * added in `2026_07_30_120000_add_missing_cms_list_indexes`. The partial unique
 * indexes in the next migration are where `deleted_at` earns its keep.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'room_types',
        'rooms',
        'dining_venues',
        'menu_categories',
        'menu_items',
        'facilities',
        'event_spaces',
        'home_sliders',
        'promotions',
        'pages',
        'amenities',
        'testimonials',
        'faqs',
        'experiences',
        'gallery_categories',
        'gallery_items',
        'journal_posts',
        'site_settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
