<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * The in-room amenity catalog the mobile room-details screen renders.
     *
     * `icon` is a stable key the app maps to its own icon set — never a URL, so
     * a design change does not need a data migration.
     */
    private const AMENITIES = [
        ['slug' => 'city-view-balcony',   'icon' => 'balcony',  'en' => 'City View Balcony',    'ar' => 'شرفة بإطلالة على المدينة'],
        ['slug' => 'jacuzzi',             'icon' => 'jacuzzi',  'en' => 'Jacuzzi',              'ar' => 'جاكوزي'],
        ['slug' => 'work-desk',           'icon' => 'desk',     'en' => 'Work Desk',            'ar' => 'مكتب عمل'],
        ['slug' => 'smart-tv',            'icon' => 'tv',       'en' => 'Smart TV',             'ar' => 'تلفاز ذكي'],
        ['slug' => 'in-room-safe',        'icon' => 'safe',     'en' => 'In-room Safe',         'ar' => 'خزنة داخل الغرفة'],
        ['slug' => 'tea-coffee-station',  'icon' => 'coffee',   'en' => 'Tea & Coffee Station', 'ar' => 'ركن الشاي والقهوة'],
    ];

    public function run(): void
    {
        $ids = [];

        foreach (self::AMENITIES as $index => $amenity) {
            $model = Amenity::updateOrCreate(
                ['slug' => $amenity['slug']],
                [
                    'name'       => ['en' => $amenity['en'], 'ar' => $amenity['ar']],
                    'icon'       => $amenity['icon'],
                    'is_active'  => true,
                    'sort_order' => $index,
                ],
            );
            $ids[] = $model->id;
        }

        // Attach the full catalog to every room type that has none yet, flagging
        // the first four as highlights. Existing pivot rows (from the legacy
        // JSON migration or CMS edits) are left alone.
        RoomType::doesntHave('amenityList')->get()->each(function (RoomType $roomType) use ($ids) {
            $roomType->amenityList()->attach(
                collect($ids)->mapWithKeys(fn (int $id, int $i) => [
                    $id => ['is_highlight' => $i < 4, 'sort_order' => $i],
                ])->all(),
            );
        });
    }
}
