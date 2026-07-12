<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price_usd', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('menu_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
