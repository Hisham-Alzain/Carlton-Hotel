<?php

namespace App\Http\Controllers\Api;

use App\Base\BasePublicIndexController;
use App\Base\BaseService;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Cms\PromotionResource;
use App\Models\Promotion;
use App\Services\Cms\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends BasePublicIndexController
{
    protected ?string $resource = PromotionResource::class;

    public function __construct(private readonly PromotionService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(Promotion $promotion, Request $request): JsonResponse
    {
        if (! $promotion->is_active) {
            throw new NotFoundException();
        }

        return $this->showResponse($promotion, $request);
    }
}
