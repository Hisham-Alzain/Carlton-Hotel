<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Cms\CreatePromotionRequest;
use App\Http\Requests\Cms\UpdatePromotionRequest;
use App\Http\Resources\Cms\PromotionResource;
use App\Models\Promotion;
use App\Services\Cms\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = PromotionResource::class;

    public function __construct(private readonly PromotionService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Promotion $promotion, Request $request): JsonResponse
    {
        return $this->showResponse($promotion, $request);
    }

    public function store(CreatePromotionRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        return $this->updateResponse($request, $promotion);
    }

    public function destroy(Promotion $promotion, Request $request): JsonResponse
    {
        return $this->destroyResponse($promotion, $request);
    }

    public function restore(Promotion $promotion, Request $request): JsonResponse
    {
        return $this->restoreResponse($promotion, $request);
    }

    public function forceDestroy(Promotion $promotion, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($promotion, $request);
    }
}
