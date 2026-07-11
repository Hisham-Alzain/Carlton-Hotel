<?php

namespace App\Http\Controllers\Admin;

use App\Base\BaseController;
use App\Http\Requests\Payment\SettleReservationRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Reservation;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends BaseController
{
    public function __construct(private readonly PaymentService $service) {}

    public function settleReservation(SettleReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $result         = $this->service->settleReservation(
            $reservation,
            $request->validated(),
            $request->user(),
        );
        $result['data'] = new PaymentResource($result['data']);
        return $this->respondFromService($result, 'custom.messages.payment_settled', $request);
    }
}
