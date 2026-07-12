<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'menu_category_id' => MenuCategory::factory(),
            'name'             => ['en' => $this->faker->words(3, true), 'ar' => 'صنف'],
            'description'      => ['en' => $this->faker->sentence(), 'ar' => 'وصف'],
            'price_usd'        => $this->faker->randomFloat(2, 5, 60),
            'is_active'        => true,
        ];
    }
}
