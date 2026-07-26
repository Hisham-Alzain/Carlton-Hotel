<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug'       => Str::slug($name),
            'name'       => ['en' => $name, 'ar' => $this->faker->unique()->words(2, true)],
            'icon'       => $this->faker->randomElement(['tv', 'safe', 'coffee', 'desk']),
            'is_active'  => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
