<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (Q4, LOY-02): the loyalty program settings singleton.
 *
 * One row (`singleton` = 1) holds the six dashboard-configurable values. The
 * migration inserts NO row and the four rate columns are nullable with no
 * default ("no seeded rates"): a null rate means that capability is switched
 * off. Only `expiry_months` (24) and `expiry_warning_days` (30) carry column
 * defaults. Settings never live in the public website settings table and are
 * never cached (M-8). `updated_by` is restrictOnDelete: staff are deactivated,
 * never hard-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton')->unique();
            $table->decimal('earn_rate', 8, 4)->nullable();
            $table->decimal('redeem_value_usd', 10, 4)->nullable();
            $table->unsignedSmallInteger('expiry_months')->default(24);
            $table->unsignedSmallInteger('expiry_warning_days')->default(30);
            $table->unsignedInteger('min_redeem_points')->nullable();
            $table->decimal('max_redeem_percent', 5, 2)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
