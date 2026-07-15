<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named guest_notifications (not notifications) — User already carries
        // Laravel's Notifiable trait, which owns the plain "notifications" table
        // with its own schema; this is a distinct, domain-specific table.
        Schema::create('guest_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('department')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('guest_id');
            $table->index('department');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_notifications');
    }
};
