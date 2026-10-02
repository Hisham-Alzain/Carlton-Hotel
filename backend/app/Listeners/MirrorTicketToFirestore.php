<?php

namespace App\Listeners;

use App\Events\TicketChanged;
use App\Support\OperationsQueueMirror;
use App\Traits\MirrorsToFirestore;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The Firestore writer for tickets (Phase 7, D-21): every ticket single writer
 * that changes a queue row dispatches TicketChanged after commit and this
 * listener writes `ops_queue/ticket_{uuid}` with the unchanged payload shape.
 * Once the queue ticket arm delegates to the writers (07-08) it is the only one.
 *
 * Queued: production needs a running queue worker for the live view to move.
 * Best effort: a Firestore outage is logged, never raised (MirrorsToFirestore).
 */
class MirrorTicketToFirestore implements ShouldQueue
{
    use MirrorsToFirestore;

    public function handle(TicketChanged $event): void
    {
        $ticket = $event->ticket;
        $ticket->loadMissing(['guest', 'assignedUser']);

        $this->mirrorToFirestore(
            'ops_queue',
            OperationsQueueMirror::documentId($ticket),
            OperationsQueueMirror::payload($ticket),
        );
    }
}
