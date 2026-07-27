<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceItemFactory extends Factory
{
    protected $model = ServiceItem::class;

    public function definition(): array
    {
        return [
            'service_category_id' => ServiceCategory::factory(),
            'name'             => ['en' => $this->faker->unique()->words(2, true), 'ar' => 'خدمة فرعية'],
            'description'      => ['en' => $this->faker->sentence(), 'ar' => 'وصف'],
            'expected_minutes' => 30,
            'price_usd'        => null,
            'is_default'       => false,
            'is_active'        => true,
            'sort_order'       => 0,
        ];
    }

    public function priced(float $price = 20.00): static
    {
        return $this->state(['price_usd' => $price]);
    }

    public function defaultItem(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
