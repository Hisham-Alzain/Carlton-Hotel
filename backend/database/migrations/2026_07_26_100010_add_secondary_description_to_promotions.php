<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The offer card shows two separate blocks of copy around the banner. `terms`
// already exists but means something else (legal small print), so the second
// block gets its own column rather than being overloaded onto it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->json('secondary_description')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('secondary_description');
        });
    }
};
