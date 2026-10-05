<?php

namespace App\Http\Resources\Loyalty;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * The loyalty report (Phase 10, LOY-19). Wraps the service's plain array, so it
 * never queries: points are integers, `liability_usd` is a two-decimal string or
 * null while no redeem value is configured.
 *
 * @property array<string, mixed> $resource
 */
class LoyaltyReportResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        return [
            'period' => [
                'date_from' => $report['period']['date_from'],
                'date_to' => $report['period']['date_to'],
                'timezone' => $report['period']['timezone'],
            ],
            'issued_points' => (int) $report['issued_points'],
            'redeemed_points' => (int) $report['redeemed_points'],
            'expired_points' => (int) $report['expired_points'],
            'refunded_points' => (int) $report['refunded_points'],
            'clawed_back_points' => (int) $report['clawed_back_points'],
            'adjusted_out_points' => (int) $report['adjusted_out_points'],
            'outstanding_points' => (int) $report['outstanding_points'],
            'liability_usd' => $report['liability_usd'],
        ];
    }
}
