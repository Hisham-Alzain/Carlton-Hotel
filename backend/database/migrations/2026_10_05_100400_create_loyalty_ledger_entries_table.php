<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (M-9, Q16, LOY-17): the immutable signed points ledger.
 *
 * Append-only: rows are inserted and never updated, so there is no
 * `updated_at` (only `created_at`) and no activity log (the ledger IS the
 * audit trail). `points` is signed (credits positive, debits negative).
 * `idempotency_key` is unique so a retried action cannot write twice;
 * `reverses_entry_id` is unique so an earn or redeem is reversed at most once.
 * `source` is a snapshot of the originating batch source so listings need no
 * join. `shortfall_points` records clawback that could not be taken because
 * the points were already spent. Every FK is restrictOnDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('source', 16)->nullable();
            $table->integer('points');
            $table->foreignId('batch_id')->nullable()->constrained('loyalty_earn_batches')->restrictOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained('loyalty_vouchers')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->restrictOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained('folios')->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->unique()->constrained('loyalty_ledger_entries')->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedInteger('shortfall_points')->default(0);
            $table->decimal('discount_usd', 10, 2)->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            // Also serves as the guest_id FK index (leftmost prefix).
            $table->index(['guest_id', 'occurred_at', 'id']);
            $table->index(['type', 'occurred_at']);
            $table->index('reservation_id');
            $table->index('folio_id');
            $table->index('batch_id');
            $table->index('voucher_id');
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_ledger_entries');
    }
};
