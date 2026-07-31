<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Service\StoreMenuItemRequest;
use App\Http\Requests\Service\UpdateMenuItemRequest;
use App\Http\Resources\Service\MenuItemResource;
use App\Models\MenuItem;
use App\Services\Service\MenuItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = MenuItemResource::class;

    public function __construct(private readonly MenuItemService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(MenuItem $menuItem, Request $request): JsonResponse
    {
        return $this->showResponse($menuItem, $request);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        return $this->updateResponse($request, $menuItem);
    }

    public function destroy(MenuItem $menuItem, Request $request): JsonResponse
    {
        return $this->destroyResponse($menuItem, $request);
    }

    public function restore(MenuItem $menuItem, Request $request): JsonResponse
    {
        return $this->restoreResponse($menuItem, $request);
    }

    public function forceDestroy(MenuItem $menuItem, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($menuItem, $request);
    }
}
