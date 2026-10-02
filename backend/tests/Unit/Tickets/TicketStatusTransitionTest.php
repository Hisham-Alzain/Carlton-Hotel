<?php

namespace Tests\Unit\Tickets;

use App\Enums\TicketStatus;
use PHPUnit\Framework\TestCase;

/**
 * The D-06 ticket transition table, without a database.
 */
class TicketStatusTransitionTest extends TestCase
{
    private const TABLE = [
        'open'          => ['in_progress', 'resolved', 'closed'],
        'assigned'      => ['in_progress', 'waiting_guest', 'resolved', 'closed'],
        'in_progress'   => ['waiting_guest', 'resolved', 'closed'],
        'waiting_guest' => ['in_progress', 'resolved', 'closed'],
        'resolved'      => ['closed', 'in_progress'],
        'closed'        => [],
    ];

    private static function values(array $cases): array
    {
        return array_map(static fn (TicketStatus $s) => $s->value, $cases);
    }

    public function test_allowed_transitions_match_the_d06_table(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertEqualsCanonicalizing(
                self::TABLE[$status->value],
                self::values($status->allowedTransitions()),
                $status->value,
            );
        }
    }

    public function test_allowed_targets_are_transitions_without_assigned(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $targets = $status->allowedTargets();

            $this->assertNotContains(TicketStatus::ASSIGNED, $targets, $status->value);
            $this->assertSame(
                array_values(array_filter(
                    self::values($status->allowedTransitions()),
                    static fn (string $v) => $v !== 'assigned',
                )),
                self::values($targets),
                $status->value,
            );
        }

        $this->assertSame([], TicketStatus::CLOSED->allowedTargets());
        $this->assertSame(['in_progress', 'resolved', 'closed'], self::values(TicketStatus::OPEN->allowedTargets()));
    }

    public function test_active_is_the_four_working_statuses(): void
    {
        $this->assertSame(
            [TicketStatus::OPEN, TicketStatus::ASSIGNED, TicketStatus::IN_PROGRESS, TicketStatus::WAITING_GUEST],
            TicketStatus::active(),
        );
    }

    public function test_values_are_declared_in_lifecycle_order(): void
    {
        $this->assertSame(
            ['open', 'assigned', 'in_progress', 'waiting_guest', 'resolved', 'closed'],
            TicketStatus::values(),
        );
    }
}
