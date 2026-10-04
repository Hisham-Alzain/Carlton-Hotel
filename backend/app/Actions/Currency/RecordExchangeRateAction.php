<?php

namespace App\Actions\Currency;

use App\Exceptions\ExchangeRateLargeChangeException;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Append a display exchange rate (Phase 9.1, D-14, D-17). Always a new row.
 *
 * Fat-finger guard: when a current rate exists and the new one differs by more
 * than 50% either way, the write needs `confirm_large_change: true` — this also
 * catches a 100× SYP redenomination slip (R-7). The currency's latest row is
 * locked so two concurrent writers compare against the same baseline.
 *
 * bcmath at scale 6 locally rather than FolioLedger: FolioLedger carries 2-dp
 * money semantics, while a rate has 6 decimals and is not money.
 */
class RecordExchangeRateAction
{
    private const SCALE = 6;

    private const MAX_CHANGE_PERCENT = '50';

    /**
     * @param  array{currency: string, rate: string, note?: ?string, confirm_large_change?: bool}  $data
     */
    public function handle(array $data, User $actor): array
    {
        $proposed = bcadd($data['rate'], '0', self::SCALE);

        $rate = DB::transaction(function () use ($data, $actor, $proposed) {
            $current = ExchangeRate::where('currency', $data['currency'])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($current !== null && ! ($data['confirm_large_change'] ?? false)) {
                $this->assertWithinGuard($data['currency'], (string) $current->rate, $proposed);
            }

            return ExchangeRate::create([
                'currency' => $data['currency'],
                'rate' => $proposed,
                'note' => $data['note'] ?? null,
                'set_by' => $actor->id,
            ]);
        });

        return ['data' => $rate->load('setBy:id,uuid,name'), 'code' => 201];
    }

    private function assertWithinGuard(string $currency, string $current, string $proposed): void
    {
        $current = bcadd($current, '0', self::SCALE);
        $percent = bcdiv(bcmul(bcsub($proposed, $current, self::SCALE), '100', self::SCALE), $current, self::SCALE);

        if (bccomp(ltrim($percent, '-'), self::MAX_CHANGE_PERCENT, self::SCALE) <= 0) {
            return;
        }

        throw new ExchangeRateLargeChangeException(__('custom.errors.exchange_rate_large_change'), [
            'currency' => $currency,
            'current_rate' => $current,
            'proposed_rate' => $proposed,
            'change_percent' => $percent,
        ]);
    }
}
