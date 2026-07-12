<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Service\PlaceServiceRequestRequest;
use App\Http\Resources\Service\ServiceRequestResource;
use App\Services\Service\ServiceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends BaseController
{
    public function __construct(private readonly ServiceRequestService $service) {}

    public function store(PlaceServiceRequestRequest $request): JsonResponse
    {
        $result = $this->service->place($request->user('guests'), $request->validated());
        $result['data'] = new ServiceRequestResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.service_request_placed', $request);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->myRequests($request->user('guests'))['data'],
            ServiceRequestResource::class,
            $request
        );
    }
}
