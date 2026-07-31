<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateGalleryCategoryRequest;
use App\Http\Requests\Cms\UpdateGalleryCategoryRequest;
use App\Http\Resources\Cms\GalleryCategoryResource;
use App\Models\GalleryCategory;
use App\Services\Cms\GalleryCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryCategoryController extends BaseController
{
    public function __construct(private readonly GalleryCategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], GalleryCategoryResource::class, $request);
    }

    public function show(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        $result = $this->service->show($galleryCategory);
        $result['data'] = new GalleryCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateGalleryCategoryRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new GalleryCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateGalleryCategoryRequest $request, GalleryCategory $galleryCategory): JsonResponse
    {
        $result = $this->service->update($galleryCategory, $request->validated());
        $result['data'] = new GalleryCategoryResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    /**
     * Deleting a chip deletes its photographs — the FK cascades. That is the
     * intended editorial behaviour, not an accident; see the migration.
     */
    public function destroy(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        $this->service->destroy($galleryCategory);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    public function trashed(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->trashed($this->indexParams($request), perPage: $this->perPageParam($request))['data'], GalleryCategoryResource::class, $request);
    }

    /** Restoring a chip brings back the photographs the cascade took with it. */
    public function restore(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        $result = $this->service->restore($galleryCategory);
        $result['data'] = new GalleryCategoryResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.restored', $request);
    }

    public function forceDestroy(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        $this->service->forceDestroy($galleryCategory);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
