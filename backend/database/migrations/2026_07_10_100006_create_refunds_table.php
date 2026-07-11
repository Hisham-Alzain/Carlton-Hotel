<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_usd', 10, 2);
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->index('payment_id');
            $table->index('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
