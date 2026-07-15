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

    private function menu(): void
    {
        $menu = [
            'Starters' => [
                ['en' => 'Hummus with Pita', 'price' => 8],
                ['en' => 'Fattoush Salad', 'price' => 9],
                ['en' => 'Stuffed Grape Leaves', 'price' => 10],
            ],
            'Main Courses' => [
                ['en' => 'Grilled Kebab Platter', 'price' => 22],
                ['en' => 'Chicken Shawarma', 'price' => 16],
                ['en' => 'Seafood Mezze', 'price' => 28],
            ],
            'Desserts' => [
                ['en' => 'Baklava', 'price' => 7],
                ['en' => 'Kunafa', 'price' => 9],
                ['en' => 'Rice Pudding', 'price' => 6],
            ],
        ];

        foreach (array_values($menu) as $i => $items) {
            $categoryName = array_keys($menu)[$i];
            $category = MenuCategory::create(['name' => ['en' => $categoryName, 'ar' => $categoryName], 'sort_order' => $i, 'is_active' => true]);
            foreach ($items as $item) {
                MenuItem::create([
                    'menu_category_id' => $category->id,
                    'name' => ['en' => $item['en'], 'ar' => $item['en']],
                    'description' => ['en' => 'A house specialty.', 'ar' => 'من تخصصات المطعم.'],
                    'price_usd' => $item['price'], 'is_active' => true,
                ]);
            }
        }
    }
}
