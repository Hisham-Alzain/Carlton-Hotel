<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'currency' => 'SYP',
            'rate' => '13000.000000',
            'note' => null,
            'set_by' => User::factory(),
        ];
    }
}
