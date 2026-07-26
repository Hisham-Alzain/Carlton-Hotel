<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fields the mobile room card and room-details screen read: view type, bed
// configuration and the free-cancellation window.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->string('view_type', 32)->nullable()->after('description')->index();
            $table->json('bed_types')->nullable()->after('view_type');
            // Hours before check-in during which a cancellation is still free.
            $table->unsignedSmallInteger('cancellation_hours')->default(48)->after('base_price_usd');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropIndex(['view_type']);
            $table->dropColumn(['view_type', 'bed_types', 'cancellation_hours']);
        });
    }
};
