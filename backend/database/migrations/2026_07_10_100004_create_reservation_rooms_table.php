<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete()->index();
            $table->foreignId('room_type_id')->constrained('room_types')->index();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete()->index();
            $table->decimal('price_usd', 10, 2); // snapshotted at booking time
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_rooms');
    }
};
