<?php

namespace App\Actions\Booking;

use App\Exceptions\InvalidPromoException;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\RoomType;
use Carbon\Carbon;

class QuoteReservationAction
{
    // Pricing chain: base → pricing_rules → promo. Never mutates state.
    public function handle(RoomType $roomType, string $checkIn, string $checkOut, ?string $promoCode = null): array
    {
        $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        $daily  = (float) $roomType->base_price_usd;

        // Apply matching pricing rules (date overlap)
        $rules = PricingRule::where('room_type_id', $roomType->id)
            ->where('is_active', true)
            ->where('starts_on', '<', $checkOut)
            ->where('ends_on', '>', $checkIn)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->modifier_type === PricingRule::TYPE_PERCENTAGE) {
                $daily *= 1 + ((float) $rule->modifier_value / 100);
            } else {
                $daily += (float) $rule->modifier_value;
            }
        }

        $subtotal = round($daily * $nights, 2);
        $discount = 0.0;
        $promoModel = null;

        if ($promoCode) {
            $promoModel = PromoCode::where('code', $promoCode)->first();

            if (! $promoModel || ! $promoModel->isValid()) {
                throw new InvalidPromoException(__('custom.errors.invalid_promo'));
            }

            if ($promoModel->type === PromoCode::TYPE_PERCENTAGE) {
                $discount = round($subtotal * ((float) $promoModel->value / 100), 2);
            } else {
                $discount = min((float) $promoModel->value, $subtotal);
            }
        }

        $total = max(0, round($subtotal - $discount, 2));

        return [
            'daily_rate_usd' => round($daily, 2),
            'nights'         => $nights,
            'subtotal_usd'   => $subtotal,
            'discount_usd'   => $discount,
            'total_usd'      => $total,
            'promo_code_id'  => $promoModel?->id,
            'rules_applied'  => $rules->count(),
        ];
    }
}
