<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Service\CreateServiceBookingRequest;
use App\Http\Resources\Service\ServiceBookingResource;
use App\Services\Service\ServiceBookingService;
use Illuminate\Http\JsonResponse;

class ServiceBookingController extends BaseController
{
    public function __construct(private readonly ServiceBookingService $service) {}

    public function store(CreateServiceBookingRequest $request): JsonResponse
    {
        $result = $this->service->create($request->user('guests'), $request->validated());
        $result['data'] = new ServiceBookingResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.service_booking_created', $request);
    }
}
