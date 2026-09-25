<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (D-03): audit trail of housekeeping status changes.
 *
 * `room_status_history` rows are immutable — one row per accepted transition,
 * so there is no `updated_at`. `rooms.status_changed_at` / `status_changed_by`
 * are a deliberate denormalised copy of the latest history row, so the room
 * board reads them without a join.
 *
 * Sync owner: `App\Actions\Cms\UpdateRoomStatusAction` is the only writer of
 * this table and of the two `rooms` columns; it writes all three in one
 * `DB::transaction`.
 *
 * Adding / dropping the `status_changed_by` foreign key rebuilds `rooms` on
 * SQLite, which drops the `WHERE deleted_at IS NULL` scope of
 * `rooms_number_live_unique`; `restoreLiveRoomNumberUnique()` puts it back
 * (see 2026_09_26_100000_change_rooms_status_to_string).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            // Immutable audit row: created_at only, no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['room_id', 'created_at']);
            $table->index('changed_by');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('status_changed_by');
        });

        $this->restoreLiveRoomNumberUnique();
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['status_changed_by']);
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn('status_changed_at');
        });

        $this->restoreLiveRoomNumberUnique();

        Schema::dropIfExists('room_status_history');
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
