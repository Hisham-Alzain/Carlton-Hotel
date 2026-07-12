<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->decimal('subtotal_usd', 10, 2)->default(0);
            $table->decimal('total_usd', 10, 2)->default(0);
            $table->timestamp('approved_by_guest_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
