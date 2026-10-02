<?php

namespace App\Actions\Tickets;

use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Exceptions\TicketClosedException;
use App\Exceptions\TicketEscalationInvalidException;
use App\Exceptions\TicketEscalationLimitException;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use App\Support\AssigneeEligibility;
use App\Support\OperationsQueueType;
use Illuminate\Support\Facades\DB;

/**
 * The ticket escalation writer (Phase 7, D-18, D-19, D-20, D-21; A8).
 *
 * The target is explicit — there is no department-manager concept. Guards,
 * under the ticket lock, in this order (FA-7.06-2): not active →
 * `ticket_closed`; target is the actor → `ticket_escalation_invalid {self}`;
 * target is the current assignee → `{same_assignee}`; level already at
 * `hotel.ticket_max_escalation_level` → `ticket_escalation_limit {level, max}`;
 * target fails AssigneeEligibility (tickets.respond) → `assignee_not_eligible`.
 *
 * Effects: assignee := target, level + 1, open → assigned (other active
 * statuses unchanged), one `escalation` row (body = reason, meta {level,
 * previous_assignee_uuid}). A → B → A is allowed; the cap is the termination
 * guarantee. Nothing is scheduled or automatic (PITFALLS #5) and no staff
 * notification is sent (D-20); the live signal is the queue mirror. The
 * escalation timestamp is the row's created_at (A8: the timeline is canonical).
 */
class EscalateTicketAction
{
    public function handle(Ticket $ticket, User $target, string $reason, User $actor): array
    {
        return DB::transaction(function () use ($ticket, $target, $reason, $actor) {
            $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! in_array($from, TicketStatus::active(), true)) {
                throw new TicketClosedException(__('custom.errors.ticket_closed'), ['status' => $from->value]);
            }

            if ($target->getKey() === $actor->getKey()) {
                throw new TicketEscalationInvalidException(__('custom.errors.ticket_escalation_invalid'), ['reason' => 'self']);
            }

            if ($target->getKey() === $locked->assigned_user_id) {
                throw new TicketEscalationInvalidException(__('custom.errors.ticket_escalation_invalid'), ['reason' => 'same_assignee']);
            }

            $max = (int) config('hotel.ticket_max_escalation_level', 3);

            if ($locked->escalation_level >= $max) {
                throw new TicketEscalationLimitException(
                    __('custom.errors.ticket_escalation_limit'),
                    ['level' => $locked->escalation_level, 'max' => $max],
                );
            }

            AssigneeEligibility::assert($target, OperationsQueueType::forModel($locked)->statusPermission);

            $previousUuid = $locked->assigned_user_id === null
                ? null
                : User::whereKey($locked->assigned_user_id)->value('uuid');

            $moved = $from === TicketStatus::OPEN;

            $locked->assigned_user_id = $target->getKey();
            $locked->escalation_level = $locked->escalation_level + 1;

            if ($moved) {
                $locked->status = TicketStatus::ASSIGNED;
            }

            $locked->save();

            TicketAction::create([
                'ticket_id'      => $locked->id,
                'user_id'        => $actor->getKey(),
                'type'           => TicketActionType::ESCALATION,
                'body'           => $reason,
                'from_status'    => $moved ? TicketStatus::OPEN->value : null,
                'to_status'      => $moved ? TicketStatus::ASSIGNED->value : null,
                'target_user_id' => $target->getKey(),
                'meta'           => ['level' => $locked->escalation_level, 'previous_assignee_uuid' => $previousUuid],
            ]);

            TicketChanged::dispatch($locked);

            return ['data' => $locked, 'code' => 200];
        }, 3);
    }
}
