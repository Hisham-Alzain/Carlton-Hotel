<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StorePoolCabanaRequest;
use App\Http\Resources\Service\PoolCabanaResource;
use App\Models\PoolCabana;
use App\Services\Service\PoolCabanaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoolCabanaController extends BaseController
{
    public function __construct(private readonly PoolCabanaService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], PoolCabanaResource::class, $request);
    }

    public function show(PoolCabana $poolCabana, Request $request): JsonResponse
    {
        $result = $this->service->show($poolCabana);
        $result['data'] = new PoolCabanaResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StorePoolCabanaRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new PoolCabanaResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StorePoolCabanaRequest $request, PoolCabana $poolCabana): JsonResponse
    {
        $result = $this->service->update($poolCabana, $request->validated());
        $result['data'] = new PoolCabanaResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(PoolCabana $poolCabana, Request $request): JsonResponse
    {
        $this->service->destroy($poolCabana);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
