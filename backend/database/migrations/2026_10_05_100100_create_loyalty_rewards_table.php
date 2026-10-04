<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (Q18): the staff-managed rewards catalog.
 *
 * `name` / `description` are Spatie translatable JSON locale maps. Rewards are
 * soft-deletable (recycle bin); a purged reward never breaks voucher history
 * because vouchers snapshot `type`, `reward_name` and `value_usd`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('type', 20)->index();
            $table->unsignedInteger('points_cost');
            $table->decimal('discount_usd', 10, 2)->nullable();
            $table->unsignedSmallInteger('voucher_valid_days');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // The CMS list rule (CmsListIndexTest): the composite serves the
            // public `is_active` list, the lone index serves `order by sort_order`.
            $table->index(['is_active', 'sort_order']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
    }
};
