<?php

namespace App\Actions\Service;

use App\Models\Guest;
use App\Models\Reservation;

class RequestTransportAction
{
    public function __construct(private readonly PlaceServiceRequestAction $placeServiceRequest) {}

    public function handle(Guest $guest, Reservation $reservation, ?string $notes = null): array
    {
        return $this->placeServiceRequest->handle($guest, $reservation, [
            'type'  => 'transport',
            'notes' => $notes,
        ]);
    }
}
