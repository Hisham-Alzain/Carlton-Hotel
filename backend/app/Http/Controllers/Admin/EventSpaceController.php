<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateEventSpaceRequest;
use App\Http\Requests\Cms\UpdateEventSpaceRequest;
use App\Http\Resources\Cms\EventSpaceResource;
use App\Models\EventSpace;
use App\Services\Cms\EventSpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventSpaceController extends BaseController
{
    public function __construct(private readonly EventSpaceService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], EventSpaceResource::class, $request);
    }

    public function show(EventSpace $eventSpace, Request $request): JsonResponse
    {
        $result = $this->service->show($eventSpace);
        $result['data'] = new EventSpaceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateEventSpaceRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new EventSpaceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateEventSpaceRequest $request, EventSpace $eventSpace): JsonResponse
    {
        $result = $this->service->update($eventSpace, $request->validated());
        $result['data'] = new EventSpaceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(EventSpace $eventSpace, Request $request): JsonResponse
    {
        $this->service->destroy($eventSpace);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
