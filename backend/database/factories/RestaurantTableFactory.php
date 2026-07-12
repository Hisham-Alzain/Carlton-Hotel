<?php

namespace Database\Factories;

use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    public function definition(): array
    {
        return [
            'dining_venue_id' => null,
            'table_number'    => 'T-' . $this->faker->unique()->numberBetween(1, 999),
            'capacity'        => $this->faker->numberBetween(2, 8),
            'is_active'       => true,
        ];
    }
}
