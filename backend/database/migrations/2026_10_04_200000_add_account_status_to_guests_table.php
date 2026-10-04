<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9.1 (D-05): a guest who deletes their account is anonymized, never
 * removed — the row anchors reservations, folios, payments and tickets.
 * `account_status` (GuestAccountStatus) + `account_deleted_at` record it;
 * existing rows read `active` through the default. `down()` drops only these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->string('account_status', 16)->default('active')->after('preferred_locale');
            $table->timestamp('account_deleted_at')->nullable()->after('account_status');
            $table->index('account_status', 'guests_account_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->dropIndex('guests_account_status_index');
        });

        Schema::table('guests', function (Blueprint $table): void {
            $table->dropColumn(['account_status', 'account_deleted_at']);
        });
    }
};
