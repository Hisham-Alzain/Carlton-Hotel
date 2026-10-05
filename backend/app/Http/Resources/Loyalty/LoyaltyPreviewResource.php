<?php

namespace App\Http\Resources\Loyalty;

use App\Base\BaseResource;
use App\Models\LoyaltyVoucher;
use Illuminate\Http\Request;

/**
 * The booking preview (LOY-15). Wraps the array built by PreviewLoyaltyAction,
 * so it never queries. Money is a two-decimal string; the voucher is reduced to
 * its code and type and there are no ids.
 *
 * @property array<string, mixed> $resource
 */
class LoyaltyPreviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $preview = $this->resource;
        $voucher = $preview['voucher'];

        return [
            'quote' => $preview['quote'],
            'loyalty' => [
                'points_redeemed' => $preview['points_redeemed'],
                'points_discount_usd' => $preview['points_discount_usd'],
                'voucher' => $voucher instanceof LoyaltyVoucher
                    ? ['code' => $voucher->code, 'type' => $voucher->type->value]
                    : null,
                'voucher_discount_usd' => $preview['voucher_discount_usd'],
                'upgrade_requested' => $preview['upgrade_requested'],
            ],
            'net_total_usd' => $preview['net_total_usd'],
            'available_points' => $preview['available_points'],
            'max_points' => $preview['max_points'],
            'points_earnable_estimate' => $preview['points_earnable_estimate'],
            'program' => $preview['program'],
        ];
    }
}
