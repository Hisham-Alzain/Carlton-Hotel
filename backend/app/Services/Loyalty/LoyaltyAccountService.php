<?php

namespace App\Services\Loyalty;

use App\Base\BaseService;
use App\Enums\LoyaltyEntryType;
use App\Filters\LoyaltyLedgerFilter;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Support\LoyaltyLedger;
use App\Support\LoyaltyProgram;
use Illuminate\Support\Facades\DB;

/**
 * A guest's loyalty balance and ledger (Phase 10, LOY-06/LOY-07, Q15, Q16).
 * Serves the guest routes (the caller) and the staff routes (any guest): the
 * caller picks the guest, this class never reads the request.
 */
class LoyaltyAccountService extends BaseService
{
    protected string $model = LoyaltyLedgerEntry::class;

    protected ?string $filter = LoyaltyLedgerFilter::class;

    /** Column-limited eager loads: the resource needs only these fields. */
    protected array $with = [
        'batch:id,expires_at',
        'reservation:id,uuid,booking_code',
        'folio:id,uuid',
        'voucher:id,uuid,code',
        'performer:id,uuid,name',
    ];

    public function __construct(private readonly LoyaltyLedger $points) {}

    /**
     * Balance, expiry horizon, lifetime figures and the program values, in
     * three queries (settings row, balances, one grouped ledger sum).
     * Lifetime earned is earn plus positive adjustments minus clawbacks;
     * lifetime redeemed is redeem minus refunds; both floor at zero (FA-10.06-1).
     *
     * @return array{data: array<string, mixed>, code: int}
     */
    public function account(Guest $guest): array
    {
        $program = LoyaltyProgram::current();
        $balances = $this->points->balances($guest->id, $program->expiryWarningDays());
        $sums = $this->ledgerSums($guest);
        $setting = $program->settings();

        return ['data' => [
            'available_points' => $balances['available'],
            'expiring_soon_points' => $balances['expiring_soon'],
            'expiring_soon_window_days' => $program->expiryWarningDays(),
            'next_expiry_at' => $balances['next_expiry_at'],
            'lifetime_earned_points' => max(0, $sums['earn'] + $sums['adjust'] - $sums['clawback']),
            'lifetime_redeemed_points' => max(0, $sums['redeem'] - $sums['refund']),
            'program' => $program->capabilities(),
            'redeem_value_usd' => $setting->redeem_value_usd,
            'min_redeem_points' => $program->minRedeemPoints(),
            'max_redeem_percent' => $setting->max_redeem_percent,
            'guest' => $guest,
        ], 'code' => 200];
    }

    /**
     * The guest's ledger, newest first, narrowed by `LoyaltyLedgerFilter`.
     *
     * @param  array<string, mixed>  $params  the query string, via the controller's indexParams()
     */
    public function ledger(Guest $guest, array $params = [], ?int $perPage = null): array
    {
        $query = $this->query()
            ->where('guest_id', $guest->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $this->makeFilter($params)?->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    /**
     * Credited (positive) points per type, plus the debited (absolute negative)
     * points per type, from one grouped query.
     *
     * @return array{earn: int, adjust: int, clawback: int, redeem: int, refund: int}
     */
    private function ledgerSums(Guest $guest): array
    {
        $rows = DB::table('loyalty_ledger_entries')
            ->where('guest_id', $guest->id)
            ->whereIn('type', [
                LoyaltyEntryType::EARN->value,
                LoyaltyEntryType::ADJUST->value,
                LoyaltyEntryType::CLAWBACK->value,
                LoyaltyEntryType::REDEEM->value,
                LoyaltyEntryType::REFUND->value,
            ])
            ->groupBy('type')
            ->selectRaw(
                'type, '
                .'COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) as credited, '
                .'COALESCE(SUM(CASE WHEN points < 0 THEN -points ELSE 0 END), 0) as debited'
            )
            ->get()
            ->keyBy('type');

        $credited = fn (LoyaltyEntryType $t): int => (int) ($rows->get($t->value)->credited ?? 0);
        $debited = fn (LoyaltyEntryType $t): int => (int) ($rows->get($t->value)->debited ?? 0);

        return [
            'earn' => $credited(LoyaltyEntryType::EARN),
            'adjust' => $credited(LoyaltyEntryType::ADJUST),
            'clawback' => $debited(LoyaltyEntryType::CLAWBACK),
            'redeem' => $debited(LoyaltyEntryType::REDEEM),
            'refund' => $credited(LoyaltyEntryType::REFUND),
        ];
    }
}
