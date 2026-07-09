<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;
use App\Support\NormalizesPhone;

class VerifyGuestBookingRequest extends BaseRequest
{
    use NormalizesPhone;

    public function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $normalized = $this->normalizePhone($this->input('phone'));
            if ($normalized) {
                $this->merge(['phone' => $normalized['e164']]);
            }
        }
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'reservation_uuid' => ['required', 'string', 'exists:reservations,uuid'],
            'phone'            => ['nullable', 'string'],
            'email'            => ['nullable', 'email'],
            'otp_code'         => ['required', 'string', 'size:6'],
        ];
    }
}
