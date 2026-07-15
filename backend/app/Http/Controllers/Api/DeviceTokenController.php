<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Notification\RegisterDeviceTokenRequest;
use App\Http\Resources\Notification\DeviceTokenResource;
use App\Services\Notification\DeviceTokenService;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends BaseController
{
    public function __construct(private readonly DeviceTokenService $service) {}

    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $result = $this->service->register($request->user('guests'), $request->validated());
        $result['data'] = new DeviceTokenResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
