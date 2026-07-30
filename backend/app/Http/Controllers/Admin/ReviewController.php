<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Review\SetReviewPublishedRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Review;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseController
{
    public function __construct(private readonly ReviewService $service) {}

    /**
     * The moderation queue.
     *
     * Query params are handed down whole (`indexParams`) for `ReviewFilter` to
     * whitelist, and `per_page` through `perPageParam` — the same two calls every
     * other index controller makes. It used to read `is_published` itself with
     * `$request->boolean()`, which made `?is_published=` (empty) mean *false* and
     * `?is_published=trve` mean false as well, and it dropped `per_page` on the
     * floor. Reading a filter in the controller is what made all three possible;
     * the filter layer answers them consistently.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->adminIndex(
                $this->indexParams($request),
                $this->perPageParam($request),
            )['data'],
            ReviewResource::class,
            $request,
        );
    }

    /**
     * One review from the queue — the `show` every other CMS module has and this
     * one did not, which left the moderation screen able to list a comment but
     * not open it.
     *
     * Behind the same `cms.view|cms.edit` read gate as every other CMS `show`:
     * reading a guest's comment is a read, and the publish toggle beside it is
     * the write. Drafts are visible here on purpose — see `ReviewService::show()`.
     */
    public function show(Request $request, Review $review): JsonResponse
    {
        $result = $this->service->show($review);
        $result['data'] = new ReviewResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }

    public function setPublished(SetReviewPublishedRequest $request, Review $review): JsonResponse
    {
        $result = $this->service->publish($review, $request->boolean('is_published'));
        $result['data'] = new ReviewResource($result['data']);

        return $this->respondFromService($result, 'custom.messages.review_moderated', $request);
    }
}
