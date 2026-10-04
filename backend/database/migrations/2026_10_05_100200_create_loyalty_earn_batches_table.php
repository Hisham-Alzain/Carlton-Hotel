<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (M-9, LOY-04): earn lots, consumed FIFO and expired per batch.
 *
 * Each credit of points is one batch with its own `expires_at`;
 * `points_remaining` is what is still spendable. The UNIQUE
 * (`folio_id`, `source`) index is the database backstop for "points are earned
 * once per settled folio": a retried settlement collides here (NULL folios -
 * manual and refund batches - never collide). Indexes serve the FIFO
 * consumption read (guest, status, expires_at), the daily expiry sweep
 * (status, expires_at) and the expiry-warning sweep
 * (status, expiry_warned_at, expires_at). Every FK is restrictOnDelete so the
 * points history cannot vanish through a cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_earn_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->string('source', 16);
            $table->foreignId('folio_id')->nullable()->constrained('folios')->restrictOnDelete();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedInteger('points');
            $table->unsignedInteger('points_remaining');
            $table->timestamp('earned_at');
            $table->timestamp('expires_at');
            $table->timestamp('expiry_warned_at')->nullable();
            $table->string('status', 16);
            $table->timestamps();

            // Also serves as the guest_id FK index (leftmost prefix).
            $table->index(['guest_id', 'status', 'expires_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'expiry_warned_at', 'expires_at']);
            $table->index('folio_id');
            $table->index('awarded_by');
            $table->unique(['folio_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_earn_batches');
    }
};
