<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'guest_id'       => Guest::factory(),
            'reservation_id' => Reservation::factory(),
            'type'           => 'room_service',
            'department'     => ServiceRequest::DEPARTMENT_KITCHEN,
            'status'         => ServiceRequest::STATUS_NEW,
            'priority'       => ServiceRequest::PRIORITY_NORMAL,
        ];
    }
}
