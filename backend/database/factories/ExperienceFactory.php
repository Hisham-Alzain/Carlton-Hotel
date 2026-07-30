<?php

namespace Database\Factories;

use App\Models\Experience;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExperienceFactory extends Factory
{
    protected $model = Experience::class;

    public function definition(): array
    {
        return [
            'slug'             => $this->faker->unique()->slug(3),
            'title'            => ['en' => $this->faker->sentence(4), 'ar' => 'تجربة ' . $this->faker->word()],
            'description'      => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'category'         => $this->faker->randomElement(['gastronomy', 'culture', 'privilege']),
            'duration_minutes' => 120,
            'price_usd'        => null,
            'is_active'        => true,
            'sort_order'       => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
