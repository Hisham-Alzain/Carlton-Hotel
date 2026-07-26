<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Full-bleed hero slides on the mobile home screen. The photo lives in `media`
// via the same morph every other CMS model uses, so uploads reuse MediaService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sliders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->json('header_text');
            $table->json('location');
            $table->json('description_text');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sliders');
    }
};
