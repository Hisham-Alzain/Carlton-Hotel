<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 (D-01, D-02, consultant override): housekeeping tasks.
 *
 * Dedupe lives in the database, not in action code:
 *  - `dedupe_key` is nullable and UNIQUE. `App\Models\HousekeepingTask`'s
 *    saving hook derives it (`{room_id}:{type}` for an open turnover, stayover
 *    or inspection task, NULL otherwise), so at most one open task of each of
 *    those types exists per room while any number of closed ones coexist
 *    (NULLs never collide). No other code writes the column.
 *  - `service_request_id` is a plain nullable UNIQUE FK (consultant override:
 *    no `source_type`/`source_id` morph, no morph alias), so a service request
 *    has at most one task, ever.
 *
 * `CreateHousekeepingTaskAction::ensureOpen()` catches one unique violation and
 * re-reads under the room lock. MySQL and SQLite keep the transaction usable
 * after that failed insert; Postgres would need a savepoint around it.
 *
 * `created_by` NULL means a system-created task — and also a task whose
 * creator was later deleted (nullOnDelete). That ambiguity is documented and
 * accepted (an explicit `origin` column is deferred).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->string('priority', 10)->default('normal');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('dedupe_key', 40)->nullable()->unique();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index('assigned_user_id');
            $table->index('reservation_id');
            $table->index('completed_by');
            $table->index('created_by');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_tasks');
    }
};
