<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (D-01, D-05, D-06): staff-side state on an event inquiry.
 *
 * - `staff_notes`: internal notes. `notes` stays the guest's RFP brief and is
 *   never written by staff routes (D-01).
 * - `deposit_status` + `deposit_paid_at`: current deposit state over the
 *   `payments` ledger (folio precedent: status + settled_at). The amount lives
 *   only on the payment row — no `deposit_usd` copy (3NF, D-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_inquiries', function (Blueprint $table): void {
            $table->text('staff_notes')->nullable()->after('notes');
            $table->string('deposit_status', 20)->default('unpaid');
            $table->timestamp('deposit_paid_at')->nullable();

            $table->index('deposit_status');
        });
    }

    public function down(): void
    {
        Schema::table('event_inquiries', function (Blueprint $table): void {
            $table->dropIndex(['deposit_status']);
            $table->dropColumn(['staff_notes', 'deposit_status', 'deposit_paid_at']);
        });
    }
};
