<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (D-02): the staff checklist of an event inquiry.
 *
 * Rows are created lazily on the first tick (the template is the
 * `EventChecklistItem` enum), so an inquiry with no rows is "nothing done".
 * The derived `deposit` item is never stored (D-04). `completed_by` is
 * restrictOnDelete: staff are deactivated, never hard-deleted (Phase 7 A6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_inquiry_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('item', 20);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['event_inquiry_id', 'item']);
            $table->index('completed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_inquiry_checklist_items');
    }
};
