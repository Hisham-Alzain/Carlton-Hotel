<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Loyalty\LoyaltyReportRequest;
use App\Http\Resources\Loyalty\LoyaltyReportResource;
use App\Services\Loyalty\LoyaltyReportService;
use Illuminate\Http\JsonResponse;

/** Phase 10 (LOY-19, LOY-20): the loyalty report; loyalty.view only. */
class LoyaltyReportController extends BaseController
{
    public function __construct(private readonly LoyaltyReportService $service) {}

    public function index(LoyaltyReportRequest $request): JsonResponse
    {
        $result = $this->service->report(
            $request->validated('date_from'),
            $request->validated('date_to'),
        );

        return $this->success(new LoyaltyReportResource($result['data']), 'custom.messages.success', $result['code'], $request);
    }
}
