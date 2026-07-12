<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('bookable_type');
            $table->unsignedBigInteger('bookable_id');
            $table->dateTime('scheduled_at');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('guest_id');
            $table->index('reservation_id');
            $table->index(['bookable_type', 'bookable_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_bookings');
    }
};
