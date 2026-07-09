<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\RoomResource;
use App\Models\Room;
use App\Services\Cms\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends BaseController
{
    public function __construct(private readonly RoomService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->indexPublic()['data'], RoomResource::class, $request);
    }

    public function show(Room $room, Request $request): JsonResponse
    {
        if (!$room->is_active) {
            throw new NotFoundException();
        }
        $result = $this->service->show($room);
        $result['data'] = new RoomResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
