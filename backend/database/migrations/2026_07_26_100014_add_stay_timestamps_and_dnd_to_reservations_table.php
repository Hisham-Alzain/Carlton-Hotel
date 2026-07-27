<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `check_in` / `check_out` are DATE columns — they cannot answer "what time did
// I check in?". These record the actual transition moments. No backfill: rows
// that predate this migration return null and the mobile app falls back to the
// date.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('check_out');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            // Do-not-disturb is state, not work: an expiring timestamp rather
            // than a boolean, so a forgotten toggle clears itself.
            $table->dateTime('dnd_until')->nullable()->after('checked_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'checked_out_at', 'dnd_until']);
        });
    }
};
