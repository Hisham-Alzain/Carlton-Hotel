<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'status'   => Conversation::STATUS_OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => Conversation::STATUS_CLOSED]);
    }
}
