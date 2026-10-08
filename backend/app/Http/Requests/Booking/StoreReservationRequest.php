<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ReadsIdempotencyKey;
use Illuminate\Validation\Rule;

/**
 * POST /reservations (authenticated guest). Phase 10 adds the optional
 * `loyalty_points` and `voucher_code` (LOY-16); when either is sent the
 * `Idempotency-Key` header is required, otherwise it is ignored. The client can
 * never send a discount or a total: the server prices the redemption (M-7).
 */
class StoreReservationRequest extends BaseRequest
{
    use ReadsIdempotencyKey {
        prepareForValidation as mergeIdempotencyKey;
    }

    public function prepareForValidation(): void
    {
        $this->mergeIdempotencyKey();

        if (is_string($this->input('voucher_code'))) {
            $this->merge(['voucher_code' => strtoupper((string) preg_replace('/\s+/', '', $this->input('voucher_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'room_type_uuid' => ['required', 'string', 'exists:room_types,uuid'],
            'check_in'       => ['required', 'date', 'after_or_equal:today'],
            'check_out'      => ['required', 'date', 'after:check_in'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'promo_code'     => ['nullable', 'string'],
            // Optional party size; null means the default (1 adult, 0 children). 20 = the max_occupancy ceiling.
            'adults'         => ['nullable', 'integer', 'min:1', 'max:20'],
            'children'       => ['nullable', 'integer', 'min:0', 'max:20'],
            'loyalty_points' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'voucher_code'   => ['nullable', 'string', 'max:16'],
            'idempotency_key' => ['nullable', 'string', 'max:64', 'required_with:loyalty_points,voucher_code'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->idempotencyKeyMessages(), [
            'idempotency_key.required_with' => __('custom.errors.idempotency_key_required'),
        ]);
    }
}
