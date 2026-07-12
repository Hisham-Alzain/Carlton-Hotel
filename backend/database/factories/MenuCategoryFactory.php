<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return [
            'name'       => ['en' => $this->faker->words(2, true), 'ar' => 'قسم القائمة'],
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_active'  => true,
        ];
    }
}
