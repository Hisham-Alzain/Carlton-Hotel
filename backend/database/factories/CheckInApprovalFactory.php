<?php

namespace Database\Factories;

use App\Models\CheckInApproval;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckInApprovalFactory extends Factory
{
    protected $model = CheckInApproval::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'status'         => CheckInApproval::STATUS_PENDING,
        ];
    }
}
