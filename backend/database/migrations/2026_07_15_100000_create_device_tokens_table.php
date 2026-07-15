<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('token', 500)->unique();
            $table->string('platform');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
