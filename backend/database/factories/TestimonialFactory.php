<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'author_name'  => $this->faker->name(),
            'author_title' => ['en' => $this->faker->city(), 'ar' => $this->faker->city()],
            'quote'        => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'rating'       => 5,
            'is_active'    => true,
            'sort_order'   => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
