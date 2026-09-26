<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 (D-10, D-11): online check-in and a display-only digital key; NOT
 * lock-grade.
 *
 * Every column is nullable and additive. The key columns are written only by
 * IssueDigitalKeyAction / RevokeDigitalKeyAction through forceFill(); none is
 * mass-assignable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Hotel-local wall time the guest expects to arrive (H:i).
            $table->time('arrival_time')->nullable();
            $table->timestamp('online_check_in_submitted_at')->nullable();
            // Encrypted at rest by the model's `encrypted` cast.
            $table->string('digital_key_code', 255)->nullable();
            // HMAC-SHA256 of the code with APP_KEY, for a future verifier.
            $table->string('digital_key_hash', 64)->nullable()->index();
            $table->timestamp('digital_key_issued_at')->nullable();
            // The expiry sweep filters on it every 15 minutes.
            $table->timestamp('digital_key_expires_at')->nullable()->index();
            $table->timestamp('digital_key_revoked_at')->nullable();
            // checked_out | cancelled | rejected | expired
            $table->string('digital_key_revoked_reason', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // SQLite refuses to drop an indexed column, so the indexes go first.
            $table->dropIndex(['digital_key_hash']);
            $table->dropIndex(['digital_key_expires_at']);
            $table->dropColumn([
                'arrival_time',
                'online_check_in_submitted_at',
                'digital_key_code',
                'digital_key_hash',
                'digital_key_issued_at',
                'digital_key_expires_at',
                'digital_key_revoked_at',
                'digital_key_revoked_reason',
            ]);
        });
    }
};
