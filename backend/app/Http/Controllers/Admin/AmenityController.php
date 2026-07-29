<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateAmenityRequest;
use App\Http\Requests\Cms\UpdateAmenityRequest;
use App\Http\Resources\Cms\AmenityResource;
use App\Models\Amenity;
use App\Services\Cms\AmenityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmenityController extends BaseController
{
    public function __construct(private readonly AmenityService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], AmenityResource::class, $request);
    }

    public function show(Amenity $amenity, Request $request): JsonResponse
    {
        $result = $this->service->show($amenity);
        $result['data'] = new AmenityResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateAmenityRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new AmenityResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateAmenityRequest $request, Amenity $amenity): JsonResponse
    {
        $result = $this->service->update($amenity, $request->validated());
        $result['data'] = new AmenityResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Amenity $amenity, Request $request): JsonResponse
    {
        $this->service->destroy($amenity);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
