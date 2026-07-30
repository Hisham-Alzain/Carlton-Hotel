<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Cms\GalleryCategoryResource;
use App\Http\Resources\Cms\GalleryItemResource;
use App\Services\Cms\GalleryCategoryService;
use App\Services\Cms\GalleryItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The website's gallery is two reads off one page: the chip row and the
 * photographs. Both live on one controller because they are one screen — there
 * is no public `show` for either, since no page deep-links to a single photo.
 */
class GalleryController extends BaseController
{
    public function __construct(
        private readonly GalleryCategoryService $categories,
        private readonly GalleryItemService $items,
    ) {}

    public function categories(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->categories->indexPublic($this->perPageParam($request))['data'], GalleryCategoryResource::class, $request);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->items->indexPublic($this->perPageParam($request))['data'], GalleryItemResource::class, $request);
    }
}
