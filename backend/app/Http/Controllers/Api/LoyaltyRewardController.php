<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Loyalty\RedeemLoyaltyRewardRequest;
use App\Http\Resources\Loyalty\LoyaltyRewardResource;
use App\Http\Resources\Loyalty\LoyaltyVoucherResource;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyRewardService;
use App\Services\Loyalty\LoyaltyVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest-facing rewards catalog (Phase 10, LOY-12): active rewards only, no
 * filter params, and no dependency on the program being configured.
 */
class LoyaltyRewardController extends BaseController
{
    public function __construct(
        private readonly LoyaltyRewardService $service,
        private readonly LoyaltyVoucherService $vouchers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->indexPublic($this->perPageParam($request));

        return $this->paginatedSuccess($result['data'], LoyaltyRewardResource::class, $request);
    }

    /** Spend the reward's points on a voucher, once per Idempotency-Key (LOY-13). */
    public function redeem(RedeemLoyaltyRewardRequest $request, LoyaltyReward $reward): JsonResponse
    {
        $result = $this->vouchers->redeem(
            auth('guests')->user(),
            $reward,
            (string) $request->validated('idempotency_key'),
        );

        return $this->success(new LoyaltyVoucherResource($result['data']), 'custom.messages.loyalty_reward_redeemed', $result['code'], $request);
    }
}
