<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationRoomFactory extends Factory
{
    protected $model = ReservationRoom::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'room_type_id'   => RoomType::factory(),
            'room_id'        => null,
            'price_usd'      => $this->faker->randomFloat(2, 50, 500),
        ];
    }
}
