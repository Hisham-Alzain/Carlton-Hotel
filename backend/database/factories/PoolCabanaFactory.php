<?php

namespace Database\Factories;

use App\Models\PoolCabana;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoolCabanaFactory extends Factory
{
    protected $model = PoolCabana::class;

    public function definition(): array
    {
        return [
            'name'      => ['en' => 'Cabana ' . $this->faker->unique()->numberBetween(1, 50), 'ar' => 'كابانا'],
            'capacity'  => $this->faker->numberBetween(2, 6),
            'price_usd' => $this->faker->randomFloat(2, 50, 300),
            'is_active' => true,
        ];
    }
}
