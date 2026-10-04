<?php

namespace App\Http\Requests\Loyalty;

use App\Base\BaseRequest;

/**
 * PUT /cms/loyalty/settings (LOY-01). Every key is optional (`sometimes`): an
 * absent key keeps the stored value, an explicit null clears a nullable one.
 * The bounds match the column precisions in loyalty_settings. Access is gated
 * by route middleware (permission:loyalty.manage).
 */
class UpdateLoyaltySettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'earn_rate' => ['sometimes', 'nullable', 'decimal:0,4', 'min:0', 'max:9999.9999'],
            'redeem_value_usd' => ['sometimes', 'nullable', 'decimal:0,4', 'min:0', 'max:999999.9999'],
            'expiry_months' => ['sometimes', 'required', 'integer', 'min:1', 'max:120'],
            'expiry_warning_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'min_redeem_points' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'max_redeem_percent' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0.01', 'max:100'],
        ];
    }
}
