<?php

namespace App\Actions\Service;

use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Events\ServiceRequestPlaced;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ServiceItem;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

class PlaceServiceRequestAction
{
    public function handle(Guest $guest, Reservation $reservation, array $data): array
    {
        $item = $this->resolveItem($data);

        // `type` and `department` stay snapshotted strings even for catalog
        // requests: the P10 operations queue keys on them, and deactivating or
        // deleting a catalog item must never rewrite the history of a request
        // staff already worked.
        $type       = $item?->category->code ?? $data['type'];
        $department = $item?->category->department ?? Department::forServiceType($type);

        $request = DB::transaction(fn () => ServiceRequest::create([
            'guest_id'        => $guest->id,
            'reservation_id'  => $reservation->id,
            'service_item_id' => $item?->id,
            'type'            => $type,
            'department'      => $department,
            'status'          => ServiceRequestStatus::NEW,
            'priority'        => $data['priority'] ?? ServiceRequestPriority::NORMAL,
            'notes'           => $data['notes'] ?? null,
        ]));

        // Firestore mirror stubbed until P9 — writes MySQL now, live queue sync lands later (same seam as P6's InquirySubmitted).
        event(new ServiceRequestPlaced($request));

        return ['data' => $request->load('serviceItem.category'), 'code' => 201];
    }

    private function resolveItem(array $data): ?ServiceItem
    {
        if (empty($data['service_item_uuid'])) {
            return null;
        }

        return ServiceItem::with('category')
            ->where('uuid', $data['service_item_uuid'])
            ->where('is_active', true)
            ->first();
    }
}
