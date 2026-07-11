<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_space_id')->nullable()->constrained('event_spaces')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('event_type');
            $table->date('event_date')->nullable();
            $table->unsignedInteger('expected_guests')->nullable();
            $table->decimal('budget_usd', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->string('department')->default('events');
            $table->timestamps();

            $table->index('status');
            $table->index('department');
            $table->index('assigned_user_id');
            $table->index('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_inquiries');
    }
};
