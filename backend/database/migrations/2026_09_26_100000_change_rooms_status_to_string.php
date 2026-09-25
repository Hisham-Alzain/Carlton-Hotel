<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (D-01): `rooms.status` becomes the housekeeping status only —
 * `available | dirty | maintenance` — and occupancy is derived from checked-in
 * reservations, never stored. The column moves from a DB enum to `string(20)`
 * so a new housekeeping state is an enum-case change, not a schema change.
 *
 * - MySQL: `->change()` compiles to a native `ALTER TABLE ... MODIFY`, which
 *   keeps `rooms_status_index` and every other index as they are.
 * - SQLite: `->change()` rebuilds the table (temp table, copy, drop, rename)
 *   and re-creates the existing indexes from the schema state.
 *
 * No index modifier is chained onto the changed column: `rooms_status_index`
 * survives on both drivers, and a second index of the same name would fail.
 *
 * The retired in-house value is mapped to `available` in the same migration
 * (idempotent — a second run matches nothing).
 *
 * SQLite caveat: the rebuild re-creates `rooms_number_live_unique` from
 * `Schema::getIndexes()`, which carries no `WHERE` clause, so the live-row
 * scope set by `2026_07_31_100100_scope_cms_unique_indexes_to_live_rows`
 * would silently become a plain unique (a trashed room's number could never be
 * reused). `restoreLiveRoomNumberUnique()` puts the partial index back after
 * every rebuild. MySQL keeps its functional index through `MODIFY`, so the
 * step is SQLite-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->change();
        });

        $this->restoreLiveRoomNumberUnique();

        DB::table('rooms')->where('status', 'occupied')->update(['status' => 'available']);
    }

    public function down(): void
    {
        // The old enum has no `dirty` value; map it first so the rebuild's
        // CHECK constraint (SQLite) or the MODIFY (MySQL) accepts every row.
        DB::table('rooms')->where('status', 'dirty')->update(['status' => 'available']);

        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available')->change();
        });

        $this->restoreLiveRoomNumberUnique();
    }

    /**
     * Re-scope `rooms_number_live_unique` to live rows after a SQLite table
     * rebuild (same statement as the 2026_07_31 scope migration).
     */
    private function restoreLiveRoomNumberUnique(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('rooms', fn (Blueprint $table) => $table->dropUnique('rooms_number_live_unique'));
        DB::statement('create unique index "rooms_number_live_unique" on "rooms" ("number") where "deleted_at" is null');
    }
};
