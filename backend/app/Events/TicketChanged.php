<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by the ticket single writers that change a queue row — create,
 * status, assign/claim, escalate — and deferred until the outermost
 * transaction commits (Phase 7, D-21). Reply and recovery never dispatch it:
 * the queue row is unchanged. Handled by the queued MirrorTicketToFirestore
 * (`ops_queue/ticket_{uuid}`).
 */
class TicketChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Ticket $ticket) {}
}
