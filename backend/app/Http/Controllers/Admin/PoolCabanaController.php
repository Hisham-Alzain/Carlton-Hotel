<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Http\Requests\Service\StorePoolCabanaRequest;
use App\Http\Resources\Service\PoolCabanaResource;
use App\Models\PoolCabana;
use App\Services\Service\PoolCabanaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * No `HandlesRecycleBin`: `PoolCabana` is not soft-deletable and has no bin
 * routes, so the three bin verbs would be unreachable methods over a service
 * that throws `LogicException` for them.
 */
class PoolCabanaController extends BaseCRUDController
{
    protected ?string $resource = PoolCabanaResource::class;

    public function __construct(private readonly PoolCabanaService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(PoolCabana $poolCabana, Request $request): JsonResponse
    {
        return $this->showResponse($poolCabana, $request);
    }

    public function store(StorePoolCabanaRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(StorePoolCabanaRequest $request, PoolCabana $poolCabana): JsonResponse
    {
        return $this->updateResponse($request, $poolCabana);
    }

    public function destroy(PoolCabana $poolCabana, Request $request): JsonResponse
    {
        return $this->destroyResponse($poolCabana, $request);
    }
}
