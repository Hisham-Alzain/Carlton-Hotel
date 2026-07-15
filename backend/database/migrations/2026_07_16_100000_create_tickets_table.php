<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            // Plain column, not yet an FK — chatbot_sessions doesn't exist until P11.
            $table->unsignedBigInteger('chatbot_session_id')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('category');
            $table->string('status')->default('open');
            $table->unsignedTinyInteger('priority')->default(2);
            $table->string('department');
            $table->string('source')->default('chatbot');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('guest_id');
            $table->index('chatbot_session_id');
            $table->index('status');
            $table->index('department');
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
