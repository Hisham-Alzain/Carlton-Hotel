<?php

namespace App\Http\Controllers\Api;

use App\Actions\Service\RequestTransportAction;
use App\Base\BaseController;
use App\Http\Requests\Service\RequestTransportRequest;
use App\Http\Resources\Service\ServiceRequestResource;
use App\Support\GuestEntitlement;
use Illuminate\Http\JsonResponse;

class TransportRequestController extends BaseController
{
    public function __construct(private readonly RequestTransportAction $action) {}

    public function store(RequestTransportRequest $request): JsonResponse
    {
        $guest       = $request->user('guests');
        $reservation = GuestEntitlement::currentReservation($guest);

        $result = $this->action->handle($guest, $reservation, $request->validated('notes'));
        $result['data'] = new ServiceRequestResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.service_request_placed', $request);
    }
}
