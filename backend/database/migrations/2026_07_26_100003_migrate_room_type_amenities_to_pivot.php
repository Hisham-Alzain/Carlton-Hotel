<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// `room_types.amenities` was a free-text JSON array. Amenities are now a seeded
// catalog joined through `amenity_room_type`, so lift the existing strings into
// real rows. The old column is left in place (additive-only) but is no longer
// read by any resource.
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('amenities')->pluck('id', 'slug')->all();

        DB::table('room_types')->select('id', 'amenities')->orderBy('id')
            ->chunk(100, function ($roomTypes) use (&$existing) {
                foreach ($roomTypes as $roomType) {
                    $names = json_decode($roomType->amenities ?? '[]', true);
                    if (! is_array($names)) {
                        continue;
                    }

                    $sort = 0;
                    foreach ($names as $name) {
                        if (! is_string($name) || trim($name) === '') {
                            continue;
                        }
                        $slug = Str::slug($name);
                        if ($slug === '') {
                            continue;
                        }

                        if (! isset($existing[$slug])) {
                            $existing[$slug] = DB::table('amenities')->insertGetId([
                                'uuid'       => (string) Str::uuid(),
                                'slug'       => $slug,
                                // No AR copy exists for legacy strings — mirror EN so the
                                // key is present in both locales until CMS edits it.
                                'name'       => json_encode(['en' => $name, 'ar' => $name], JSON_UNESCAPED_UNICODE),
                                'icon'       => null,
                                'is_active'  => true,
                                'sort_order' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('amenity_room_type')->updateOrInsert(
                            ['amenity_id' => $existing[$slug], 'room_type_id' => $roomType->id],
                            ['is_highlight' => $sort < 4, 'sort_order' => $sort],
                        );
                        $sort++;
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('amenity_room_type')->delete();
    }
};
