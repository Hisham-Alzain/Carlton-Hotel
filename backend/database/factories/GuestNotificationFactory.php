<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\GuestNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestNotificationFactory extends Factory
{
    protected $model = GuestNotification::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'type'     => GuestNotification::TYPE_WELCOME,
            'title'    => $this->faker->sentence(3),
            'body'     => $this->faker->sentence(10),
            'data'     => [],
        ];
    }

    public function department(string $department = 'events'): static
    {
        return $this->state(fn () => [
            'guest_id'   => null,
            'department' => $department,
            'type'       => GuestNotification::TYPE_INQUIRY_ROUTED,
        ]);
    }
}
