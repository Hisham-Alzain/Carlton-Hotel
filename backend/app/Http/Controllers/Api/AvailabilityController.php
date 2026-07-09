<?php

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Http\Requests\Booking\CheckAvailabilityRequest;
use App\Http\Requests\Booking\QuoteRequest;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\PricingService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends BaseController
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService      $pricing,
    ) {}

    public function check(CheckAvailabilityRequest $request): JsonResponse
    {
        $result = $this->availability->check(
            $request->validated('room_type_uuid'),
            $request->validated('check_in'),
            $request->validated('check_out'),
        );
        return $this->success($result['data'], 'custom.messages.success', $result['code'], $request);
    }

    public function quote(QuoteRequest $request): JsonResponse
    {
        $result = $this->pricing->quote(
            $request->validated('room_type_uuid'),
            $request->validated('check_in'),
            $request->validated('check_out'),
            $request->validated('promo_code'),
        );
        return $this->success($result['data'], 'custom.messages.success', $result['code'], $request);
    }
}
