<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (Q6, LOY-17): the loyalty discount applied to one reservation.
 *
 * `reservation_id` is unique (one application per booking, and the once-only
 * reversal anchor); `redeem_entry_id` is unique so a redeem entry funds at most
 * one application. `idempotency_key` is unique per guest (Q6): the booking
 * replay guard lives here, not in the ledger. `voucher_id` is deliberately
 * NOT unique - a voucher restored on cancel may be applied to a later
 * reservation, so two application rows can reference the same voucher over its
 * lifetime. Every FK is restrictOnDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_reservation_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->unique()->constrained('reservations')->restrictOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->string('idempotency_key', 64);
            $table->foreignId('redeem_entry_id')->nullable()->unique()->constrained('loyalty_ledger_entries')->restrictOnDelete();
            $table->unsignedInteger('points_redeemed')->default(0);
            $table->decimal('points_discount_usd', 10, 2)->default(0);
            $table->foreignId('voucher_id')->nullable()->constrained('loyalty_vouchers')->restrictOnDelete();
            $table->decimal('voucher_discount_usd', 10, 2)->default(0);
            $table->string('status', 16)->index();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            // Also serves as the guest_id FK index (leftmost prefix).
            $table->unique(['guest_id', 'idempotency_key']);
            $table->index('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_reservation_applications');
    }
};
