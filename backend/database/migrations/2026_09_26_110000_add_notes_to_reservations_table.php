<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (D-11): staff free-text notes on a reservation.
 *
 * Additive and nullable, no index: notes are free text and never filtered on.
 * The edit history is the Reservation model's own LogsActivity trail (old/new
 * text and the staff causer), so there is no `reservation_notes` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
