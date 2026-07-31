<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateExperienceRequest;
use App\Http\Requests\Cms\UpdateExperienceRequest;
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
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], ExperienceResource::class, $request);
    }

    public function show(Experience $experience, Request $request): JsonResponse
    {
        $result = $this->service->show($experience);
        $result['data'] = new ExperienceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateExperienceRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new ExperienceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateExperienceRequest $request, Experience $experience): JsonResponse
    {
        $result = $this->service->update($experience, $request->validated());
        $result['data'] = new ExperienceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Experience $experience, Request $request): JsonResponse
    {
        $this->service->destroy($experience);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    public function trashed(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->trashed($this->indexParams($request), perPage: $this->perPageParam($request))['data'], ExperienceResource::class, $request);
    }

    public function restore(Experience $experience, Request $request): JsonResponse
    {
        $result = $this->service->restore($experience);
        $result['data'] = new ExperienceResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.restored', $request);
    }

    public function forceDestroy(Experience $experience, Request $request): JsonResponse
    {
        $this->service->forceDestroy($experience);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
