<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateFacilityRequest;
use App\Http\Requests\Cms\UpdateFacilityRequest;
use App\Http\Resources\Cms\FacilityResource;
use App\Models\Facility;
use App\Services\Cms\FacilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityController extends BaseController
{
    public function __construct(private readonly FacilityService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], FacilityResource::class, $request);
    }

    public function show(Facility $facility, Request $request): JsonResponse
    {
        $result = $this->service->show($facility);
        $result['data'] = new FacilityResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateFacilityRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new FacilityResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateFacilityRequest $request, Facility $facility): JsonResponse
    {
        $result = $this->service->update($facility, $request->validated());
        $result['data'] = new FacilityResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Facility $facility, Request $request): JsonResponse
    {
        $this->service->destroy($facility);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
