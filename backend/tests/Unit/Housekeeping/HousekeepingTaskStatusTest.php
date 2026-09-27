<?php

namespace Tests\Unit\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use PHPUnit\Framework\TestCase;

/**
 * The D-05 task transition table and the D-01 type list, without a database.
 */
class HousekeepingTaskStatusTest extends TestCase
{
    public function test_allowed_targets_match_the_d05_table(): void
    {
        $expected = [
            'pending'     => ['assigned', 'in_progress', 'cancelled'],
            'assigned'    => ['in_progress', 'cancelled'],
            'in_progress' => ['done', 'cancelled'],
            'done'        => [],
            'cancelled'   => [],
        ];

        foreach (HousekeepingTaskStatus::cases() as $status) {
            $this->assertSame(
                $expected[$status->value],
                array_map(static fn (HousekeepingTaskStatus $s) => $s->value, $status->allowedTargets()),
                $status->value,
            );
        }
    }

    public function test_can_transition_to_agrees_with_the_table(): void
    {
        foreach (HousekeepingTaskStatus::cases() as $from) {
            foreach ($from->allowedTargets() as $to) {
                $this->assertTrue($from->canTransitionTo($to), "{$from->value} -> {$to->value}");
            }
        }

        $this->assertFalse(HousekeepingTaskStatus::PENDING->canTransitionTo(HousekeepingTaskStatus::DONE));
        $this->assertFalse(HousekeepingTaskStatus::ASSIGNED->canTransitionTo(HousekeepingTaskStatus::ASSIGNED));
        $this->assertFalse(HousekeepingTaskStatus::DONE->canTransitionTo(HousekeepingTaskStatus::PENDING));
        $this->assertFalse(HousekeepingTaskStatus::CANCELLED->canTransitionTo(HousekeepingTaskStatus::IN_PROGRESS));
    }

    public function test_open_statuses(): void
    {
        $this->assertSame(
            [HousekeepingTaskStatus::PENDING, HousekeepingTaskStatus::ASSIGNED, HousekeepingTaskStatus::IN_PROGRESS],
            HousekeepingTaskStatus::open(),
        );

        foreach (HousekeepingTaskStatus::cases() as $status) {
            $this->assertSame(in_array($status, HousekeepingTaskStatus::open(), true), $status->isOpen(), $status->value);
        }
    }

    public function test_task_types(): void
    {
        $this->assertSame(['turnover', 'stayover', 'inspection', 'request'], HousekeepingTaskType::values());
        $this->assertSame(
            [HousekeepingTaskType::TURNOVER, HousekeepingTaskType::STAYOVER, HousekeepingTaskType::INSPECTION],
            HousekeepingTaskType::deduped(),
        );
    }
}
