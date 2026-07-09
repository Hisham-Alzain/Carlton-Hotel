<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateRoomRequest;
use App\Http\Requests\Cms\UpdateRoomRequest;
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
        return $this->paginatedSuccess($this->service->index()['data'], RoomResource::class, $request);
    }

    public function show(Room $room, Request $request): JsonResponse
    {
        $result = $this->service->show($room);
        $result['data'] = new RoomResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateRoomRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new RoomResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $result = $this->service->update($room, $request->validated());
        $result['data'] = new RoomResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Room $room, Request $request): JsonResponse
    {
        $this->service->destroy($room);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
