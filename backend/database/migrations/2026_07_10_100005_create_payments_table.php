<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('payable');
            $table->string('method');
            $table->decimal('amount_usd', 10, 2);
            $table->foreignId('recorded_by')->constrained('users');
            $table->text('note')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->index('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
