<?php

namespace App\Actions\Tickets;

use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Exceptions\TicketClosedException;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use App\Support\AssigneeEligibility;
use App\Support\ClaimGuard;
use App\Support\OperationsQueueType;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of a ticket's assignee (Phase 7, D-07, D-08, D-09, D-21).
 *
 * Under the ticket row lock (a leaf lock): a resolved or closed ticket is
 * refused with `ticket_closed`; the assignee must pass AssigneeEligibility
 * with the registry's work permission (tickets.respond) — checked before the
 * no-op so a current assignee who lost the permission is refused too; the
 * current assignee is a 200 no-op (no timeline row, no event). Otherwise
 * `open → assigned` (assignment row from open to assigned) or, on an active
 * ticket, a swap (assignment row with null from/to — the status did not move).
 *
 * There is no unassign verb. `assigned` is reached only here, through claim
 * and through escalate. With `$claim` (07-09, D-22) the actor assigns
 * themselves: closed check → ClaimGuard → assign, all under this lock; the
 * assignment row carries meta {claim: true}.
 */
class AssignTicketAction
{
    public function handle(Ticket $ticket, User $assignee, User $actor, bool $claim = false): array
    {
        return DB::transaction(function () use ($ticket, $assignee, $actor, $claim) {
            $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! in_array($from, TicketStatus::active(), true)) {
                throw new TicketClosedException(__('custom.errors.ticket_closed'), ['status' => $from->value]);
            }

            // 07-09 (D-22): a claim is mine / nobody's / someone else's, decided
            // under this lock; the claimer passed the work permission, so the
            // eligibility check is skipped for it.
            if ($claim) {
                if (ClaimGuard::check($locked, $actor)) {
                    return ['data' => $locked, 'code' => 200, 'claimed' => false];
                }
            } else {
                AssigneeEligibility::assert($assignee, OperationsQueueType::forModel($locked)->statusPermission);

                if ($locked->assigned_user_id === $assignee->getKey()) {
                    return ['data' => $locked, 'code' => 200, 'claimed' => false];
                }
            }

            $moved = $from === TicketStatus::OPEN;

            $locked->assigned_user_id = $assignee->getKey();

            if ($moved) {
                $locked->status = TicketStatus::ASSIGNED;
            }

            $locked->save();

            TicketAction::create([
                'ticket_id'      => $locked->id,
                'user_id'        => $actor->getKey(),
                'type'           => TicketActionType::ASSIGNMENT,
                'from_status'    => $moved ? TicketStatus::OPEN->value : null,
                'to_status'      => $moved ? TicketStatus::ASSIGNED->value : null,
                'target_user_id' => $assignee->getKey(),
                'meta'           => $claim ? ['claim' => true] : null,
            ]);

            TicketChanged::dispatch($locked);

            return ['data' => $locked, 'code' => 200, 'claimed' => $claim];
        }, 3);
    }
}
