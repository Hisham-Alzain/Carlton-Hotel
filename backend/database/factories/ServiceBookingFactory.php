<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use App\Models\SpaService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceBookingFactory extends Factory
{
    protected $model = ServiceBooking::class;

    public function definition(): array
    {
        return [
            'guest_id'       => Guest::factory(),
            'reservation_id' => Reservation::factory(),
            'bookable_type'  => 'spa_service',
            'bookable_id'    => SpaService::factory(),
            'scheduled_at'   => $this->faker->dateTimeBetween('+1 day', '+10 days'),
            'status'         => ServiceBooking::STATUS_PENDING,
        ];
    }
}
