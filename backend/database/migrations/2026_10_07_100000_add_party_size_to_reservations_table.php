<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Party size of a booking: `adults` (at least 1) and `children`, as sent by the
 * guest app on POST /reservations and by reception on POST /cms/reservations.
 *
 * Every booking path writes them through CreateReservationAction, which also
 * caps adults + children at the room type's max_occupancy. Reservations created
 * before this migration read the minimum party (1 adult, 0 children) because the
 * number was never collected: a floor, not a recorded fact.
 * Additive, NOT NULL with defaults, no index (read per row, never filtered).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['adults', 'children']);
        });
    }
};
