<?php

namespace App\Services\Service;

use App\Actions\Service\PlaceServiceRequestAction;
use App\Models\Guest;
use App\Models\ServiceRequest;
use App\Support\GuestEntitlement;

class ServiceRequestService
{
    public function __construct(private readonly PlaceServiceRequestAction $action) {}

    public function place(Guest $guest, array $data): array
    {
        $reservation = GuestEntitlement::currentReservation($guest);
        return $this->action->handle($guest, $reservation, $data);
    }

    public function myRequests(Guest $guest): array
    {
        return ['data' => ServiceRequest::where('guest_id', $guest->id)
            ->latest()
            ->paginate(15), 'code' => 200];
    }
}
