<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Enums\TicketCategory;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
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
            'category'   => TicketCategory::INQUIRY,
            'status'     => TicketStatus::OPEN,
            'priority'   => 2,
            'department' => Department::CONCIERGE,
            'source'     => TicketSource::CHATBOT,
        ];
    }
}
