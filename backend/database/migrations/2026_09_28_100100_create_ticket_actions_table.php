<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (D-01): the append-only ticket timeline. One row per ticket event
 * (created, status change, assignment, escalation, reply, recovery), written by
 * the ticket single writers in the same transaction as the ticket change.
 *
 * Actor and target FKs are restrictOnDelete (council A6): a user with ticket
 * history cannot be hard-deleted — staff are deactivated, never deleted.
 * `message_id` is reserved for TICKET-08 (guest-visible replies): never
 * written, never exposed this milestone. No `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
            $table->index('user_id');
            $table->index('target_user_id');
            $table->index('type');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_actions');
    }
};
