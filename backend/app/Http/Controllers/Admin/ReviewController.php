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

    public function index(Request $request): JsonResponse
    {
        $isPublished = $request->has('is_published')
            ? $request->boolean('is_published')
            : null;

        return $this->paginatedSuccess(
            $this->service->adminIndex($isPublished)['data'],
            ReviewResource::class,
            $request,
        );
    }

    public function setPublished(SetReviewPublishedRequest $request, Review $review): JsonResponse
    {
        $result = $this->service->publish($review, $request->boolean('is_published'));
        $result['data'] = new ReviewResource($result['data']);

        return $this->respondFromService($result, 'custom.messages.review_moderated', $request);
    }
}
