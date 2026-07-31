<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateEventSpaceRequest;
use App\Http\Requests\Cms\UpdateEventSpaceRequest;
use App\Http\Resources\Cms\EventSpaceResource;
use App\Models\EventSpace;
use App\Services\Cms\EventSpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventSpaceController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = EventSpaceResource::class;

    public function __construct(private readonly EventSpaceService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(EventSpace $eventSpace, Request $request): JsonResponse
    {
        return $this->showResponse($eventSpace, $request);
    }

    public function store(CreateEventSpaceRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateEventSpaceRequest $request, EventSpace $eventSpace): JsonResponse
    {
        return $this->updateResponse($request, $eventSpace);
    }

    public function destroy(EventSpace $eventSpace, Request $request): JsonResponse
    {
        return $this->destroyResponse($eventSpace, $request);
    }

    public function restore(EventSpace $eventSpace, Request $request): JsonResponse
    {
        return $this->restoreResponse($eventSpace, $request);
    }

    public function forceDestroy(EventSpace $eventSpace, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($eventSpace, $request);
    }
}
