<?php
namespace App\Http\Controllers\Auth;

use App\Base\BaseController;
use App\Http\Requests\Auth\LinkBookingCodeRequest;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\UpdateGuestProfileRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\GuestResource;
use App\Services\Auth\AuthGuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestAuthController extends BaseController
{
    public function __construct(private readonly AuthGuestService $service) {}

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $identifier = $request->validated('phone') ?? $request->validated('email');
        $result = $this->service->requestOtp(
            $identifier,
            $request->validated('channel'),
            $request->validated('purpose'),
        );
        return $this->success($result['data'], 'custom.auth.otp_sent', 200, $request);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $identifier = $request->validated('phone') ?? $request->validated('email');
        $result = $this->service->verifyOtp(
            $identifier,
            $request->validated('code'),
            $request->validated('purpose'),
            $request->validated('booking_code'),
        );
        return $this->success([
            'guest' => new GuestResource($result['data']['guest']),
            'token' => $result['data']['token'],
        ], 'custom.auth.otp_verified', 200, $request);
    }

    public function linkBookingCode(LinkBookingCodeRequest $request): JsonResponse
    {
        $result = $this->service->linkBookingCode(
            $request->validated('booking_code'),
            $request->validated('last_name'),
            $request->validated('phone'),
        );
        return $this->success($result['data'], 'custom.auth.otp_sent', 200, $request);
    }

    public function me(Request $request): JsonResponse
    {
        $result = $this->service->me($request->user('guests'));
        return $this->success(new GuestResource($result['data']), 'custom.messages.success', 200, $request);
    }

    // Completes the profile after OTP sign-in (first/last name, plus whichever
    // of phone/email the guest did not sign in with).
    public function updateProfile(UpdateGuestProfileRequest $request): JsonResponse
    {
        $result = $this->service->updateProfile($request->user('guests'), $request->validated());
        return $this->success(new GuestResource($result['data']), 'custom.messages.profile_updated', 200, $request);
    }
}
