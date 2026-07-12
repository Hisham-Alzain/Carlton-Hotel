<?php

namespace App\Actions\Service;

use App\Events\ServiceRequestPlaced;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

class PlaceServiceRequestAction
{
    public function handle(Guest $guest, Reservation $reservation, array $data): array
    {
        $request = DB::transaction(function () use ($guest, $reservation, $data) {
            $department = ServiceRequest::TYPE_DEPARTMENTS[$data['type']] ?? ServiceRequest::DEPARTMENT_CONCIERGE;

            return ServiceRequest::create([
                'guest_id'       => $guest->id,
                'reservation_id' => $reservation->id,
                'type'           => $data['type'],
                'department'     => $department,
                'status'         => ServiceRequest::STATUS_NEW,
                'priority'       => $data['priority'] ?? ServiceRequest::PRIORITY_NORMAL,
                'notes'          => $data['notes'] ?? null,
            ]);
        });

        // Firestore mirror stubbed until P9 — writes MySQL now, live queue sync lands later (same seam as P6's InquirySubmitted).
        event(new ServiceRequestPlaced($request));

        return ['data' => $request, 'code' => 201];
    }
}
