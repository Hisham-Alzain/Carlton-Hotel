<?php

namespace Database\Factories;

use App\Enums\LoyaltyRewardType;
use App\Models\LoyaltyReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyReward>
 */
class LoyaltyRewardFactory extends Factory
{
    protected $model = LoyaltyReward::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->unique()->words(3, true), 'ar' => 'مكافأة '.$this->faker->unique()->numerify('####')],
            'description' => ['en' => $this->faker->sentence(), 'ar' => 'وصف المكافأة'],
            'type' => LoyaltyRewardType::DISCOUNT_VOUCHER,
            'points_cost' => 2500,
            'discount_usd' => '25.00',
            'voucher_valid_days' => 90,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function freeNight(): static
    {
        return $this->state([
            'type' => LoyaltyRewardType::FREE_NIGHT,
            'points_cost' => 10000,
            'discount_usd' => null,
        ]);
    }

    public function roomUpgrade(): static
    {
        return $this->state([
            'type' => LoyaltyRewardType::ROOM_UPGRADE,
            'points_cost' => 5000,
            'discount_usd' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
