<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseCRUDController;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Http\Requests\Loyalty\CreateLoyaltyRewardRequest;
use App\Http\Requests\Loyalty\UpdateLoyaltyRewardRequest;
use App\Http\Resources\Loyalty\LoyaltyRewardResource;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyRewardController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = LoyaltyRewardResource::class;

    public function __construct(private readonly LoyaltyRewardService $service) {}

    protected function service(): BaseService
    {
        return $this->service;
    }

    public function show(LoyaltyReward $reward, Request $request): JsonResponse
    {
        return $this->showResponse($reward, $request);
    }

    public function store(CreateLoyaltyRewardRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateLoyaltyRewardRequest $request, LoyaltyReward $reward): JsonResponse
    {
        return $this->updateResponse($request, $reward);
    }

    public function destroy(LoyaltyReward $reward, Request $request): JsonResponse
    {
        return $this->destroyResponse($reward, $request);
    }

    public function restore(LoyaltyReward $reward, Request $request): JsonResponse
    {
        return $this->restoreResponse($reward, $request);
    }

    public function forceDestroy(LoyaltyReward $reward, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($reward, $request);
    }
}
