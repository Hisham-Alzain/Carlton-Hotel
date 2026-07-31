<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Http\Requests\Service\StoreRestaurantTableRequest;
use App\Http\Resources\Service\RestaurantTableResource;
use App\Models\RestaurantTable;
use App\Services\Service\RestaurantTableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantTableController extends BaseCRUDController
{
    protected ?string $resource = RestaurantTableResource::class;

    public function __construct(private readonly RestaurantTableService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(RestaurantTable $restaurantTable, Request $request): JsonResponse
    {
        return $this->showResponse($restaurantTable, $request);
    }

    public function store(StoreRestaurantTableRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(StoreRestaurantTableRequest $request, RestaurantTable $restaurantTable): JsonResponse
    {
        return $this->updateResponse($request, $restaurantTable);
    }

    public function destroy(RestaurantTable $restaurantTable, Request $request): JsonResponse
    {
        return $this->destroyResponse($restaurantTable, $request);
    }
}
