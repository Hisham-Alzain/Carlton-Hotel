<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (D-09): dispute history per folio item; status + history instead of
 * flag columns. Exactly one of guest_id/user_id is set (app-enforced). One open
 * dispute per item is enforced under the folio row lock, not by a partial index.
 *
 * (folio_item_id, status) serves "the open dispute of this item" and, by the
 * leftmost-prefix rule, the folio_item_id foreign key too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_item_disputes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('folio_item_id')->constrained('folio_items')->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('reason', 500);
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['folio_item_id', 'status']);
            $table->index('status');
            $table->index('guest_id');
            $table->index('user_id');
            $table->index('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_item_disputes');
    }
};
