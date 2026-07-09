<?php

namespace Database\Factories;

use App\Models\DiningVenue;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiningVenueFactory extends Factory
{
    protected $model = DiningVenue::class;

    public function definition(): array
    {
        return [
            'name'         => ['en' => $this->faker->unique()->word(), 'ar' => $this->faker->unique()->word()],
            'description'  => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'cuisine_type' => ['en' => 'International', 'ar' => 'دولي'],
            'location'     => ['en' => 'Level 1', 'ar' => 'الطابق الأول'],
            'hours'        => ['en' => '7am–11pm', 'ar' => '٧ص–١١م'],
            'is_active'    => true,
            'sort_order'   => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
