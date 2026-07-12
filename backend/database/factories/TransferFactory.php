<?php

namespace Database\Factories;

use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'name'      => ['en' => 'Airport Transfer', 'ar' => 'نقل المطار'],
            'price_usd' => $this->faker->randomFloat(2, 15, 80),
            'is_active' => true,
        ];
    }
}
