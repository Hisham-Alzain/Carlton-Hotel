<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (D-04): folio_items becomes an append-only ledger. Posted rows carry
 * quantity/unit price/poster/reason; credits point at the row they reverse;
 * reconcile keys on (folio_id, source_type, source_id, source_line). NULL
 * source_id values never collide in the unique index, so any number of
 * manual/credit rows fit.
 *
 * `reverses_item_id` is restrictOnDelete: the database itself refuses to drop a
 * charge a credit points at, backing up GenerateFolioAction's freeze rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_items', function (Blueprint $table): void {
            $table->string('source_type', 32)->change();
            $table->unsignedSmallInteger('quantity')->default(1)->after('description');
            $table->decimal('unit_price_usd', 10, 2)->nullable()->after('quantity');
            $table->unsignedSmallInteger('source_line')->default(0)->after('source_id');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->foreignId('reverses_item_id')->nullable()->constrained('folio_items')->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable();

            $table->index('posted_by');
            $table->index('reverses_item_id');
            $table->unique(['folio_id', 'idempotency_key']);
            $table->unique(['folio_id', 'source_type', 'source_id', 'source_line']);
        });
    }

    public function down(): void
    {
        Schema::table('folio_items', function (Blueprint $table): void {
            $table->dropUnique(['folio_id', 'source_type', 'source_id', 'source_line']);
            $table->dropUnique(['folio_id', 'idempotency_key']);
            $table->dropIndex(['reverses_item_id']);
            $table->dropIndex(['posted_by']);
        });

        Schema::table('folio_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reverses_item_id');
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn(['quantity', 'unit_price_usd', 'source_line', 'reason', 'idempotency_key']);
        });

        Schema::table('folio_items', function (Blueprint $table): void {
            $table->string('source_type')->change();
        });
    }
};
