<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 (D-08): guest preferences as typed columns; no JSON column; split to
 * a 1:1 table at 8+ fields or per-stay preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('bed_type', 20)->nullable();
            $table->string('pillow_type', 20)->nullable();
            $table->string('floor_preference', 10)->nullable();
            $table->string('preferences_other', 500)->nullable();
            $table->timestamp('preferences_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn([
                'bed_type', 'pillow_type', 'floor_preference', 'preferences_other', 'preferences_updated_at',
            ]);
        });
    }
};
