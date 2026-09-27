<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Operations\IndexDepartureServicesRequest;
use App\Http\Requests\Operations\UpdateDepartureServiceStatusRequest;
use App\Services\Operations\DepartureServiceService;
use Illuminate\Http\JsonResponse;

/**
 * Departure services (Phase 6, SVC-02/03). The list is unpaginated
 * (`data.items` capped at 500 plus `data.meta {count, truncated}`), so it
 * answers through success() rather than paginatedSuccess().
 */
class DepartureServiceController extends BaseController
{
    public function __construct(private readonly DepartureServiceService $service) {}

    public function index(IndexDepartureServicesRequest $request): JsonResponse
    {
        $result = $this->service->index($request->validated('date'), $request->kinds(), $request->statuses());

        return $this->success($result['data'], 'custom.messages.success', $result['code'], $request);
    }

    public function updateStatus(string $uuid, UpdateDepartureServiceStatusRequest $request): JsonResponse
    {
        $result = $this->service->updateStatus(
            $uuid,
            $request->validated('status'),
            $request->validated('source_type'),
            $request->validated('reason'),
            $request->user('users'),
        );

        return $this->success($result['data'], 'custom.messages.departure_service_status_updated', $result['code'], $request);
    }
}
