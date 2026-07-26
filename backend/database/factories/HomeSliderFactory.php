<?php

namespace Database\Factories;

use App\Models\HomeSlider;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomeSliderFactory extends Factory
{
    protected $model = HomeSlider::class;

    public function definition(): array
    {
        return [
            'header_text'      => ['en' => $this->faker->sentence(3), 'ar' => $this->faker->sentence(3)],
            'location'         => ['en' => $this->faker->city(), 'ar' => $this->faker->city()],
            'description_text' => ['en' => $this->faker->sentence(12), 'ar' => $this->faker->sentence(12)],
            'is_active'        => true,
            'sort_order'       => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
