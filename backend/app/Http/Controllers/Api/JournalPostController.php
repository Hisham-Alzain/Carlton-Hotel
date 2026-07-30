<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
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
        return $this->paginatedSuccess($this->service->indexPublic($this->perPageParam($request))['data'], JournalPostResource::class, $request);
    }

    /**
     * Bound by `slug`, not `uuid` — the route is `/public/journal/{journalPost:slug}`.
     * The website's URL is the slug an editor chose; the CMS keeps addressing
     * posts by uuid so a retitle-and-reslug never breaks an admin bookmark.
     *
     * The only visibility check is `is_active`. `published_on` is NOT consulted:
     * a post dated next month is reachable, by product decision.
     */
    public function show(JournalPost $journalPost, Request $request): JsonResponse
    {
        if (! $journalPost->is_active) {
            throw new NotFoundException();
        }

        $result = $this->service->show($journalPost);
        $result['data'] = new JournalPostResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
