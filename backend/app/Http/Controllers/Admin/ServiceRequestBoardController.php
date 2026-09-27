<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Resources\Operations\ServiceRequestBoardResource;
use App\Models\ServiceRequest;
use App\Services\Operations\ServiceRequestBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff service-request board (Phase 6, SVC-01). Read-only on purpose
 * (D-17): the dashboard progresses a row through
 * `PATCH /operations/queue/service-requests/{uuid}/assign|status`.
 */
class ServiceRequestBoardController extends BaseController
{
    public function __construct(private readonly ServiceRequestBoardService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->index($this->indexParams($request), null, $this->perPageParam($request))['data'],
            ServiceRequestBoardResource::class,
            $request,
        );
    }

    public function show(ServiceRequest $serviceRequest, Request $request): JsonResponse
    {
        $result = $this->service->show($serviceRequest);
        $result['data'] = new ServiceRequestBoardResource($result['data']);

        return $this->respondFromService($result, request: $request);
    }
}
