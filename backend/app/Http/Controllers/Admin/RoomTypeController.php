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
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], RoomTypeResource::class, $request);
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

    /**
     * The recycle bin. Same list shape as `index`, over the rows `destroy` marked.
     */
    public function trashed(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->trashed($this->indexParams($request), perPage: $this->perPageParam($request))['data'], RoomTypeResource::class, $request);
    }

    /**
     * Undo a delete. The route carries `->withTrashed()`; without it implicit
     * binding resolves through the soft-delete scope and this 404s on the only
     * kind of record it can be given. Every `restore`/`forceDestroy` pair below
     * and in the other 16 CMS controllers depends on that the same way.
     */
    public function restore(RoomType $roomType, Request $request): JsonResponse
    {
        $result = $this->service->restore($roomType);
        $result['data'] = new RoomTypeResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.restored', $request);
    }

    /** Empty this row out of the bin for good — this is where media is purged. */
    public function forceDestroy(RoomType $roomType, Request $request): JsonResponse
    {
        $this->service->forceDestroy($roomType);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
