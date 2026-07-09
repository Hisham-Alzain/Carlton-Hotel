<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Cms\CreatePromotionRequest;
use App\Http\Requests\Cms\UpdatePromotionRequest;
use App\Http\Resources\Cms\PromotionResource;
use App\Models\Promotion;
use App\Services\Cms\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends BaseController
{
    public function __construct(private readonly PromotionService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->index()['data'], PromotionResource::class, $request);
    }

    public function show(Promotion $promotion, Request $request): JsonResponse
    {
        $result = $this->service->show($promotion);
        $result['data'] = new PromotionResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function store(CreatePromotionRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());
        $result['data'] = new PromotionResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $result = $this->service->update($promotion, $request->validated());
        $result['data'] = new PromotionResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function destroy(Promotion $promotion, Request $request): JsonResponse
    {
        $this->service->destroy($promotion);
        return $this->success(null, 'custom.messages.deleted', 204, $request);
    }
}
