<?php

namespace App\Http\Resources\Loyalty;

use App\Base\BaseResource;
use App\Support\LoyaltyProgram;
use Illuminate\Http\Request;

/**
 * The six program values, the capability flags and when they last changed.
 * Wraps a LoyaltyProgram, so it never queries: rates are the stored decimal
 * strings (or null while a capability is unset), counts are integers.
 *
 * @property LoyaltyProgram $resource
 */
class LoyaltySettingsResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $setting = $this->resource->settings();

        return [
            'earn_rate' => $setting->earn_rate,
            'redeem_value_usd' => $setting->redeem_value_usd,
            'expiry_months' => $setting->expiry_months,
            'expiry_warning_days' => $setting->expiry_warning_days,
            'min_redeem_points' => $setting->min_redeem_points,
            'max_redeem_percent' => $setting->max_redeem_percent,
            'program' => $this->resource->capabilities(),
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }
}
