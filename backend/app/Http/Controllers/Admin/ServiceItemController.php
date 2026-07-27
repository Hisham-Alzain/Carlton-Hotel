<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StoreServiceItemRequest;
use App\Http\Resources\Service\ServiceItemResource;
use App\Models\ServiceItem;
use App\Services\Service\ServiceItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceItemController extends BaseController
{
    public function __construct(private readonly ServiceItemService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], ServiceItemResource::class, $request);
    }

    public function show(ServiceItem $serviceItem, Request $request): JsonResponse
    {
        $result = $this->service->show($serviceItem);
        $result['data'] = new ServiceItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StoreServiceItemRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new ServiceItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StoreServiceItemRequest $request, ServiceItem $serviceItem): JsonResponse
    {
        $result = $this->service->update($serviceItem, $request->validated());
        $result['data'] = new ServiceItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(ServiceItem $serviceItem, Request $request): JsonResponse
    {
        $this->service->destroy($serviceItem);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
