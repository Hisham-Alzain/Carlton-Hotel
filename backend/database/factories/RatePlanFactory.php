<?php

namespace Database\Factories;

use App\Models\RatePlan;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatePlanFactory extends Factory
{
    protected $model = RatePlan::class;

    public function definition(): array
    {
        return [
            'room_type_id'   => RoomType::factory(),
            'name'           => $this->faker->unique()->words(2, true),
            'prepay_required'=> false,
            'is_active'      => true,
        ];
    }
}
