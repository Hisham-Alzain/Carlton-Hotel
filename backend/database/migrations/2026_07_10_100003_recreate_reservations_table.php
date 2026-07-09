<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replaces the P1 stub. Runs after promo_codes so the FK is valid.
return new class extends Migration {
    public function up(): void
    {
        Schema::drop('reservations');

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete()->index();
            $table->string('booking_code')->unique();
            $table->string('source')->default('direct')->index();
            $table->string('external_ref')->nullable();
            $table->string('external_channel')->nullable();
            $table->date('check_in')->index();
            $table->date('check_out')->index();
            $table->string('status')->default('pending')->index();
            $table->datetime('hold_expires_at')->nullable()->index();
            $table->string('payment_method');
            $table->decimal('total_usd', 10, 2);
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete()->index();
            // Contact override for externally-sourced reservations without a linked guest
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->unique(['external_channel', 'external_ref'], 'reservations_ext_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete()->index();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }
};
