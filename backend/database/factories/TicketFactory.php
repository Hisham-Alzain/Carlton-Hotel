<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'guest_id'   => Guest::factory(),
            'subject'    => $this->faker->sentence(4),
            'category'   => Ticket::CATEGORY_INQUIRY,
            'status'     => Ticket::STATUS_OPEN,
            'priority'   => 2,
            'department' => Ticket::DEPARTMENT_CONCIERGE,
            'source'     => Ticket::SOURCE_CHATBOT,
        ];
    }
}
