<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateGalleryCategoryRequest;
use App\Http\Requests\Cms\UpdateGalleryCategoryRequest;
use App\Http\Resources\Cms\GalleryCategoryResource;
use App\Models\GalleryCategory;
use App\Services\Cms\GalleryCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryCategoryController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = GalleryCategoryResource::class;

    public function __construct(private readonly GalleryCategoryService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        return $this->showResponse($galleryCategory, $request);
    }

    public function store(CreateGalleryCategoryRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateGalleryCategoryRequest $request, GalleryCategory $galleryCategory): JsonResponse
    {
        return $this->updateResponse($request, $galleryCategory);
    }

    /**
     * Deleting a chip deletes its photographs — the FK cascades. That is the
     * intended editorial behaviour, not an accident; see the migration.
     */
    public function destroy(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        return $this->destroyResponse($galleryCategory, $request);
    }

    /** Restoring a chip brings back the photographs the cascade took with it. */
    public function restore(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        return $this->restoreResponse($galleryCategory, $request);
    }

    public function forceDestroy(GalleryCategory $galleryCategory, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($galleryCategory, $request);
    }
}
