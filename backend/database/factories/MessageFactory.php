<?php

namespace Database\Factories;

use App\Enums\MessageSender;
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
            // sender_type is a morph column, not an enum column — store the raw value.
            'sender_type'     => MessageSender::GUEST->value,
            'sender_id'       => Guest::factory(),
            'body'            => $this->faker->sentence(8),
        ];
    }
}
