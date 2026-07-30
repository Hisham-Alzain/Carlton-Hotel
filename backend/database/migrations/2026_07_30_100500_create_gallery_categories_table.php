<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The filter chips above the website's photo gallery — Rooms, Dining, Lobby,
// Damascus.
//
// A table rather than an enum or a free-text column on `gallery_items` because
// the chip label is translated per locale (`name` is json) and editors reorder
// the chips; an enum would need a migration per new theme and a string column
// would have no place to keep either the label or the order.
//
// `slug` is the stable key the website filters on, so a re-labelled chip
// ("Dining" → "Restaurants") does not break a bookmarked deep link.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique()->index();
            $table->json('name');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_categories');
    }
};
