<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 (D-08): the reports dashboard range-scans posted folio lines and
 * completed payments over a hotel-local period converted to a UTC window.
 * `down()` drops only these two indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_items', function (Blueprint $table): void {
            $table->index('created_at', 'folio_items_created_at_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'payments_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('folio_items', function (Blueprint $table): void {
            $table->dropIndex('folio_items_created_at_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_status_created_at_index');
        });
    }
};
