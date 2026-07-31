<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\EventSpaceResource;
use App\Models\EventSpace;
use App\Services\Cms\EventSpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventSpaceController extends BasePublicIndexController
{
    protected ?string $resource = EventSpaceResource::class;

    public function __construct(private readonly EventSpaceService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(EventSpace $eventSpace, Request $request): JsonResponse
    {
        if (! $eventSpace->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($eventSpace, $request);
    }
}
