<?php
// P1 STUB — minimal reservations table so LinkBookingCodeAction can look up booking_code.
// P4 supersedes/expands this. Do NOT add reservation logic here.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique(); // CARL-XXXXXX format
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete()->index();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            // TODO(P4): P4 will add all reservation fields
        });
    }

    public function down(): void { Schema::dropIfExists('reservations'); }
};
