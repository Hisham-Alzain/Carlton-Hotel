<?php

namespace App\Actions\Service;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use Illuminate\Support\Facades\DB;

class CreateServiceBookingAction
{
    public function handle(Guest $guest, Reservation $reservation, array $data): array
    {
        $booking = DB::transaction(fn () => ServiceBooking::create([
            'guest_id'       => $guest->id,
            'reservation_id' => $reservation->id,
            'bookable_type'  => $data['bookable_type'],
            'bookable_id'    => $data['bookable_id'],
            'scheduled_at'   => $data['scheduled_at'],
            'status'         => ServiceBooking::STATUS_PENDING,
            'notes'          => $data['notes'] ?? null,
        ]));

        return ['data' => $booking->load('bookable'), 'code' => 201];
    }
}
