<?php

namespace Database\Factories;

use App\Models\SpaService;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpaServiceFactory extends Factory
{
    protected $model = SpaService::class;

    public function definition(): array
    {
        return [
            'name'             => ['en' => $this->faker->words(2, true), 'ar' => 'خدمة سبا'],
            'duration_minutes' => $this->faker->randomElement([30, 60, 90]),
            'price_usd'        => $this->faker->randomFloat(2, 20, 200),
            'is_active'        => true,
        ];
    }
}
