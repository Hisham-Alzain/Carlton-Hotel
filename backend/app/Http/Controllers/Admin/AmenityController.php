<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateAmenityRequest;
use App\Http\Requests\Cms\UpdateAmenityRequest;
use App\Http\Resources\Cms\AmenityResource;
use App\Models\Amenity;
use App\Services\Cms\AmenityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmenityController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = AmenityResource::class;

    public function __construct(private readonly AmenityService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    // `index()` and `trashed()` are inherited whole. The verbs below exist only
    // to carry the concrete type-hints implicit route-model binding and
    // FormRequest resolution read; the bodies live on the base pair.

    public function show(Amenity $amenity, Request $request): JsonResponse
    {
        return $this->showResponse($amenity, $request);
    }

    public function store(CreateAmenityRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateAmenityRequest $request, Amenity $amenity): JsonResponse
    {
        return $this->updateResponse($request, $amenity);
    }

    public function destroy(Amenity $amenity, Request $request): JsonResponse
    {
        return $this->destroyResponse($amenity, $request);
    }

    public function restore(Amenity $amenity, Request $request): JsonResponse
    {
        return $this->restoreResponse($amenity, $request);
    }

    public function forceDestroy(Amenity $amenity, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($amenity, $request);
    }
}
