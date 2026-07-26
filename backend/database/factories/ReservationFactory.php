<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $checkOut = (clone $checkIn)->modify('+' . $this->faker->numberBetween(1, 7) . ' days');

        return [
            'guest_id'       => Guest::factory(),
            'booking_code'   => 'CARL-' . strtoupper($this->faker->unique()->lexify('????????')),
            'source'         => ReservationSource::DIRECT,
            'check_in'       => $checkIn->format('Y-m-d'),
            'check_out'      => $checkOut->format('Y-m-d'),
            'status'         => ReservationStatus::PENDING,
            'payment_method' => PaymentMethod::ON_ARRIVAL,
            'total_usd'      => $this->faker->randomFloat(2, 50, 500),
        ];
    }

    public function pendingVerification(): static
    {
        return $this->state(fn () => [
            'status'         => ReservationStatus::PENDING_VERIFICATION,
            'hold_expires_at'=> now()->addMinutes(5),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::CONFIRMED]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::CHECKED_IN]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::CANCELLED]);
    }

    public function expiredHold(): static
    {
        return $this->state(fn () => [
            'status'         => ReservationStatus::PENDING_VERIFICATION,
            'hold_expires_at'=> now()->subMinutes(1),
        ]);
    }
}
