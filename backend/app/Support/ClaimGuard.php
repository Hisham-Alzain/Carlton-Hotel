<?php

namespace App\Support;

use App\Exceptions\QueueItemAlreadyClaimedException;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;

/**
 * The claim decision for a queue item (Phase 7, D-22, council A8).
 *
 * Called by each assign writer (AssignTicketAction, AssignHousekeepingTaskAction,
 * AssignRequestAction's service-request arm) inside its own row lock, after the
 * writer's own closed check and before any write (RESEARCH Pattern 2). There is
 * no shared claim-closed code: each writer's closed code (`ticket_closed`,
 * `housekeeping_task_closed`, `service_request_closed`) passes through (council A1).
 *
 * - nobody's  → false: the caller assigns the item to the actor;
 * - the actor's → true: the caller returns a 200 no-op;
 * - someone else's → QueueItemAlreadyClaimedException (409). A claim never overrides.
 */
final class ClaimGuard
{
    public static function check(Ticket|ServiceRequest|HousekeepingTask $item, User $actor): bool
    {
        if ($item->assigned_user_id === null) {
            return false;
        }

        if ((int) $item->assigned_user_id === (int) $actor->getKey()) {
            return true;
        }

        throw new QueueItemAlreadyClaimedException(
            __('custom.errors.queue_item_already_claimed'),
            ['assigned_user_uuid' => $item->assignedUser?->uuid],
        );
    }
}
