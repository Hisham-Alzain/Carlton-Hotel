<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreatePageRequest;
use App\Http\Requests\Cms\UpdatePageRequest;
use App\Http\Resources\Cms\PageResource;
use App\Models\Page;
use App\Services\Cms\PageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends BaseController
{
    public function __construct(private readonly PageService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], PageResource::class, $request);
    }

    public function show(Page $page, Request $request): JsonResponse
    {
        $result = $this->service->show($page);
        $result['data'] = new PageResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreatePageRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new PageResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $result = $this->service->update($page, $request->validated());
        $result['data'] = new PageResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Page $page, Request $request): JsonResponse
    {
        $this->service->destroy($page);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
