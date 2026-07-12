<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->timestamps();

            $table->index('guest_id');
            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_documents');
    }
};
