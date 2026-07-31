<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateGalleryItemRequest;
use App\Http\Requests\Cms\UpdateGalleryItemRequest;
use App\Http\Resources\Cms\GalleryItemResource;
use App\Models\GalleryItem;
use App\Services\Cms\GalleryItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryItemController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = GalleryItemResource::class;

    public function __construct(private readonly GalleryItemService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(GalleryItem $galleryItem, Request $request): JsonResponse
    {
        return $this->showResponse($galleryItem, $request);
    }

    public function store(CreateGalleryItemRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateGalleryItemRequest $request, GalleryItem $galleryItem): JsonResponse
    {
        return $this->updateResponse($request, $galleryItem);
    }

    public function destroy(GalleryItem $galleryItem, Request $request): JsonResponse
    {
        return $this->destroyResponse($galleryItem, $request);
    }

    public function restore(GalleryItem $galleryItem, Request $request): JsonResponse
    {
        return $this->restoreResponse($galleryItem, $request);
    }

    public function forceDestroy(GalleryItem $galleryItem, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($galleryItem, $request);
    }
}
