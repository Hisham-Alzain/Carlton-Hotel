<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The "microservices" a guest can request under a category.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            // Locale-free so the client formats it ("~30 min" / "٣٠ دقيقة") and
            // ops can later sort/compare it for SLA highlighting.
            $table->unsignedSmallInteger('expected_minutes')->nullable();
            // Null = complimentary. A priced item is billed to the folio when a
            // request for it exists — see GenerateFolioAction.
            $table->decimal('price_usd', 10, 2)->nullable();
            // The hidden item behind a `direct` category, requested without a picker.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['service_category_id', 'is_active', 'sort_order'], 'service_items_catalog_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
