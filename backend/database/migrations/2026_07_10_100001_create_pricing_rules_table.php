<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete()->index();
            $table->string('scope'); // seasonal, weekend, holiday
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->string('modifier_type'); // percentage, flat
            $table->decimal('modifier_value', 8, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
