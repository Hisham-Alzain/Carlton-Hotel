<?php

namespace Database\Factories;

use App\Models\PricingRule;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        return [
            'room_type_id'   => RoomType::factory(),
            'scope'          => PricingRule::SCOPE_SEASONAL,
            'starts_on'      => now()->addDay()->toDateString(),
            'ends_on'        => now()->addMonths(3)->toDateString(),
            'modifier_type'  => PricingRule::TYPE_PERCENTAGE,
            'modifier_value' => $this->faker->randomFloat(2, 5, 30),
            'is_active'      => true,
        ];
    }
}
