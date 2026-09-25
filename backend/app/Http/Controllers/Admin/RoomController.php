<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cms\UpdateRoomStatusAction;
use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Enums\RoomStatus;
use App\Http\Requests\Cms\CreateRoomRequest;
use App\Http\Requests\Cms\UpdateRoomRequest;
use App\Http\Requests\Cms\UpdateRoomStatusRequest;
use App\Http\Resources\Cms\RoomResource;
use App\Models\Room;
use App\Services\Cms\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = RoomResource::class;

    public function __construct(private readonly RoomService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Room $room, Request $request): JsonResponse
    {
        return $this->showResponse($room, $request);
    }

    public function store(CreateRoomRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        return $this->updateResponse($request, $room);
    }

    /** PATCH /cms/rooms/{room}/status — the only runtime writer of rooms.status (D-01). */
    public function updateStatus(UpdateRoomStatusRequest $request, Room $room, UpdateRoomStatusAction $action): JsonResponse
    {
        $result = $action->handle(
            $room,
            RoomStatus::from($request->validated('status')),
            $request->validated('reason'),
            $request->user('users'),
        );
        $result['data'] = new RoomResource($result['data']);

        return $this->respondFromService($result, 'custom.messages.room_status_updated', $request);
    }

    public function destroy(Room $room, Request $request): JsonResponse
    {
        return $this->destroyResponse($room, $request);
    }

    public function restore(Room $room, Request $request): JsonResponse
    {
        return $this->restoreResponse($room, $request);
    }

    public function forceDestroy(Room $room, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($room, $request);
    }
}
