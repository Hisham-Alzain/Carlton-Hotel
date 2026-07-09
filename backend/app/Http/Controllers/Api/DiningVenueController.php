<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
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
        return $this->paginatedSuccess($this->service->indexPublic()['data'], DiningVenueResource::class, $request);
    }

    public function show(DiningVenue $diningVenue, Request $request): JsonResponse
    {
        if (!$diningVenue->is_active) {
            throw new NotFoundException();
        }
        $result = $this->service->show($diningVenue);
        $result['data'] = new DiningVenueResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
