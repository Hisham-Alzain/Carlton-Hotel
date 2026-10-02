<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Operations\IndexOperationsStaffRequest;
use App\Http\Resources\Operations\OperationsStaffResource;
use App\Services\Operations\OperationsStaffService;
use Illuminate\Http\JsonResponse;

/**
 * The assignee picker (Phase 7, OPS-02, D-23). Unpaginated (`data.items`
 * capped at 200 plus `data.meta {count, truncated}`), so it answers through
 * success() like DepartureServiceController.
 */
class OperationsStaffController extends BaseController
{
    public function __construct(private readonly OperationsStaffService $service) {}

    public function index(IndexOperationsStaffRequest $request): JsonResponse
    {
        $result = $this->service->index($request->validated());

        return $this->success([
            'items' => OperationsStaffResource::collection($result['data']['items'])->resolve($request),
            'meta'  => $result['data']['meta'],
        ], 'custom.messages.success', $result['code'], $request);
    }
}
