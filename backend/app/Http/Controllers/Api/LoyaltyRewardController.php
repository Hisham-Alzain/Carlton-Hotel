<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Loyalty\LoyaltyRewardResource;
use App\Services\Loyalty\LoyaltyRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest-facing rewards catalog (Phase 10, LOY-12): active rewards only, no
 * filter params, and no dependency on the program being configured.
 */
class LoyaltyRewardController extends BaseController
{
    public function __construct(private readonly LoyaltyRewardService $service) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->indexPublic($this->perPageParam($request));

        return $this->paginatedSuccess($result['data'], LoyaltyRewardResource::class, $request);
    }
}
