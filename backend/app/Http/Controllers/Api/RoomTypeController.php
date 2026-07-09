<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\RoomTypeResource;
use App\Models\RoomType;
use App\Services\Cms\RoomTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeController extends BaseController
{
    public function __construct(private readonly RoomTypeService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->indexPublic()['data'], RoomTypeResource::class, $request);
    }

    public function show(RoomType $roomType, Request $request): JsonResponse
    {
        if (!$roomType->is_active) {
            throw new NotFoundException();
        }
        $result = $this->service->show($roomType);
        $result['data'] = new RoomTypeResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
