<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateExperienceRequest;
use App\Http\Requests\Cms\UpdateExperienceRequest;
use App\Http\Resources\Cms\ExperienceResource;
use App\Models\Experience;
use App\Services\Cms\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = ExperienceResource::class;

    public function __construct(private readonly ExperienceService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Experience $experience, Request $request): JsonResponse
    {
        return $this->showResponse($experience, $request);
    }

    public function store(CreateExperienceRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateExperienceRequest $request, Experience $experience): JsonResponse
    {
        return $this->updateResponse($request, $experience);
    }

    public function destroy(Experience $experience, Request $request): JsonResponse
    {
        return $this->destroyResponse($experience, $request);
    }

    public function restore(Experience $experience, Request $request): JsonResponse
    {
        return $this->restoreResponse($experience, $request);
    }

    public function forceDestroy(Experience $experience, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($experience, $request);
    }
}
