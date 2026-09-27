<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 (D-20): how a stay was checked out (`none`, `staff_force`,
 * `guest_express` — App\Enums\CheckOutMode).
 *
 * Written only by CheckOutReservationAction, in the same update as
 * `checked_out_at`, with the effective mode. Rows checked out before this
 * phase stay NULL: honestly unknown, never backfilled. Staff-only on the wire.
 * Additive and nullable, no index (read per row, never filtered in SQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('check_out_mode', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('check_out_mode');
        });
    }
};
