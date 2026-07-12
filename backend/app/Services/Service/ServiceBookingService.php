<?php

namespace App\Services\Service;

use App\Actions\Service\CreateServiceBookingAction;
use App\Exceptions\NotFoundException;
use App\Models\Guest;
use App\Support\GuestEntitlement;
use Illuminate\Database\Eloquent\Relations\Relation;

class ServiceBookingService
{
    public function __construct(private readonly CreateServiceBookingAction $action) {}

    public function create(Guest $guest, array $data): array
    {
        $reservation = GuestEntitlement::currentReservation($guest);

        $modelClass = Relation::getMorphedModel($data['bookable_type']);
        $bookableId = $modelClass ? $modelClass::where('uuid', $data['bookable_uuid'])->value('id') : null;

        if (! $bookableId) {
            throw new NotFoundException(__('custom.errors.not_found'));
        }

        return $this->action->handle($guest, $reservation, [
            'bookable_type' => $data['bookable_type'],
            'bookable_id'   => $bookableId,
            'scheduled_at'  => $data['scheduled_at'],
            'notes'         => $data['notes'] ?? null,
        ]);
    }
}
