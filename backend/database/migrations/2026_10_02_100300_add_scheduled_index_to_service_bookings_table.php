<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (D-08): the staff table-reservation list scans one bookable type
 * over a hotel-local day window (`GET /cms/table-reservations`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table): void {
            $table->index(['bookable_type', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table): void {
            $table->dropIndex(['bookable_type', 'scheduled_at']);
        });
    }
};
