<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Reports\ReportDashboardRequest;
use App\Services\Reports\ReportService;
use App\Support\HotelClock;
use Illuminate\Http\JsonResponse;

/**
 * The reports dashboard (Phase 9, REPORT-01, D-01, D-16). `reports.view`
 * only — a night auditor with `night_audit.manage` alone sees no revenue.
 */
class ReportController extends BaseController
{
    public function __construct(private readonly ReportService $service) {}

    public function dashboard(ReportDashboardRequest $request): JsonResponse
    {
        // Default period: the hotel's today, not the audit business date (D-16).
        $today = HotelClock::today()->toDateString();
        $from  = $request->validated('date_from') ?? $today;
        $to    = $request->validated('date_to') ?? $today;

        return $this->respondFromService(['data' => $this->service->dashboard($from, $to), 'code' => 200], 'custom.messages.success', $request);
    }
}
