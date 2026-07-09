<?php

namespace App\Services\Booking;

use App\Actions\Booking\QuoteReservationAction;
use App\Models\RoomType;

class PricingService
{
    public function __construct(private readonly QuoteReservationAction $action) {}

    public function quote(string $roomTypeUuid, string $checkIn, string $checkOut, ?string $promoCode = null): array
    {
        $roomType = RoomType::where('uuid', $roomTypeUuid)->where('is_active', true)->firstOrFail();
        $pricing  = $this->action->handle($roomType, $checkIn, $checkOut, $promoCode);

        return ['data' => $pricing, 'code' => 200];
    }
}
