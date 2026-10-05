<?php

namespace App\Http\Requests\Loyalty;

use App\Base\BaseRequest;

/**
 * GET /loyalty/preview (LOY-15): the booking fields of POST /reservations plus
 * the two loyalty inputs, under the same names. The query string is validated
 * like a body. Nothing here is a discount or a total: those are computed
 * server-side (M-7).
 */
class LoyaltyPreviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'room_type_uuid' => ['required', 'string', 'exists:room_types,uuid'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'promo_code' => ['nullable', 'string'],
            'loyalty_points' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'voucher_code' => ['nullable', 'string', 'max:16'],
        ];
    }
}
