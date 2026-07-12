<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Service\ApproveCheckInRequest;
use App\Http\Resources\Service\CheckInApprovalResource;
use App\Models\Reservation;
use App\Services\Service\PreArrivalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInApprovalController extends BaseController
{
    public function __construct(private readonly PreArrivalService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess($this->service->adminIndex()['data'], CheckInApprovalResource::class, $request);
    }

    public function approve(ApproveCheckInRequest $request, Reservation $reservation): JsonResponse
    {
        $result = $this->service->approve(
            $reservation,
            $request->validated('status'),
            $request->user(),
            $request->validated('notes'),
        );
        $result['data']   = new CheckInApprovalResource($result['data']);
        $messageKey        = $request->validated('status') === 'approved'
            ? 'custom.messages.check_in_approved'
            : 'custom.messages.check_in_rejected';
        return $this->respondFromService($result, $messageKey, $request);
    }
}
