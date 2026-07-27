<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The menu card shows a vegan badge. Photos go through the shared media morph,
// so no column is needed for them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('is_vegan')->default(false)->after('price_usd')->index();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['is_vegan']);
            $table->dropColumn('is_vegan');
        });
    }
};
