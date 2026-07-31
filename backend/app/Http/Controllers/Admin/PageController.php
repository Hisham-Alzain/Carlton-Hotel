<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreatePageRequest;
use App\Http\Requests\Cms\UpdatePageRequest;
use App\Http\Resources\Cms\PageResource;
use App\Models\Page;
use App\Services\Cms\PageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = PageResource::class;

    public function __construct(private readonly PageService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Page $page, Request $request): JsonResponse
    {
        return $this->showResponse($page, $request);
    }

    public function store(CreatePageRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        return $this->updateResponse($request, $page);
    }

    public function destroy(Page $page, Request $request): JsonResponse
    {
        return $this->destroyResponse($page, $request);
    }

    public function restore(Page $page, Request $request): JsonResponse
    {
        return $this->restoreResponse($page, $request);
    }

    public function forceDestroy(Page $page, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($page, $request);
    }
}
