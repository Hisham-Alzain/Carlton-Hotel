<?php

namespace Database\Factories;

use App\Models\LoyaltySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltySetting>
 */
class LoyaltySettingFactory extends Factory
{
    protected $model = LoyaltySetting::class;

    /** A fully configured program: every capability on. The table is a singleton, so create at most one. */
    public function definition(): array
    {
        return [
            'singleton' => 1,
            'earn_rate' => '1.0000',
            'redeem_value_usd' => '0.0100',
            'expiry_months' => 24,
            'expiry_warning_days' => 30,
            'min_redeem_points' => 100,
            'max_redeem_percent' => '50.00',
            'updated_by' => null,
        ];
    }

    /** The state before staff configure anything: the four rate columns are null. */
    public function unset(): static
    {
        return $this->state([
            'earn_rate' => null,
            'redeem_value_usd' => null,
            'min_redeem_points' => null,
            'max_redeem_percent' => null,
        ]);
    }
}
