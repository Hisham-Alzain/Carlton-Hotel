<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The guest app's transfer sheet shows a short blurb and the vehicle's seat count,
// neither of which the table carried.
//
// `description` is Spatie translatable copy (en/ar, like `name`). `max_passengers`
// is the vehicle's seat count as shown to guests; it is informational, not an
// inventory constraint.
//
// Both are nullable and additive: existing rows have no value and it is honestly
// unknown, so nothing is backfilled. Not indexed — nothing filters or sorts on
// either.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->json('description')->nullable()->after('name');
            $table->unsignedSmallInteger('max_passengers')->nullable()->after('price_usd');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['description', 'max_passengers']);
        });
    }
};
