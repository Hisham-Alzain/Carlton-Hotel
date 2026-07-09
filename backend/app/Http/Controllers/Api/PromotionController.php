<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Exceptions\NotFoundException;
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
        return $this->paginatedSuccess($this->service->indexPublic()['data'], PromotionResource::class, $request);
    }

    public function show(Promotion $promotion, Request $request): JsonResponse
    {
        if (!$promotion->is_active) {
            throw new NotFoundException();
        }
        $result = $this->service->show($promotion);
        $result['data'] = new PromotionResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }
}
