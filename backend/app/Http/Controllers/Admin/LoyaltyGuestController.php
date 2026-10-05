<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Resources\Loyalty\LoyaltyAccountResource;
use App\Http\Resources\Loyalty\LoyaltyLedgerEntryResource;
use App\Models\Guest;
use App\Services\Loyalty\LoyaltyAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff view of any guest's loyalty balance and ledger (Phase 10, LOY-07).
 * `loyalty.view` in the route; the resources add the staff-only fields.
 */
class LoyaltyGuestController extends BaseController
{
    public function __construct(private readonly LoyaltyAccountService $service) {}

    public function show(Guest $guest, Request $request): JsonResponse
    {
        $result = $this->service->account($guest);

        return $this->success(new LoyaltyAccountResource($result['data']), 'custom.messages.success', $result['code'], $request);
    }

    public function ledger(Guest $guest, Request $request): JsonResponse
    {
        $result = $this->service->ledger($guest, $this->indexParams($request), $this->perPageParam($request));

        return $this->paginatedSuccess($result['data'], LoyaltyLedgerEntryResource::class, $request);
    }
}
