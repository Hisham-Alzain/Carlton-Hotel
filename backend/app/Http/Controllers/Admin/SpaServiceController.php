<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Http\Requests\Service\StoreSpaServiceRequest;
use App\Http\Resources\Service\SpaServiceResource;
use App\Models\SpaService;
use App\Services\Service\SpaServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaServiceController extends BaseCRUDController
{
    protected ?string $resource = SpaServiceResource::class;

    public function __construct(private readonly SpaServiceService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(SpaService $spaService, Request $request): JsonResponse
    {
        return $this->showResponse($spaService, $request);
    }

    public function store(StoreSpaServiceRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(StoreSpaServiceRequest $request, SpaService $spaService): JsonResponse
    {
        return $this->updateResponse($request, $spaService);
    }

    public function destroy(SpaService $spaService, Request $request): JsonResponse
    {
        return $this->destroyResponse($spaService, $request);
    }
}
