<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'title'       => ['en' => $this->faker->unique()->sentence(3), 'ar' => $this->faker->unique()->sentence(3)],
            'description' => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'terms'       => ['en' => $this->faker->sentence(), 'ar' => $this->faker->sentence()],
            'valid_from'  => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
