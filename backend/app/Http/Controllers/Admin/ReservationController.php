<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Enums\CheckOutMode;
use App\Exceptions\ForbiddenException;
use App\Http\Requests\Booking\AssignRoomRequest;
use App\Http\Requests\Booking\CheckInReservationRequest;
use App\Http\Requests\Booking\CheckOutReservationRequest;
use App\Http\Requests\Booking\StoreAdminReservationRequest;
use App\Http\Requests\Booking\UpdateReservationNotesRequest;
use App\Http\Resources\Booking\ReservationResource;
use App\Models\Reservation;
use App\Services\Booking\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends BaseController
{
    public function __construct(private readonly ReservationService $service) {}

    // `?status` and `?folio_status` are whitelisted by ReservationFilter (D-10).
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->adminIndex($this->indexParams($request))['data'],
            ReservationResource::class,
            $request,
        );
    }

    public function show(Reservation $reservation, Request $request): JsonResponse
    {
        $result = $this->service->show($reservation);
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    // Front-desk booking: reception books for a guest at the desk or on the
    // phone. No OTP step — see ReservationService::adminStore().
    public function store(StoreAdminReservationRequest $request): JsonResponse
    {
        $result = $this->service->adminStore($request->validated());
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

    // Assigns or moves the reservation's room without changing its status.
    // `room_uuid` is optional — omit it to keep the reserved room. Check-in is
    // its own verb: POST /cms/reservations/{reservation}/check-in (D-03).
    public function assignRoom(AssignRoomRequest $request, Reservation $reservation): JsonResponse
    {
        $result = $this->service->assignRoom($reservation, $request->validated('room_uuid'));
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    // The desk's pick list for this reservation (D-12). Read only.
    public function availableRooms(Reservation $reservation, Request $request): JsonResponse
    {
        return $this->respondFromService($this->service->availableRooms($reservation), request: $request);
    }

    // Staff-only free-text notes; `null` clears them (D-11).
    public function updateNotes(UpdateReservationNotesRequest $request, Reservation $reservation): JsonResponse
    {
        $result = $this->service->updateNotes($reservation, $request->validated('notes'));
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.reservation_notes_updated', $request);
    }

    // The check-in verb (D-01): room given, else reserved, else auto-picked.
    public function checkIn(CheckInReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $result = $this->service->checkIn(
            $reservation,
            $request->validated('room_uuid'),
            $request->user(),
            (bool) $request->validated('early_check_in', false),
            $request->validated('reason'),
        );
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.reservation_checked_in', $request);
    }

    // The check-out verb (D-06). `force` overrides an open folio, and only for
    // a folios.settle holder: anyone else gets 403 before the action runs (D-07).
    public function checkOut(CheckOutReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $force = (bool) $request->validated('force', false);

        if ($force && ! $request->user()->can('folios.settle')) {
            throw new ForbiddenException(__('custom.errors.forbidden'));
        }

        $result = $this->service->checkOut(
            $reservation,
            $force ? CheckOutMode::STAFF_FORCE : CheckOutMode::NONE,
            $request->user(),
            $request->validated('reason'),
        );
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.reservation_checked_out', $request);
    }
}
