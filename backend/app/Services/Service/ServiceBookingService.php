<?php

namespace App\Services\Service;

use App\Actions\Service\CreateServiceBookingAction;
use App\Actions\Service\ReserveTableAction;
use App\Exceptions\NotFoundException;
use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\Reservation;
use App\Support\GuestEntitlement;
use Illuminate\Database\Eloquent\Relations\Relation;

class ServiceBookingService
{
    public function __construct(
        private readonly CreateServiceBookingAction $action,
        private readonly ReserveTableAction         $reserveTable,
    ) {}

    /**
     * Restaurant table booking. Unlike create(), the guest names a venue and a
     * party size — the action picks the table.
     */
    public function reserveTable(Guest $guest, Reservation $reservation, DiningVenue $venue, array $data): array
    {
        return $this->reserveTable->handle($guest, $reservation, $venue, $data);
    }

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
