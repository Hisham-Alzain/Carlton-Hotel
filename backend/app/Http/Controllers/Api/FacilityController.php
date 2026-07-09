<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
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
        return $this->paginatedSuccess($this->service->indexPublic()['data'], FacilityResource::class, $request);
    }

    public function show(Facility $facility, Request $request): JsonResponse
    {
        if (!$facility->is_active) {
            throw new NotFoundException();
        }
        $result = $this->service->show($facility);
        $result['data'] = new FacilityResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
