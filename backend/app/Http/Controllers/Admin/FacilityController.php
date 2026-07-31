<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateFacilityRequest;
use App\Http\Requests\Cms\UpdateFacilityRequest;
use App\Http\Resources\Cms\FacilityResource;
use App\Models\Facility;
use App\Services\Cms\FacilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = FacilityResource::class;

    public function __construct(private readonly FacilityService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Facility $facility, Request $request): JsonResponse
    {
        return $this->showResponse($facility, $request);
    }

    public function store(CreateFacilityRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateFacilityRequest $request, Facility $facility): JsonResponse
    {
        return $this->updateResponse($request, $facility);
    }

    public function destroy(Facility $facility, Request $request): JsonResponse
    {
        return $this->destroyResponse($facility, $request);
    }

    public function restore(Facility $facility, Request $request): JsonResponse
    {
        return $this->restoreResponse($facility, $request);
    }

    public function forceDestroy(Facility $facility, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($facility, $request);
    }
}
