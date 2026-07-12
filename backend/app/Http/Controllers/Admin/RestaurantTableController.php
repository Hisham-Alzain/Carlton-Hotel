<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StoreRestaurantTableRequest;
use App\Http\Resources\Service\RestaurantTableResource;
use App\Models\RestaurantTable;
use App\Services\Service\RestaurantTableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantTableController extends BaseController
{
    public function __construct(private readonly RestaurantTableService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], RestaurantTableResource::class, $request);
    }

    public function show(RestaurantTable $restaurantTable, Request $request): JsonResponse
    {
        $result = $this->service->show($restaurantTable);
        $result['data'] = new RestaurantTableResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StoreRestaurantTableRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new RestaurantTableResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StoreRestaurantTableRequest $request, RestaurantTable $restaurantTable): JsonResponse
    {
        $result = $this->service->update($restaurantTable, $request->validated());
        $result['data'] = new RestaurantTableResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(RestaurantTable $restaurantTable, Request $request): JsonResponse
    {
        $this->service->destroy($restaurantTable);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
