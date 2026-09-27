<?php

namespace Database\Factories;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ServiceRequestPriority;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only: application code creates tasks through
 * CreateHousekeepingTaskAction::ensureOpen(). The dedupe key is always
 * re-derived by the model's saving hook, whatever a state passes.
 */
class HousekeepingTaskFactory extends Factory
{
    protected $model = HousekeepingTask::class;

    public function definition(): array
    {
        return [
            'room_id'  => Room::factory(),
            'type'     => HousekeepingTaskType::TURNOVER,
            'status'   => HousekeepingTaskStatus::PENDING,
            'priority' => ServiceRequestPriority::NORMAL,
            'due_at'   => now()->addHours(2),
        ];
    }

    public function stayover(): static
    {
        return $this->state(['type' => HousekeepingTaskType::STAYOVER]);
    }

    public function inspection(): static
    {
        return $this->state(['type' => HousekeepingTaskType::INSPECTION]);
    }

    public function request(?ServiceRequest $serviceRequest = null): static
    {
        return $this->state(['type' => HousekeepingTaskType::REQUEST])
            ->for($serviceRequest ?? ServiceRequest::factory(), 'serviceRequest');
    }

    public function assigned(?User $user = null): static
    {
        return $this->state([
            'status'           => HousekeepingTaskStatus::ASSIGNED,
            'assigned_user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state([
            'status'     => HousekeepingTaskStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function done(): static
    {
        return $this->state([
            'status'       => HousekeepingTaskStatus::DONE,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => HousekeepingTaskStatus::CANCELLED]);
    }
}
