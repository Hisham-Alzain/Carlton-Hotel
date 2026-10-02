<?php

namespace App\Actions\Tickets;

use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Exceptions\TicketTransitionException;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single writer of a ticket's status (Phase 7, D-06, D-07, D-21).
 *
 *  - Lock → re-read → rule → write + one `status_change` timeline row, in one
 *    `DB::transaction(fn, 3)`. The ticket lock is a leaf lock: it is never
 *    held while locking rooms, tasks, service requests or folios.
 *  - Moves follow `TicketStatus::allowedTransitions()`; `assigned` is
 *    system-managed (assign, claim, escalate) and always refused here with
 *    `ticket_transition_invalid` {from, to, allowed = allowedTargets()}.
 *  - A reason is required for → closed from anything but resolved and for the
 *    reopen resolved → in_progress (422 validation_failed on `reason`); it is
 *    stored in the row's `body`.
 *  - → in_progress on an unassigned ticket self-assigns the actor (recorded as
 *    the row's `target_user_id`, no second row); → resolved stamps
 *    `resolved_at`; the reopen clears it; → closed stamps `closed_at`.
 *  - The operations-queue ticket arm delegates here (07-08), so both status
 *    routes share one transition table and one timeline.
 */
class UpdateTicketStatusAction
{
    public function handle(Ticket $ticket, TicketStatus $to, ?string $reason, User $actor): array
    {
        return DB::transaction(function () use ($ticket, $to, $reason, $actor) {
            $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if ($to === TicketStatus::ASSIGNED || ! $from->canTransitionTo($to)) {
                throw new TicketTransitionException(
                    __('custom.errors.ticket_transition_invalid'),
                    [
                        'from'    => $from->value,
                        'to'      => $to->value,
                        'allowed' => array_map(static fn (TicketStatus $s) => $s->value, $from->allowedTargets()),
                    ],
                );
            }

            $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

            if ($reason === null && $this->needsReason($from, $to)) {
                throw ValidationException::withMessages([
                    'reason' => [__('custom.validation.ticket_reason_required')],
                ]);
            }

            $selfAssigned = false;

            if ($to === TicketStatus::IN_PROGRESS && $locked->assigned_user_id === null) {
                $locked->assigned_user_id = $actor->getKey();
                $selfAssigned             = true;
            }

            if ($to === TicketStatus::RESOLVED) {
                $locked->resolved_at = now();
            }

            if ($from === TicketStatus::RESOLVED && $to === TicketStatus::IN_PROGRESS) {
                $locked->resolved_at = null;
            }

            if ($to === TicketStatus::CLOSED) {
                $locked->closed_at = now();
            }

            $locked->status = $to;
            $locked->save();

            TicketAction::create([
                'ticket_id'      => $locked->id,
                'user_id'        => $actor->getKey(),
                'type'           => TicketActionType::STATUS_CHANGE,
                'body'           => $reason,
                'from_status'    => $from->value,
                'to_status'      => $to->value,
                'target_user_id' => $selfAssigned ? $actor->getKey() : null,
            ]);

            TicketChanged::dispatch($locked);

            return ['data' => $locked, 'code' => 200];
        }, 3);
    }

    private function needsReason(TicketStatus $from, TicketStatus $to): bool
    {
        return ($to === TicketStatus::CLOSED && $from !== TicketStatus::RESOLVED)
            || ($from === TicketStatus::RESOLVED && $to === TicketStatus::IN_PROGRESS);
    }
}
