<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NoActiveReservationException;
use App\Exceptions\NotFoundException;
use App\Http\Requests\Service\ReserveTableRequest;
use App\Http\Resources\Service\ServiceBookingResource;
use App\Models\DiningVenue;
use App\Services\Service\ServiceBookingService;
use App\Support\GuestEntitlement;
use Illuminate\Http\JsonResponse;

class TableReservationController extends BaseController
{
    public function __construct(private readonly ServiceBookingService $service) {}

    public function store(ReserveTableRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        if (! $diningVenue->is_active) {
            throw new NotFoundException();
        }

        $guest = $request->user('guests');

        // Server-resolved, never taken from client input.
        $reservation = GuestEntitlement::currentReservation($guest)
            ?? throw new NoActiveReservationException(__('custom.errors.no_active_reservation'));

        $result = $this->service->reserveTable($guest, $reservation, $diningVenue, $request->validated());
        $result['data'] = new ServiceBookingResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }
}
