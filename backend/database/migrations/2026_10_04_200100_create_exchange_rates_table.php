<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9.1 (D-14): display exchange rates, append-only. `rate` = units of
 * `currency` per 1 USD. Each change is a new row (the table is its own
 * history); there is no update or delete path. `(currency, id)` serves the
 * latest-per-currency lookup and, by leftmost prefix, currency filters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('currency', 3);
            $table->decimal('rate', 20, 6);
            $table->string('note', 255)->nullable();
            // Staff are deactivated, never deleted.
            $table->foreignId('set_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['currency', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
