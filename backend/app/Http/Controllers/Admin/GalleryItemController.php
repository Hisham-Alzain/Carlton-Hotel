<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateGalleryItemRequest;
use App\Http\Requests\Cms\UpdateGalleryItemRequest;
use App\Http\Resources\Cms\GalleryItemResource;
use App\Models\GalleryItem;
use App\Services\Cms\GalleryItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryItemController extends BaseController
{
    public function __construct(private readonly GalleryItemService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], GalleryItemResource::class, $request);
    }

    public function show(GalleryItem $galleryItem, Request $request): JsonResponse
    {
        $result = $this->service->show($galleryItem);
        $result['data'] = new GalleryItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateGalleryItemRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new GalleryItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateGalleryItemRequest $request, GalleryItem $galleryItem): JsonResponse
    {
        $result = $this->service->update($galleryItem, $request->validated());
        $result['data'] = new GalleryItemResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(GalleryItem $galleryItem, Request $request): JsonResponse
    {
        $this->service->destroy($galleryItem);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
