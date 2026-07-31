<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateRoomTypeRequest;
use App\Http\Requests\Cms\UpdateRoomTypeRequest;
use App\Http\Resources\Cms\RoomTypeResource;
use App\Models\RoomType;
use App\Services\Cms\RoomTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = RoomTypeResource::class;

    public function __construct(private readonly RoomTypeService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(RoomType $roomType, Request $request): JsonResponse
    {
        return $this->showResponse($roomType, $request);
    }

    public function store(CreateRoomTypeRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateRoomTypeRequest $request, RoomType $roomType): JsonResponse
    {
        return $this->updateResponse($request, $roomType);
    }

    public function destroy(RoomType $roomType, Request $request): JsonResponse
    {
        return $this->destroyResponse($roomType, $request);
    }

    /**
     * Undo a delete. The route carries `->withTrashed()`; without it implicit
     * binding resolves through the soft-delete scope and this 404s on the only
     * kind of record it can be given. Every `restore`/`forceDestroy` pair below
     * and in the other 16 CMS controllers depends on that the same way — and it
     * is why the model type-hint stays here rather than moving onto the base
     * class: implicit binding reads *this* signature.
     */
    public function restore(RoomType $roomType, Request $request): JsonResponse
    {
        return $this->restoreResponse($roomType, $request);
    }

    /** Empty this row out of the bin for good — this is where media is purged. */
    public function forceDestroy(RoomType $roomType, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($roomType, $request);
    }
}
