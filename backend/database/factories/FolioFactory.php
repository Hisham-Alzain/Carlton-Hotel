<?php

namespace Database\Factories;

use App\Models\Folio;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class FolioFactory extends Factory
{
    protected $model = Folio::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'status'         => Folio::STATUS_OPEN,
            'subtotal_usd'   => 0,
            'total_usd'      => 0,
        ];
    }
}
