<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 (D-06): append-only staff notes on a guest; not activity-logged.
 *
 * The composite (guest_id, created_at) index serves the newest-first list and,
 * by the leftmost-prefix rule, the guest_id foreign key as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['guest_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_notes');
    }
};
