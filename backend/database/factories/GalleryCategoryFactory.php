<?php

namespace Database\Factories;

use App\Models\GalleryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryCategoryFactory extends Factory
{
    protected $model = GalleryCategory::class;

    public function definition(): array
    {
        return [
            'slug'       => $this->faker->unique()->slug(2),
            'name'       => ['en' => $this->faker->words(2, true), 'ar' => 'قسم ' . $this->faker->word()],
            'is_active'  => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
