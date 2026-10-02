<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (D-02): service-recovery records, one per `recovery` timeline row.
 * Money is DECIMAL here, never in the action's json meta. A `folio_credit`
 * recovery LINKS an existing Phase 5 credit line (D-15); the unique
 * `folio_item_id` stops one credit being claimed twice.
 *
 * No `ticket_id` and no `recorded_by`: both are reached through the action (3NF).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_recoveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_action_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('amount_usd', 10, 2)->nullable();
            $table->string('description', 1000);
            $table->foreignId('folio_item_id')->nullable()->unique()->constrained('folio_items')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_recoveries');
    }
};
