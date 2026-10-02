<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (D-03): tickets carry current state for the staff support desk —
 * description, optional stay/room links, the creating staff member, the two
 * "have we ever" stamps and the current escalation level (the cap in D-19).
 *
 * History (escalation targets, reasons, per-status timestamps) is NOT stored
 * here: the `ticket_actions` timeline is canonical (council A8). The model
 * excludes `description` from the activity log (council A6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('subject');
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            // null = created by the chatbot, a guest or the system.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);

            $table->index('reservation_id');
            $table->index('room_id');
            $table->index('created_by');
            $table->index('source');
            $table->index(['status', 'priority']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'priority']);
            $table->dropIndex(['source']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['room_id']);
            $table->dropIndex(['reservation_id']);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('room_id');
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropColumn(['description', 'resolved_at', 'closed_at', 'escalation_level']);
        });
    }
};
