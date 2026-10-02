<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TicketStatus: string
{
    use HasValues;

    case OPEN          = 'open';
    case ASSIGNED      = 'assigned';
    case IN_PROGRESS   = 'in_progress';
    case WAITING_GUEST = 'waiting_guest';
    case RESOLVED      = 'resolved';
    case CLOSED        = 'closed';

    /** Statuses that keep a ticket on the operations queue (D-06). */
    public static function active(): array
    {
        return [self::OPEN, self::ASSIGNED, self::IN_PROGRESS, self::WAITING_GUEST];
    }

    /**
     * The enforced D-06 transition table. Every ticket status writer
     * (`UpdateTicketStatusAction`, and the operations-queue ticket arm that
     * delegates to it) checks a move against this list. `closed` is terminal;
     * `resolved → in_progress` is the reopen.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN          => [self::IN_PROGRESS, self::RESOLVED, self::CLOSED],
            self::ASSIGNED      => [self::IN_PROGRESS, self::WAITING_GUEST, self::RESOLVED, self::CLOSED],
            self::IN_PROGRESS   => [self::WAITING_GUEST, self::RESOLVED, self::CLOSED],
            self::WAITING_GUEST => [self::IN_PROGRESS, self::RESOLVED, self::CLOSED],
            self::RESOLVED      => [self::CLOSED, self::IN_PROGRESS],
            self::CLOSED        => [],
        };
    }

    /**
     * Targets a client may request through a status PATCH: the transition
     * table minus ASSIGNED. ASSIGNED is system-managed — reached only through
     * assign, claim or escalate — so it is never an advertised target.
     * `OperationsQueueType::allowedStatuses()` and `TicketResource` read this.
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return array_values(array_filter(
            $this->allowedTransitions(),
            static fn (self $case) => $case !== self::ASSIGNED,
        ));
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
