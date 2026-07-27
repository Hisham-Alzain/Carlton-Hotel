<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Restaurant table reservations carry a party size. Nullable because spa
// treatments, cabanas and transfers do not.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('guest_count')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropColumn('guest_count');
        });
    }
};
