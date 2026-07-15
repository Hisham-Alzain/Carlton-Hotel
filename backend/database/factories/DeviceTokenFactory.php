<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'guest_id'     => Guest::factory(),
            'token'        => $this->faker->uuid() . ':' . $this->faker->uuid(),
            'platform'     => DeviceToken::PLATFORM_ANDROID,
            'last_used_at' => now(),
        ];
    }
}
