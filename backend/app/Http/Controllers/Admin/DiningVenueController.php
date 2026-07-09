<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateDiningVenueRequest;
use App\Http\Requests\Cms\UpdateDiningVenueRequest;
use App\Http\Resources\Cms\DiningVenueResource;
use App\Models\DiningVenue;
use App\Services\Cms\DiningVenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningVenueController extends BaseController
{
    public function __construct(private readonly DiningVenueService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], DiningVenueResource::class, $request);
    }

    public function show(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        $result = $this->service->show($diningVenue);
        $result['data'] = new DiningVenueResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateDiningVenueRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new DiningVenueResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateDiningVenueRequest $request, DiningVenue $diningVenue): JsonResponse
    {
        $result = $this->service->update($diningVenue, $request->validated());
        $result['data'] = new DiningVenueResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        $this->service->destroy($diningVenue);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
