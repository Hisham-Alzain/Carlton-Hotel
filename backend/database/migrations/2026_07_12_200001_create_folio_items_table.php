<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount_usd', 10, 2);
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->index('folio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_items');
    }
};
