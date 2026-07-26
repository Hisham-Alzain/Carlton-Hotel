<?php

namespace Database\Factories;

use App\Enums\ModifierType;
use App\Enums\PricingScope;
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
            'scope'          => PricingScope::SEASONAL,
            'starts_on'      => now()->addDay()->toDateString(),
            'ends_on'        => now()->addMonths(3)->toDateString(),
            'modifier_type'  => ModifierType::PERCENTAGE,
            'modifier_value' => $this->faker->randomFloat(2, 5, 30),
            'is_active'      => true,
        ];
    }
}
