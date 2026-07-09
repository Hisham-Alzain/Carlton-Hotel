<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Booking\AssignRoomRequest;
use App\Http\Resources\Booking\ReservationResource;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Booking\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends BaseController
{
    public function __construct(private readonly ReservationService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->adminIndex()['data'], ReservationResource::class, $request);
    }

    public function show(Reservation $reservation, Request $request): JsonResponse
    {
        $result = $this->service->show($reservation);
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function confirm(Reservation $reservation, Request $request): JsonResponse
    {
        $result = $this->service->confirm($reservation);
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function cancel(Reservation $reservation, Request $request): JsonResponse
    {
        $this->service->cancel($reservation);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    public function assignRoom(AssignRoomRequest $request, Reservation $reservation): JsonResponse
    {
        $room   = Room::where('uuid', $request->validated('room_uuid'))->firstOrFail();
        $result = $this->service->assignRoom($reservation, $room);
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
