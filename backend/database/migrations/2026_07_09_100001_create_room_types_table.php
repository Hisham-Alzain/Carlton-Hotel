<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->json('name');
            $table->json('description');
            $table->json('amenities')->nullable();
            $table->unsignedSmallInteger('base_occupancy')->default(2);
            $table->unsignedSmallInteger('max_occupancy')->default(2);
            $table->decimal('size_sqm', 8, 2)->nullable();
            $table->decimal('base_price_usd', 10, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
