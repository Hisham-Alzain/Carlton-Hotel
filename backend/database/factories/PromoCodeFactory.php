<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'code'       => strtoupper($this->faker->unique()->bothify('????##')),
            'type'       => PromoCode::TYPE_PERCENTAGE,
            'value'      => $this->faker->randomFloat(2, 5, 25),
            'expires_at' => now()->addMonth(),
            'max_uses'   => null,
            'used_count' => 0,
            'is_active'  => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => ['max_uses' => 1, 'used_count' => 1]);
    }
}
