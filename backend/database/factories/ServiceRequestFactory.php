<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
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
            'department'     => Department::KITCHEN,
            'status'         => ServiceRequestStatus::NEW,
            'priority'       => ServiceRequestPriority::NORMAL,
        ];
    }
}
