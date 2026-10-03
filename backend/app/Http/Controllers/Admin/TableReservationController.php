<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Resources\Dining\TableReservationResource;
use App\Services\Dining\TableReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /cms/table-reservations` — the restaurant's list (Phase 8, D-10).
 * Read-only; gated `service_requests.view` (D-13). Filters: TableReservationFilter.
 */
class TableReservationController extends BaseController
{
    public function __construct(private readonly TableReservationService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->index($this->indexParams($request), null, $this->perPageParam($request))['data'],
            TableReservationResource::class,
            $request,
        );
    }
}
