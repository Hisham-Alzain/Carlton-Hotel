<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Denormalized rating aggregates. Every mobile list screen shows a star score,
// so recomputing AVG() per row on read would mean a subquery per card.
// RecalculateRatingAction is the single writer — it runs inside the same
// transaction as any review insert/update/delete/publish-toggle.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['room_types', 'dining_venues'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('rating_avg', 2, 1)->nullable()->after('is_active');
                $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            });
        }
    }

    public function down(): void
    {
        foreach (['room_types', 'dining_venues'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['rating_avg', 'rating_count']);
            });
        }
    }
};
