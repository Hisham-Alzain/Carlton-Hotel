<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Service\StoreMenuCategoryRequest;
use App\Http\Requests\Service\UpdateMenuCategoryRequest;
use App\Http\Resources\Service\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Services\Service\MenuCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuCategoryController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = MenuCategoryResource::class;

    public function __construct(private readonly MenuCategoryService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(MenuCategory $menuCategory, Request $request): JsonResponse
    {
        return $this->showResponse($menuCategory, $request);
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): JsonResponse
    {
        return $this->updateResponse($request, $menuCategory);
    }

    public function destroy(MenuCategory $menuCategory, Request $request): JsonResponse
    {
        return $this->destroyResponse($menuCategory, $request);
    }

    public function restore(MenuCategory $menuCategory, Request $request): JsonResponse
    {
        return $this->restoreResponse($menuCategory, $request);
    }

    public function forceDestroy(MenuCategory $menuCategory, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($menuCategory, $request);
    }
}
