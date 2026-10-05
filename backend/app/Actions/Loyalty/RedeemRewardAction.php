<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyVoucherStatus;
use App\Exceptions\LoyaltyInsufficientPointsException;
use App\Exceptions\LoyaltyRewardUnavailableException;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyVoucher;
use App\Support\HotelClock;
use App\Support\IdempotentWrite;
use App\Support\LoyaltyLedger;
use Illuminate\Support\Facades\DB;

/**
 * A guest spends points on a catalog reward and receives a voucher (Phase 10,
 * LOY-13, Q4, Q5, Q13, Q25).
 *
 * Lock order is guest -> batches (M-6): the guest row is locked here, then
 * LoyaltyLedger locks the batches it consumes. The ledger key is
 * `redeem:reward:{guest_id}:{client_key}`; a replay (same key, same reward)
 * answers the stored voucher with 200 and writes nothing, the same key for
 * another reward is an IdempotencyConflictException (409). Everything runs in
 * one transaction, so a failure after the points are consumed leaves neither a
 * voucher nor spent points.
 *
 * The program's min-redeem setting (Q5) applies to free-form point spending
 * only, never to a catalog reward, and a reward needs no settings row (Q4):
 * the reward carries its own cost.
 */
class RedeemRewardAction
{
    /** Crockford base32: no I, L, O or U, so a code survives being read aloud. */
    private const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private readonly LoyaltyLedger $ledger) {}

    /**
     * @return array{data: LoyaltyVoucher, code: int}
     *
     * @throws LoyaltyRewardUnavailableException when the reward is inactive or trashed
     * @throws LoyaltyInsufficientPointsException when the unexpired balance is below the cost
     */
    public function handle(Guest $guest, LoyaltyReward $reward, string $key): array
    {
        return DB::transaction(function () use ($guest, $reward, $key) {
            $locked = Guest::whereKey($guest->id)->lockForUpdate()->firstOrFail();
            $ledgerKey = 'redeem:reward:'.$locked->id.':'.$key;

            [$voucher, $replayed] = IdempotentWrite::run(
                $ledgerKey,
                fn () => LoyaltyLedgerEntry::where('idempotency_key', $ledgerKey)->first()?->voucher,
                fn (LoyaltyVoucher $stored) => (int) $stored->loyalty_reward_id === (int) $reward->id,
                fn () => $this->issue($locked, $reward, $ledgerKey),
            );

            return ['data' => $voucher->load('reservation:id,uuid,booking_code'), 'code' => $replayed ? 200 : 201];
        });
    }

    private function issue(Guest $locked, LoyaltyReward $bound, string $ledgerKey): LoyaltyVoucher
    {
        // Re-read under the guest lock: the bound model may predate a deactivation or a delete.
        $reward = LoyaltyReward::query()->find($bound->id);

        if ($reward === null || ! $reward->is_active) {
            throw new LoyaltyRewardUnavailableException(__('custom.errors.loyalty_reward_unavailable'));
        }

        $allocations = $this->ledger->consume($locked->id, $reward->points_cost);

        $voucher = LoyaltyVoucher::query()->create([
            'code' => $this->generateCode(),
            'guest_id' => $locked->id,
            'loyalty_reward_id' => $reward->id,
            'type' => $reward->type,
            'reward_name' => $reward->getTranslations('name'),
            'value_usd' => $reward->type === LoyaltyRewardType::DISCOUNT_VOUCHER ? $reward->discount_usd : null,
            'points_spent' => $reward->points_cost,
            'status' => LoyaltyVoucherStatus::ACTIVE,
            // Q13: valid through the end of the hotel-local day, so a guest never loses it mid-day.
            'expires_at' => HotelClock::today()->addDays($reward->voucher_valid_days)->endOfDay()->utc(),
        ]);

        $this->ledger->record([
            'guest_id' => $locked->id,
            'type' => LoyaltyEntryType::REDEEM,
            'points' => -$reward->points_cost,
            'voucher_id' => $voucher->id,
            'idempotency_key' => $ledgerKey,
        ], $allocations);

        return $voucher;
    }

    private function generateCode(): string
    {
        do {
            $code = 'LOY-';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, 31)];
            }
        } while (LoyaltyVoucher::where('code', $code)->exists());

        return $code;
    }
}
