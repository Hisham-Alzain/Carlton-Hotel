<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\ExperienceResource;
use App\Models\Experience;
use App\Services\Cms\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends BasePublicIndexController
{
    protected ?string $resource = ExperienceResource::class;

    public function __construct(private readonly ExperienceService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    /**
     * A draft experience must 404 on the public detail route, not merely be
     * absent from the list — otherwise the uuid of an unpublished record is a
     * working preview link for anyone who guesses it.
     *
     * The visibility check is why the base class provides no public `show()`:
     * it is a read plus a rule, and the rule belongs to the resource.
     */
    public function show(Experience $experience, Request $request): JsonResponse
    {
        if (! $experience->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($experience, $request);
    }
}
