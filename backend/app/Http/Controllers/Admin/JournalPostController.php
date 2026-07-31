<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreateJournalPostRequest;
use App\Http\Requests\Cms\UpdateJournalPostRequest;
use App\Http\Resources\Cms\JournalPostResource;
use App\Models\JournalPost;
use App\Services\Cms\JournalPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalPostController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = JournalPostResource::class;

    public function __construct(private readonly JournalPostService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    /**
     * Bound by `uuid` here — a retitle-and-reslug must never break an admin
     * bookmark. The public route addresses the same model as
     * `{journalPost:slug}`, and that keeps working because the binding field is
     * read off *this* concrete type-hint: an explicit `Route::model()` binding
     * would have dropped it (`RouteBinding::forModel` passes no field).
     */
    public function show(JournalPost $journalPost, Request $request): JsonResponse
    {
        return $this->showResponse($journalPost, $request);
    }

    public function store(CreateJournalPostRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateJournalPostRequest $request, JournalPost $journalPost): JsonResponse
    {
        return $this->updateResponse($request, $journalPost);
    }

    public function destroy(JournalPost $journalPost, Request $request): JsonResponse
    {
        return $this->destroyResponse($journalPost, $request);
    }

    public function restore(JournalPost $journalPost, Request $request): JsonResponse
    {
        return $this->restoreResponse($journalPost, $request);
    }

    public function forceDestroy(JournalPost $journalPost, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($journalPost, $request);
    }
}
