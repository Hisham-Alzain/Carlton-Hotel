<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Resources\Loyalty\LoyaltyAccountResource;
use App\Http\Resources\Loyalty\LoyaltyLedgerEntryResource;
use App\Http\Resources\Loyalty\LoyaltyVoucherResource;
use App\Services\Loyalty\LoyaltyAccountService;
use App\Services\Loyalty\LoyaltyVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest-facing loyalty (Phase 10, LOY-06). The guest is always the token's
 * own: there is no id or uuid in these routes to tamper with.
 */
class LoyaltyController extends BaseController
{
    public function __construct(
        private readonly LoyaltyAccountService $service,
        private readonly LoyaltyVoucherService $vouchers,
    ) {}

    public function account(Request $request): JsonResponse
    {
        $result = $this->service->account(auth('guests')->user());

        return $this->success(new LoyaltyAccountResource($result['data']), 'custom.messages.success', $result['code'], $request);
    }

    public function ledger(Request $request): JsonResponse
    {
        $result = $this->service->ledger(auth('guests')->user(), $this->indexParams($request), $this->perPageParam($request));

        return $this->paginatedSuccess($result['data'], LoyaltyLedgerEntryResource::class, $request);
    }

    /** The caller's own vouchers, newest first (LOY-14). */
    public function vouchers(Request $request): JsonResponse
    {
        $result = $this->vouchers->indexForGuest(auth('guests')->user(), $this->indexParams($request), $this->perPageParam($request));

        return $this->paginatedSuccess($result['data'], LoyaltyVoucherResource::class, $request);
    }
}
