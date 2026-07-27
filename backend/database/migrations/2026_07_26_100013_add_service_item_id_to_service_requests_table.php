<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Links a request back to the catalog item it came from. Nullable so existing
// free-string rows stay valid and no backfill is required; `type` and
// `department` remain snapshotted strings, so the P10 queue sees no change.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('service_item_id')->nullable()->after('reservation_id')
                ->constrained('service_items')->nullOnDelete();

            $table->index('service_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_item_id');
        });
    }
};
