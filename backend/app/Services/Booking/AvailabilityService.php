<?php

namespace App\Services\Booking;

use App\Actions\Booking\CheckAvailabilityAction;
use App\Models\RoomType;

class AvailabilityService
{
    public function __construct(private readonly CheckAvailabilityAction $action) {}

    public function check(string $roomTypeUuid, string $checkIn, string $checkOut): array
    {
        $roomType  = RoomType::where('uuid', $roomTypeUuid)->where('is_active', true)->firstOrFail();
        $available = $this->action->availableCount($roomType->id, $checkIn, $checkOut);

        return [
            'data' => [
                'room_type_uuid' => $roomType->uuid,
                'check_in'       => $checkIn,
                'check_out'      => $checkOut,
                'available'      => $available > 0,
                'rooms_available'=> $available,
            ],
            'code' => 200,
        ];
    }
}
