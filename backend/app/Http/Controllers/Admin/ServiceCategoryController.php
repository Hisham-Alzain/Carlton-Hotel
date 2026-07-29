<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StoreServiceCategoryRequest;
use App\Http\Resources\Service\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Services\Service\ServiceCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCategoryController extends BaseController
{
    public function __construct(private readonly ServiceCategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], ServiceCategoryResource::class, $request);
    }

    public function show(ServiceCategory $serviceCategory, Request $request): JsonResponse
    {
        $result = $this->service->show($serviceCategory);
        $result['data'] = new ServiceCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StoreServiceCategoryRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new ServiceCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StoreServiceCategoryRequest $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $result = $this->service->update($serviceCategory, $request->validated());
        $result['data'] = new ServiceCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(ServiceCategory $serviceCategory, Request $request): JsonResponse
    {
        $this->service->destroy($serviceCategory);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
