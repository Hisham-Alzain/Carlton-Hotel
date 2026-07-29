<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;
use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Support\NormalizesPhone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reception creating a booking on a guest's behalf.
 *
 * The guest is identified one of two ways: `guest_uuid` for somebody already on
 * file, or a name plus a phone/email for a new arrival. Unlike the public
 * two-step flow there is no OTP — the guest is at the desk or on the phone, and
 * staff carry the permission that vouches for that.
 */
class StoreAdminReservationRequest extends BaseRequest
{
    use NormalizesPhone;

    public function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $normalized = $this->normalizePhone($this->input('phone'));
            if ($normalized) {
                $this->merge(['phone' => $normalized['e164'], 'phone_country' => $normalized['country']]);
            }
        }

        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            // Identity — either an existing guest, or enough to create one.
            'guest_uuid'    => ['nullable', 'string', 'exists:guests,uuid'],
            'first_name'    => ['required_without:guest_uuid', 'string', 'max:100'],
            'last_name'     => ['required_without:guest_uuid', 'string', 'max:100'],
            'phone'         => ['nullable', 'string'],
            'phone_country' => ['nullable', 'string'],
            'email'         => ['nullable', 'email'],

            'room_type_uuid' => ['required', 'string', 'exists:room_types,uuid'],
            'check_in'       => ['required', 'date', 'after_or_equal:today'],
            'check_out'      => ['required', 'date', 'after:check_in'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'promo_code'     => ['nullable', 'string'],

            // Defaults to confirmed — staff have the guest in front of them, so
            // there is nothing left to verify. `pending` is allowed for a phone
            // booking still waiting on a deposit.
            'status' => ['sometimes', Rule::in([
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
            ])],

            // Defaults to walk_in; `direct` covers reception keying in a booking
            // the guest started elsewhere.
            'source' => ['sometimes', Rule::in([
                ReservationSource::WALK_IN->value,
                ReservationSource::DIRECT->value,
            ])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->filled('guest_uuid')) {
                return;
            }

            if (! $this->filled('phone') && ! $this->filled('email')) {
                $v->errors()->add('identity', __('custom.errors.identity_required'));
            }
        });
    }
}
