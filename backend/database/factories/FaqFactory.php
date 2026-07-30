<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'category'   => null,
            'question'   => ['en' => $this->faker->unique()->sentence().'?', 'ar' => $this->faker->unique()->sentence().'؟'],
            'answer'     => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'is_active'  => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
