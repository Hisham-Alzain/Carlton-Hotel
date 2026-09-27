<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (D-13): folio payments carry the client Idempotency-Key; unique per
 * payable. Legacy rows keep NULL, which never collides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->after('status');
            $table->unique(['payable_type', 'payable_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['payable_type', 'payable_id', 'idempotency_key']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('idempotency_key');
        });
    }
};
