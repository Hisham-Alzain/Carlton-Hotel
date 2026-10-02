<?php

namespace App\Actions\Tickets;

use App\Enums\TicketActionType;
use App\Enums\TicketStatus;
use App\Exceptions\TicketClosedException;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The internal ticket reply writer (Phase 7, D-16, D-17, D-21).
 *
 * Every reply in this milestone is internal: it appends one `reply` timeline
 * row under the ticket lock, never changes status and is refused only on a
 * closed ticket (`ticket_closed`). A guest-visible reply (TICKET-08) will be
 * the one whose reserved chat-message link is set, so historical rows stay
 * unambiguous. To answer the guest, staff use
 * `POST /cms/conversations/{conversation}/messages` (D-17).
 *
 * No TicketChanged: the operations-queue row is unchanged (D-21).
 */
class ReplyToTicketAction
{
    public function handle(Ticket $ticket, string $body, User $actor): array
    {
        return DB::transaction(function () use ($ticket, $body, $actor) {
            $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === TicketStatus::CLOSED) {
                throw new TicketClosedException(__('custom.errors.ticket_closed'), ['status' => $locked->status->value]);
            }

            TicketAction::create([
                'ticket_id' => $locked->id,
                'user_id'   => $actor->getKey(),
                'type'      => TicketActionType::REPLY,
                'body'      => $body,
            ]);

            return ['data' => $locked, 'code' => 201];
        }, 3);
    }
}
