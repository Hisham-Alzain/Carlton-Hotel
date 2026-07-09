<?php

namespace Database\Factories;

use App\Models\EventSpace;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventSpaceFactory extends Factory
{
    protected $model = EventSpace::class;

    public function definition(): array
    {
        return [
            'name'        => ['en' => $this->faker->unique()->word(), 'ar' => $this->faker->unique()->word()],
            'description' => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'capacity'    => $this->faker->numberBetween(20, 500),
            'location'    => ['en' => 'Conference Level', 'ar' => 'طابق المؤتمرات'],
            'amenities'   => ['en' => 'Projector, WiFi, Catering', 'ar' => 'جهاز عرض، واي فاي، تقديم طعام'],
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
