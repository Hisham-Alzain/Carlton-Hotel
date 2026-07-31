<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreateJournalPostRequest;
use App\Http\Requests\Cms\UpdateJournalPostRequest;
use App\Http\Resources\Cms\JournalPostResource;
use App\Models\JournalPost;
use App\Services\Cms\JournalPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalPostController extends BaseController
{
    public function __construct(private readonly JournalPostService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index($this->indexParams($request), perPage: $this->perPageParam($request))['data'], JournalPostResource::class, $request);
    }

    public function show(JournalPost $journalPost, Request $request): JsonResponse
    {
        $result = $this->service->show($journalPost);
        $result['data'] = new JournalPostResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreateJournalPostRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new JournalPostResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdateJournalPostRequest $request, JournalPost $journalPost): JsonResponse
    {
        $result = $this->service->update($journalPost, $request->validated());
        $result['data'] = new JournalPostResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(JournalPost $journalPost, Request $request): JsonResponse
    {
        $this->service->destroy($journalPost);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }

    public function trashed(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->trashed($this->indexParams($request), perPage: $this->perPageParam($request))['data'], JournalPostResource::class, $request);
    }

    public function restore(JournalPost $journalPost, Request $request): JsonResponse
    {
        $result = $this->service->restore($journalPost);
        $result['data'] = new JournalPostResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.restored', $request);
    }

    public function forceDestroy(JournalPost $journalPost, Request $request): JsonResponse
    {
        $this->service->forceDestroy($journalPost);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
