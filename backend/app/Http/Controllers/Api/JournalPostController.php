<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\JournalPostResource;
use App\Models\JournalPost;
use App\Services\Cms\JournalPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalPostController extends BasePublicIndexController
{
    protected ?string $resource = JournalPostResource::class;

    public function __construct(private readonly JournalPostService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    /**
     * Bound by `slug`, not `uuid` — the route is `/public/journal/{journalPost:slug}`.
     * The website's URL is the slug an editor chose; the CMS keeps addressing
     * posts by uuid so a retitle-and-reslug never breaks an admin bookmark.
     *
     * The binding field comes off this signature's type-hint, which is why the
     * base class does not own it.
     *
     * The only visibility check is `is_active`. `published_on` is NOT consulted:
     * a post dated next month is reachable, by product decision.
     */
    public function show(JournalPost $journalPost, Request $request): JsonResponse
    {
        if (! $journalPost->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($journalPost, $request);
    }
}
