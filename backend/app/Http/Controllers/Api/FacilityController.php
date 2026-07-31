<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\FacilityResource;
use App\Models\Facility;
use App\Services\Cms\FacilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityController extends BasePublicIndexController
{
    protected ?string $resource = FacilityResource::class;

    public function __construct(private readonly FacilityService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Facility $facility, Request $request): JsonResponse
    {
        if (! $facility->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($facility, $request);
    }
}
