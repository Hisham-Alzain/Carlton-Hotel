<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
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
            'status'   => ConversationStatus::OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => ConversationStatus::CLOSED]);
    }
}
