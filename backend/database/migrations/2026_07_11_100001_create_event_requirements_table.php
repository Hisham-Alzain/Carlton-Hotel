<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('event_inquiry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_requirements');
    }
};
