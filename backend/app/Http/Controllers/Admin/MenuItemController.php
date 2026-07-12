<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StoreMenuItemRequest;
use App\Http\Resources\Service\MenuItemResource;
use App\Models\MenuItem;
use App\Services\Service\MenuItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends BaseController
{
    public function __construct(private readonly MenuItemService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], MenuItemResource::class, $request);
    }

    public function show(MenuItem $menuItem, Request $request): JsonResponse
    {
        $result = $this->service->show($menuItem);
        $result['data'] = new MenuItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new MenuItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StoreMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        $result = $this->service->update($menuItem, $request->validated());
        $result['data'] = new MenuItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(MenuItem $menuItem, Request $request): JsonResponse
    {
        $this->service->destroy($menuItem);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
