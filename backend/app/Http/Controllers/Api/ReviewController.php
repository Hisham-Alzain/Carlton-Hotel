<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Review\SubmitReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseController
{
    public function __construct(private readonly ReviewService $service) {}

    public function index(string $type, string $uuid, Request $request): JsonResponse
    {
        $reviewable = $this->service->resolveReviewable($type, $uuid);

        return $this->paginatedSuccess(
            $this->service->indexFor($reviewable)['data'],
            ReviewResource::class,
            $request,
        );
    }

    public function store(SubmitReviewRequest $request, string $type, string $uuid): JsonResponse
    {
        $reviewable = $this->service->resolveReviewable($type, $uuid);

        $result = $this->service->store(
            $request->user('guests'),
            $reviewable,
            $request->validated(),
        );
        $result['data'] = new ReviewResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }
}
