<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Http\Requests\Service\StoreServiceCategoryRequest;
use App\Http\Requests\Service\UpdateServiceCategoryRequest;
use App\Http\Resources\Service\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Services\Service\ServiceCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCategoryController extends BaseCRUDController
{
    protected ?string $resource = ServiceCategoryResource::class;

    public function __construct(private readonly ServiceCategoryService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(ServiceCategory $serviceCategory, Request $request): JsonResponse
    {
        return $this->showResponse($serviceCategory, $request);
    }

    public function store(StoreServiceCategoryRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory): JsonResponse
    {
        return $this->updateResponse($request, $serviceCategory);
    }

    public function destroy(ServiceCategory $serviceCategory, Request $request): JsonResponse
    {
        return $this->destroyResponse($serviceCategory, $request);
    }
}
