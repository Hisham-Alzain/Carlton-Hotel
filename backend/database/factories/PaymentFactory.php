<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payable_type' => Reservation::class,
            'payable_id'   => Reservation::factory(),
            'method'       => $this->faker->randomElement(['cash', 'on_arrival']),
            'amount_usd'   => $this->faker->randomFloat(2, 10, 1000),
            'recorded_by'  => User::factory(),
            'note'         => null,
            'status'       => 'completed',
        ];
    }
}
