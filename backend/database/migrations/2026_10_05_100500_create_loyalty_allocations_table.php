<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (M-9): which earn batch a ledger entry debited or credited.
 *
 * A redeem, expire or clawback that drains several batches FIFO writes one
 * allocation per batch touched; the unique (`ledger_entry_id`, `batch_id`)
 * pair also serves as the `ledger_entry_id` FK index. Pure join data: no uuid,
 * no timestamps, no audit. Both FKs are restrictOnDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_entry_id')->constrained('loyalty_ledger_entries')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('loyalty_earn_batches')->restrictOnDelete();
            $table->unsignedInteger('points');

            $table->unique(['ledger_entry_id', 'batch_id']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_allocations');
    }
};
