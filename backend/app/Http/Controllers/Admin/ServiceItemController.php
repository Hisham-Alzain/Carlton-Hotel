<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Http\Requests\Service\StoreServiceItemRequest;
use App\Http\Requests\Service\UpdateServiceItemRequest;
use App\Http\Resources\Service\ServiceItemResource;
use App\Models\ServiceItem;
use App\Services\Service\ServiceItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceItemController extends BaseCRUDController
{
    protected ?string $resource = ServiceItemResource::class;

    public function __construct(private readonly ServiceItemService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(ServiceItem $serviceItem, Request $request): JsonResponse
    {
        return $this->showResponse($serviceItem, $request);
    }

    public function store(StoreServiceItemRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateServiceItemRequest $request, ServiceItem $serviceItem): JsonResponse
    {
        return $this->updateResponse($request, $serviceItem);
    }

    public function destroy(ServiceItem $serviceItem, Request $request): JsonResponse
    {
        return $this->destroyResponse($serviceItem, $request);
    }
}
