<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StoreMenuCategoryRequest;
use App\Http\Resources\Service\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Services\Service\MenuCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuCategoryController extends BaseController
{
    public function __construct(private readonly MenuCategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], MenuCategoryResource::class, $request);
    }

    public function show(MenuCategory $menuCategory, Request $request): JsonResponse
    {
        $result = $this->service->show($menuCategory);
        $result['data'] = new MenuCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new MenuCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StoreMenuCategoryRequest $request, MenuCategory $menuCategory): JsonResponse
    {
        $result = $this->service->update($menuCategory, $request->validated());
        $result['data'] = new MenuCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(MenuCategory $menuCategory, Request $request): JsonResponse
    {
        $this->service->destroy($menuCategory);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
