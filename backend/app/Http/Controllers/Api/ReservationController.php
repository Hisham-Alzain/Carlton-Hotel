<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\VerifyOtpAction;
use App\Base\BaseController;
use App\Exceptions\NotFoundException;
use App\Http\Requests\Booking\StoreGuestReservationRequest;
use App\Http\Requests\Booking\StoreReservationRequest;
use App\Http\Requests\Booking\VerifyGuestBookingRequest;
use App\Http\Resources\Booking\ReservationResource;
use App\Http\Resources\GuestResource;
use App\Models\OtpCode;
use App\Models\Reservation;
use App\Services\Booking\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends BaseController
{
    public function __construct(
        private readonly ReservationService $service,
        private readonly VerifyOtpAction    $verifyOtp,
    ) {}

    // Authenticated (app-only): one-step booking
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $result = $this->service->store(auth('guests')->user(), $request->validated());
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedSuccess(
            $this->service->index(auth('guests')->user())['data'],
            ReservationResource::class,
            $request
        );
    }

    public function show(Reservation $reservation, Request $request): JsonResponse
    {
        // Guest can only see their own reservations
        if ($reservation->guest_id !== auth('guests')->id()) {
            throw new NotFoundException();
        }
        $result = $this->service->show($reservation);
        $result['data'] = new ReservationResource($result['data']);
        return $this->respondFromService($result, request: $request);
    }

    public function cancel(Reservation $reservation, Request $request): JsonResponse
    {
        if ($reservation->guest_id !== auth('guests')->id()) {
            throw new NotFoundException();
        }
        $result = $this->service->cancel($reservation);
        return $this->success(null, 'custom.messages.deleted', $result['code'], $request);
    }

    // Public step 1: create pending_verification reservation + send OTP
    public function storeAsGuest(StoreGuestReservationRequest $request): JsonResponse
    {
        $result = $this->service->storeAsGuest($request->validated());
        return $this->success($result['data'], 'custom.messages.otp_sent', 200, $request);
    }

    // Public step 2: verify OTP → activate reservation → issue guest token
    public function verifyGuestBooking(VerifyGuestBookingRequest $request): JsonResponse
    {
        $reservation = Reservation::with('guest')
            ->where('uuid', $request->validated('reservation_uuid'))
            ->where('status', Reservation::STATUS_PENDING_VERIFICATION)
            ->firstOrFail();

        $identifier = $request->validated('phone') ?? $request->validated('email');

        // Guard: identifier must match the contact that made the original booking
        $linked = $reservation->guest;
        if ($linked && ($linked->phone !== $identifier && $linked->email !== $identifier)) {
            throw new NotFoundException();
        }

        // Reuse P1 OTP verification with booking_verification purpose
        $otpResult = $this->verifyOtp->handle(
            $identifier,
            $request->validated('otp_code'),
            OtpCode::PURPOSE_BOOKING_VERIFICATION,
        );

        $guest  = $otpResult['data']['guest'];
        $result = $this->service->verifyGuestBooking($reservation->uuid, $guest, $reservation);

        return $this->success([
            'reservation' => new ReservationResource($result['data']['reservation']),
            'guest'       => new GuestResource($result['data']['guest']),
            'token'       => $result['data']['token'],
        ], 'custom.messages.reservation_confirmed', 200, $request);
    }
}
