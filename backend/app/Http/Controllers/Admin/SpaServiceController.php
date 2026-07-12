<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\StoreSpaServiceRequest;
use App\Http\Resources\Service\SpaServiceResource;
use App\Models\SpaService;
use App\Services\Service\SpaServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaServiceController extends BaseController
{
    public function __construct(private readonly SpaServiceService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], SpaServiceResource::class, $request);
    }

    public function show(SpaService $spaService, Request $request): JsonResponse
    {
        $result = $this->service->show($spaService);
        $result['data'] = new SpaServiceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(StoreSpaServiceRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new SpaServiceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(StoreSpaServiceRequest $request, SpaService $spaService): JsonResponse
    {
        $result = $this->service->update($spaService, $request->validated());
        $result['data'] = new SpaServiceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(SpaService $spaService, Request $request): JsonResponse
    {
        $this->service->destroy($spaService);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
