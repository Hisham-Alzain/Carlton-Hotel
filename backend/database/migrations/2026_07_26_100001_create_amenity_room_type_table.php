<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenity_room_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            // Marks the subset surfaced as "highlights" on the room card. When a
            // room type flags none, the resource falls back to the first four.
            $table->boolean('is_highlight')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['amenity_id', 'room_type_id'], 'amenity_room_type_unique');
            $table->index('room_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_room_type');
    }
};
