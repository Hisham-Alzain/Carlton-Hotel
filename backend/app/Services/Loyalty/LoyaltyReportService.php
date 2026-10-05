<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyBatchStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\LoyaltyEarnBatch;
use App\Support\HotelClock;
use App\Support\LoyaltyMath;
use App\Support\LoyaltyProgram;
use Illuminate\Support\Facades\DB;

/**
 * The staff loyalty report (Phase 10, LOY-19, Q19): points issued, redeemed,
 * expired, refunded, clawed back and adjusted out over a hotel-local period,
 * plus the points still outstanding and what they are worth.
 *
 * The period is the half-open UTC window [dayWindow(from).start,
 * dayWindow(to).end) over `occurred_at`, so hotel-local day bounds and DST are
 * handled once, in HotelClock, and no SQL date function is used. Loyalty owns
 * this report end to end: no Phase 9 report class or permission is involved.
 * Exactly three queries run: the settings row, one grouped ledger aggregate and
 * one outstanding sum, however large the ledger is.
 */
class LoyaltyReportService
{
    /**
     * @param  string|null  $dateFrom  Y-m-d hotel-local; null means the hotel's today
     * @param  string|null  $dateTo  Y-m-d hotel-local; null means the hotel's today
     * @return array{data: array<string, mixed>, code: int}
     */
    public function report(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $today = HotelClock::today()->format('Y-m-d');
        $dateFrom ??= $today;
        $dateTo ??= $today;

        [$start] = HotelClock::dayWindow($dateFrom);
        [, $end] = HotelClock::dayWindow($dateTo);

        $sums = $this->ledgerSums($start, $end);
        $outstanding = $this->outstandingPoints();
        $redeemValue = LoyaltyProgram::current()->settings()->redeem_value_usd;

        return ['data' => [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'timezone' => HotelClock::timezone(),
            ],
            // Refunds are reported on their own line, never as issued points (FA-10.14-1).
            'issued_points' => $sums['earn']['credited'] + $sums['adjust']['credited'],
            'redeemed_points' => $sums['redeem']['debited'],
            'expired_points' => $sums['expire']['debited'],
            'refunded_points' => $sums['refund']['credited'],
            'clawed_back_points' => $sums['clawback']['debited'],
            'adjusted_out_points' => $sums['adjust']['debited'],
            // Point in time (now), not as of the period end (FA-10.14-2).
            'outstanding_points' => $outstanding,
            'liability_usd' => $redeemValue !== null && bccomp($redeemValue, '0', 4) === 1
                ? LoyaltyMath::discountForPoints($outstanding, $redeemValue)
                : null,
        ], 'code' => 200];
    }

    /**
     * Credited (positive) and debited (absolute negative) points per entry
     * type in the window, from one grouped query on the (type, occurred_at)
     * index.
     *
     * @return array<string, array{credited: int, debited: int}> keyed by every entry type value
     */
    private function ledgerSums(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = DB::table('loyalty_ledger_entries')
            ->whereIn('type', LoyaltyEntryType::values())
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->groupBy('type')
            ->selectRaw(
                'type, '
                .'COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) as credited, '
                .'COALESCE(SUM(CASE WHEN points < 0 THEN -points ELSE 0 END), 0) as debited'
            )
            ->get()
            ->keyBy('type');

        $sums = [];
        foreach (LoyaltyEntryType::values() as $type) {
            $sums[$type] = [
                'credited' => (int) ($rows->get($type)->credited ?? 0),
                'debited' => (int) ($rows->get($type)->debited ?? 0),
            ];
        }

        return $sums;
    }

    /** Spendable points right now: active batches that have not yet reached their expiry. */
    private function outstandingPoints(): int
    {
        return (int) LoyaltyEarnBatch::query()
            ->where('status', LoyaltyBatchStatus::ACTIVE->value)
            ->where('expires_at', '>', now())
            ->sum('points_remaining');
    }
}
