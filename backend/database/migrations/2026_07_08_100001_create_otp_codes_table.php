<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');         // E.164 phone or normalized email
            $table->string('channel');             // sms | whatsapp | email
            $table->string('code_hash');           // bcrypt — never plaintext
            $table->string('purpose');             // login | register | booking_link
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['identifier', 'purpose', 'expires_at'], 'otp_lookup_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('otp_codes'); }
};
