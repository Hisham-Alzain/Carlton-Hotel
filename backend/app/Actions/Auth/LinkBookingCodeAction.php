<?php
namespace App\Actions\Auth;

use App\Exceptions\NotFoundException;
use App\Models\OtpCode;
use App\Models\Reservation;

class LinkBookingCodeAction
{
    public function __construct(private readonly RequestOtpAction $requestOtp) {}

    public function handle(string $bookingCode, ?string $lastName, ?string $phone): array
    {
        // Second factor MUST be in the WHERE — code alone is never sufficient (anti-enumeration)
        $query = Reservation::with('guest')->where('booking_code', $bookingCode);

        if ($lastName && $phone) {
            $query->where(function ($q) use ($lastName, $phone) {
                $q->where('last_name', $lastName)->orWhere('phone', $phone);
            });
        } elseif ($lastName) {
            $query->where('last_name', $lastName);
        } else {
            $query->where('phone', $phone);
        }

        $reservation = $query->first();

        if (! $reservation) {
            // Generic — do NOT reveal whether the code exists
            throw new NotFoundException(__('custom.errors.booking_link_failed'));
        }

        // Issue OTP to the reservation's contact — prefer direct fields, fall back to guest
        $identifier = $reservation->phone
            ?? $reservation->guest?->email
            ?? $reservation->guest?->phone
            ?? throw new NotFoundException(__('custom.errors.booking_link_failed'));
        $channel = str_starts_with($identifier, '+') ? OtpCode::CHANNEL_SMS : OtpCode::CHANNEL_EMAIL;

        $this->requestOtp->handle($identifier, $channel, OtpCode::PURPOSE_BOOKING_LINK);

        // Mask the identifier
        $masked = strlen($identifier) > 4
            ? substr($identifier, 0, 4) . str_repeat('*', strlen($identifier) - 4)
            : '****';

        return [
            'data' => ['identifier_masked' => $masked, 'channel' => $channel],
            'code' => 200,
        ];
    }
}
