<?php

namespace Database\Seeders;

use App\Models\DiningVenue;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PoolCabana;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->spaServices();
        $this->restaurantTables();
        $this->poolCabanas();
        $this->transfers();
        $this->menu();
    }

    private function spaServices(): void
    {
        $services = [
            ['en' => 'Deep Tissue Massage', 'ar' => 'مساج الأنسجة العميقة', 'min' => 60, 'price' => 80],
            ['en' => 'Hot Stone Therapy', 'ar' => 'علاج الأحجار الساخنة', 'min' => 90, 'price' => 110],
            ['en' => 'Classic Facial', 'ar' => 'تنظيف بشرة كلاسيكي', 'min' => 45, 'price' => 60],
            ['en' => 'Aromatherapy Session', 'ar' => 'جلسة العلاج بالروائح', 'min' => 30, 'price' => 45],
        ];
        foreach ($services as $s) {
            SpaService::create(['name' => ['en' => $s['en'], 'ar' => $s['ar']], 'duration_minutes' => $s['min'], 'price_usd' => $s['price'], 'is_active' => true]);
        }
    }

    private function restaurantTables(): void
    {
        foreach (DiningVenue::all() as $venue) {
            foreach (range(1, 4) as $n) {
                RestaurantTable::create([
                    'dining_venue_id' => $venue->id,
                    'table_number' => strtoupper(substr($venue->getTranslation('name', 'en'), 0, 1)) . "-{$n}",
                    'capacity' => [2, 2, 4, 6][($n - 1) % 4],
                    'is_active' => true,
                ]);
            }
        }
    }

    private function poolCabanas(): void
    {
        foreach (range(1, 5) as $n) {
            PoolCabana::create(['name' => ['en' => "Cabana {$n}", 'ar' => "كابانا {$n}"], 'capacity' => 4, 'price_usd' => 120, 'is_active' => true]);
        }
    }

    private function transfers(): void
    {
        $transfers = [
            ['en' => 'Airport Transfer (Sedan)', 'ar' => 'نقل المطار (سيدان)', 'price' => 35],
            ['en' => 'Airport Transfer (SUV)', 'ar' => 'نقل المطار (SUV)', 'price' => 55],
            ['en' => 'City Tour Shuttle', 'ar' => 'جولة المدينة', 'price' => 25],
        ];
        foreach ($transfers as $t) {
            Transfer::create(['name' => ['en' => $t['en'], 'ar' => $t['ar']], 'price_usd' => $t['price'], 'is_active' => true]);
        }
    }

    /**
     * Menus are per-restaurant. Every active venue gets the same four category
     * types the mobile filter chips expect (breakfast / starters / main /
     * dessert), each keyed by a stable slug.
     */
    private function menu(): void
    {
        $menu = [
            ['slug' => 'breakfast', 'en' => 'Breakfast', 'ar' => 'فطور', 'items' => [
                ['en' => 'Carlton Full Breakfast', 'ar' => 'فطور كارلتون الكامل', 'price' => 14, 'vegan' => false],
                ['en' => 'Labneh & Za\'atar Plate',  'ar' => 'طبق لبنة وزعتر',     'price' => 9,  'vegan' => true],
                ['en' => 'Fresh Fruit Bowl',         'ar' => 'سلطة فواكه طازجة',   'price' => 7,  'vegan' => true],
            ]],
            ['slug' => 'starters', 'en' => 'Starters', 'ar' => 'مقبلات', 'items' => [
                ['en' => 'Hummus with Pita',     'ar' => 'حمص مع الخبز',   'price' => 8,  'vegan' => true],
                ['en' => 'Fattoush Salad',       'ar' => 'سلطة فتوش',      'price' => 9,  'vegan' => true],
                ['en' => 'Stuffed Grape Leaves', 'ar' => 'ورق عنب محشي',   'price' => 10, 'vegan' => true],
            ]],
            ['slug' => 'main', 'en' => 'Main Courses', 'ar' => 'أطباق رئيسية', 'items' => [
                ['en' => 'Grilled Kebab Platter', 'ar' => 'صحن كباب مشوي',    'price' => 22, 'vegan' => false],
                ['en' => 'Chicken Shawarma',      'ar' => 'شاورما دجاج',       'price' => 16, 'vegan' => false],
                ['en' => 'Vegetable Maqluba',     'ar' => 'مقلوبة خضار',       'price' => 18, 'vegan' => true],
            ]],
            ['slug' => 'dessert', 'en' => 'Desserts', 'ar' => 'حلويات', 'items' => [
                ['en' => 'Baklava',      'ar' => 'بقلاوة',      'price' => 7, 'vegan' => false],
                ['en' => 'Kunafa',       'ar' => 'كنافة',       'price' => 9, 'vegan' => false],
                ['en' => 'Rice Pudding', 'ar' => 'رز بحليب',    'price' => 6, 'vegan' => true],
            ]],
        ];

        foreach (DiningVenue::where('is_active', true)->get() as $venue) {
            foreach ($menu as $i => $c) {
                $category = MenuCategory::create([
                    'dining_venue_id' => $venue->id,
                    'slug'            => $c['slug'],
                    'name'            => ['en' => $c['en'], 'ar' => $c['ar']],
                    'sort_order'      => $i,
                    'is_active'       => true,
                ]);

                foreach ($c['items'] as $item) {
                    MenuItem::create([
                        'menu_category_id' => $category->id,
                        'name'        => ['en' => $item['en'], 'ar' => $item['ar']],
                        'description' => ['en' => 'A house specialty.', 'ar' => 'من تخصصات المطعم.'],
                        'price_usd'   => $item['price'],
                        'is_vegan'    => $item['vegan'],
                        'is_active'   => true,
                    ]);
                }
            }
        }
    }
}
