<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dining_venue_id')->nullable()->constrained('dining_venues')->nullOnDelete();
            $table->string('table_number');
            $table->unsignedInteger('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('dining_venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
