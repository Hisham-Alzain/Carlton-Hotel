<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 (D-03): immutable audit trail of housekeeping task status changes,
 * a twin of `room_status_history`. One row per accepted transition (creation
 * writes `null → pending`), written by the housekeeping single writers in the
 * same transaction as the status change. No `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housekeeping_task_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housekeeping_task_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['housekeeping_task_id', 'created_at'], 'hk_task_history_task_created_index');
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_task_status_history');
    }
};
