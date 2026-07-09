<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;
use App\Support\NormalizesPhone;
use Illuminate\Validation\Validator;

class StoreGuestReservationRequest extends BaseRequest
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
            'room_type_uuid' => ['required', 'string', 'exists:room_types,uuid'],
            'check_in'       => ['required', 'date', 'after_or_equal:today'],
            'check_out'      => ['required', 'date', 'after:check_in'],
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'phone'          => ['nullable', 'string'],
            'phone_country'  => ['nullable', 'string'],
            'email'          => ['nullable', 'email'],
            'payment_method' => ['sometimes', 'in:cash,on_arrival'],
            'promo_code'     => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('phone') && ! $this->filled('email')) {
                $v->errors()->add('identity', __('custom.errors.identity_required'));
            }
        });
    }
}
