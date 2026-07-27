<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Menus were global; mobile shows a menu *per restaurant* with type chips
// (breakfast / starters / main / dessert). `slug` is the stable filter key the
// app sends, so renaming a category's display name never breaks the client.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->foreignId('dining_venue_id')->nullable()->after('uuid')
                ->constrained('dining_venues')->cascadeOnDelete();
            $table->string('slug', 64)->nullable()->after('dining_venue_id');

            $table->index('dining_venue_id');
            $table->unique(['dining_venue_id', 'slug'], 'menu_categories_venue_slug_unique');
        });

        // Backfill: existing categories were global, so hand them to the
        // lowest-sorted venue rather than orphaning them from every menu.
        $venueId = DB::table('dining_venues')->orderBy('sort_order')->orderBy('id')->value('id');

        DB::table('menu_categories')->select('id', 'name')->orderBy('id')
            ->chunk(100, function ($categories) use ($venueId) {
                foreach ($categories as $category) {
                    $name = json_decode($category->name ?? '{}', true);
                    $slug = Str::slug($name['en'] ?? '') ?: "category-{$category->id}";

                    DB::table('menu_categories')->where('id', $category->id)->update([
                        'dining_venue_id' => $venueId,
                        'slug'            => $slug,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropUnique('menu_categories_venue_slug_unique');
            $table->dropConstrainedForeignId('dining_venue_id');
            $table->dropColumn('slug');
        });
    }
};
