<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\ExperienceResource;
use App\Models\Experience;
use App\Services\Cms\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends BaseController
{
    public function __construct(private readonly ExperienceService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->indexPublic($this->perPageParam($request))['data'], ExperienceResource::class, $request);
    }

    /**
     * A draft experience must 404 on the public detail route, not merely be
     * absent from the list — otherwise the uuid of an unpublished record is a
     * working preview link for anyone who guesses it.
     */
    public function show(Experience $experience, Request $request): JsonResponse
    {
        if (! $experience->is_active) {
            throw new NotFoundException();
        }

        $result = $this->service->show($experience);
        $result['data'] = new ExperienceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
