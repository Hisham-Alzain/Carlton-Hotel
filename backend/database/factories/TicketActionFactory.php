<?php

namespace Database\Factories;

use App\Enums\TicketActionType;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAction>
 */
class TicketActionFactory extends Factory
{
    protected $model = TicketAction::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'type'      => TicketActionType::CREATED,
            'to_status' => 'open',
        ];
    }

    public function statusChange(string $from, string $to, ?string $reason = null): static
    {
        return $this->state([
            'type'        => TicketActionType::STATUS_CHANGE,
            'from_status' => $from,
            'to_status'   => $to,
            'body'        => $reason,
        ]);
    }

    public function reply(string $body): static
    {
        return $this->state([
            'type'      => TicketActionType::REPLY,
            'body'      => $body,
            'to_status' => null,
        ]);
    }

    public function escalation(User $target, int $level, ?string $previousUuid = null): static
    {
        return $this->state([
            'type'           => TicketActionType::ESCALATION,
            'target_user_id' => $target->id,
            'to_status'      => null,
            'body'           => 'Escalated',
            'meta'           => ['level' => $level, 'previous_assignee_uuid' => $previousUuid],
        ]);
    }

    public function recovery(): static
    {
        return $this->state(['type' => TicketActionType::RECOVERY, 'to_status' => null]);
    }
}
