<?php

namespace App\Actions\Loyalty;

use App\Models\LoyaltySetting;
use App\Models\User;
use App\Support\LoyaltyProgram;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Q4/M-8; singleton upsert; LogsActivity records old/new; lock: loyalty_settings row only.
 *
 * Applies only the keys present in `$values` (an explicit null clears a
 * nullable value, an absent key keeps the stored one). Settings live only in
 * `loyalty_settings`: nothing here touches the public website settings table
 * or a cache, because every consumer reads the row fresh (LoyaltyProgram).
 */
class UpdateLoyaltySettingsAction
{
    private const KEYS = [
        'earn_rate', 'redeem_value_usd', 'expiry_months', 'expiry_warning_days',
        'min_redeem_points', 'max_redeem_percent',
    ];

    /**
     * @param  array<string, mixed>  $values
     * @return array{data: LoyaltyProgram, code: int}
     */
    public function handle(array $values, User $actor): array
    {
        DB::transaction(function () use ($values, $actor): void {
            $setting = LoyaltySetting::query()->where('singleton', 1)->lockForUpdate()->first();

            if ($setting instanceof LoyaltySetting) {
                $this->apply($setting, $values, $actor);

                return;
            }

            try {
                // Inner transaction = savepoint, so a lost first-insert race
                // rolls back only the insert and the outer transaction survives.
                DB::transaction(fn () => $this->apply(new LoyaltySetting, $values, $actor));
            } catch (UniqueConstraintViolationException) {
                $this->apply(
                    LoyaltySetting::query()->where('singleton', 1)->lockForUpdate()->firstOrFail(),
                    $values,
                    $actor,
                );
            }
        });

        return ['data' => LoyaltyProgram::current(), 'code' => 200];
    }

    /** @param  array<string, mixed>  $values */
    private function apply(LoyaltySetting $setting, array $values, User $actor): void
    {
        $setting->fill(Arr::only($values, self::KEYS));
        $setting->updated_by = $actor->id;

        if ($setting->isDirty()) {
            $setting->save();
        }
    }
}
