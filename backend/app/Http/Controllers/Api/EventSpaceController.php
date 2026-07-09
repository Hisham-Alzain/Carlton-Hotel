<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
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
        return $this->paginatedSuccess($this->service->indexPublic()['data'], EventSpaceResource::class, $request);
    }

    public function show(EventSpace $eventSpace, Request $request): JsonResponse
    {
        if (!$eventSpace->is_active) {
            throw new NotFoundException();
        }
        $result = $this->service->show($eventSpace);
        $result['data'] = new EventSpaceResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
