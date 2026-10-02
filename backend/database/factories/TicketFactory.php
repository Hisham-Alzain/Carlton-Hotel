<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Enums\TicketCategory;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
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

    /** A staff-created internal ticket with no guest (D-04). */
    public function staff(): static
    {
        return $this->state(['source' => TicketSource::STAFF, 'guest_id' => null]);
    }

    /** Linked to a stay; the guest is derived from the reservation (D-05). */
    public function withReservation(?Reservation $reservation = null): static
    {
        return $this->state(function () use ($reservation) {
            $reservation ??= Reservation::factory()->create();

            return ['reservation_id' => $reservation->id, 'guest_id' => $reservation->guest_id];
        });
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['status' => TicketStatus::ASSIGNED, 'assigned_user_id' => $user->id]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TicketStatus::IN_PROGRESS]);
    }

    public function waitingGuest(): static
    {
        return $this->state(['status' => TicketStatus::WAITING_GUEST]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => TicketStatus::RESOLVED, 'resolved_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => TicketStatus::CLOSED, 'closed_at' => now()]);
    }

    public function escalated(int $level): static
    {
        return $this->state(['escalation_level' => $level]);
    }
}
