<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\RoomResource;
use App\Models\Room;
use App\Services\Cms\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends BasePublicIndexController
{
    protected ?string $resource = RoomResource::class;

    public function __construct(private readonly RoomService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Room $room, Request $request): JsonResponse
    {
        if (! $room->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($room, $request);
    }
}
