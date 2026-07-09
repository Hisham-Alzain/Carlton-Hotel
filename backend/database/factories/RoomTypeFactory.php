<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        return [
            'name'           => ['en' => $this->faker->unique()->words(2, true), 'ar' => $this->faker->unique()->words(2, true)],
            'description'    => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'amenities'      => ['WiFi', 'Air Conditioning', 'Mini Bar'],
            'base_occupancy' => 2,
            'max_occupancy'  => 4,
            'size_sqm'       => 35.00,
            'base_price_usd' => 150.00,
            'is_active'      => true,
            'sort_order'     => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
