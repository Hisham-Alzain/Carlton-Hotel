<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateDiningVenueRequest;
use App\Http\Requests\Cms\UpdateDiningVenueRequest;
use App\Http\Resources\Cms\DiningVenueResource;
use App\Models\DiningVenue;
use App\Services\Cms\DiningVenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningVenueController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = DiningVenueResource::class;

    public function __construct(private readonly DiningVenueService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        return $this->showResponse($diningVenue, $request);
    }

    public function store(CreateDiningVenueRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateDiningVenueRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        return $this->updateResponse($request, $diningVenue);
    }

    public function destroy(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        return $this->destroyResponse($diningVenue, $request);
    }

    public function restore(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        return $this->restoreResponse($diningVenue, $request);
    }

    public function forceDestroy(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($diningVenue, $request);
    }
}
