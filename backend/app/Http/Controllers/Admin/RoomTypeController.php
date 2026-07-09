<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateRoomTypeRequest;
use App\Http\Requests\Cms\UpdateRoomTypeRequest;
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
        return $this->paginatedSuccess($this->service->index()['data'], RoomTypeResource::class, $request);
    }

    public function show(RoomType $roomType, Request $request): JsonResponse
    {
        $result = $this->service->show($roomType);
        $result['data'] = new RoomTypeResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateRoomTypeRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new RoomTypeResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateRoomTypeRequest $request, RoomType $roomType): JsonResponse
    {
        $result = $this->service->update($roomType, $request->validated());
        $result['data'] = new RoomTypeResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(RoomType $roomType, Request $request): JsonResponse
    {
        $this->service->destroy($roomType);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
