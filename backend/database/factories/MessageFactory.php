<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Guest;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_type'     => Message::SENDER_GUEST,
            'sender_id'       => Guest::factory(),
            'body'            => $this->faker->sentence(8),
        ];
    }
}
