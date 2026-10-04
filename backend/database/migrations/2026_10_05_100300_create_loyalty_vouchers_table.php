<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (M-9, T-10-04, T-10-05): vouchers a guest redeems from the catalog.
 *
 * `code` is unique (guest-scoped lookup is enforced in the actions) and
 * `reservation_id` is unique: a voucher is applied to at most one reservation,
 * and the column is nulled when the voucher is restored on cancel.
 * `loyalty_reward_id` is the only null-on-delete link, backed by the snapshot
 * columns (`type`, `reward_name`, `value_usd`) so a purged reward never breaks
 * voucher history.
 * Guest and reservation FKs are restrictOnDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 16)->unique();
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->foreignId('loyalty_reward_id')->nullable()->constrained('loyalty_rewards')->nullOnDelete();
            $table->string('type', 20);
            $table->json('reward_name');
            $table->decimal('value_usd', 10, 2)->nullable();
            $table->unsignedInteger('points_spent');
            $table->string('status', 16);
            $table->timestamp('expires_at');
            $table->foreignId('reservation_id')->nullable()->unique()->constrained('reservations')->restrictOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('loyalty_reward_id');
            // Also serves as the guest_id FK index (leftmost prefix).
            $table->index(['guest_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_vouchers');
    }
};
