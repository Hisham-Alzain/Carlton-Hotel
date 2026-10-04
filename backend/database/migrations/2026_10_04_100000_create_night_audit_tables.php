<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 (D-07): night audit storage.
 *
 * - `night_audit_states` is a singleton (`singleton` = 1) holding the business
 *   date the hotel is on; every opener/resolver/closer locks it first (D-11).
 * - `night_audits` is one row per business date (unique).
 * - `night_audit_checks` is always exactly five rows per audit (one per
 *   `NightAuditCheckType`); `night_audit_blockers` exists only for a non-empty
 *   blocking check (≤ 2 per audit).
 *
 * The snapshot columns (type, blocking, issue_count, evidence,
 * evidence_truncated, evaluated_at, snapshot_basis) are written once by
 * `OpenNightAuditAction` and never updated. No soft deletes and no delete
 * endpoint: every FK is restrictOnDelete so audit history cannot vanish
 * through a cascade (staff are deactivated, never hard-deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('night_audit_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton')->unique();
            $table->date('current_business_date');
            $table->date('last_closed_date')->nullable();
            $table->timestamps();
        });

        Schema::create('night_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('business_date')->unique();
            $table->string('status', 16)->index();
            $table->string('snapshot_basis', 32);
            $table->timestamp('evaluated_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('opened_by');
            $table->index('closed_by');
        });

        Schema::create('night_audit_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('night_audit_id')->constrained('night_audits')->restrictOnDelete();
            $table->string('type', 40);
            $table->boolean('blocking');
            $table->string('status', 16);
            $table->unsignedInteger('issue_count');
            $table->json('evidence');
            $table->boolean('evidence_truncated');
            $table->string('note', 1000)->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            // Also serves as the night_audit_id FK index (leftmost prefix).
            $table->unique(['night_audit_id', 'type']);
            $table->index('acted_by');
        });

        Schema::create('night_audit_blockers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('night_audit_id')->constrained('night_audits')->restrictOnDelete();
            $table->foreignId('night_audit_check_id')->unique()->constrained('night_audit_checks')->restrictOnDelete();
            $table->string('status', 16);
            $table->string('note', 1000)->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index('night_audit_id');
            $table->index('acted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('night_audit_blockers');
        Schema::dropIfExists('night_audit_checks');
        Schema::dropIfExists('night_audits');
        Schema::dropIfExists('night_audit_states');
    }
};
