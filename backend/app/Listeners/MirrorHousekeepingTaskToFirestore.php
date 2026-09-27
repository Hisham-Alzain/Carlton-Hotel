<?php

namespace App\Listeners;

use App\Events\HousekeepingTaskChanged;
use App\Support\OperationsQueueMirror;
use App\Traits\MirrorsToFirestore;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The only Firestore writer for housekeeping tasks (Phase 6, D-11b): every
 * single writer dispatches HousekeepingTaskChanged after commit and this
 * listener writes `ops_queue/housekeeping_task_{uuid}`. The queue actions'
 * task arm does not mirror separately, so one write means one mirror.
 *
 * Queued: production needs a running queue worker for the live view to move.
 * Best effort: a Firestore outage is logged, never raised (MirrorsToFirestore).
 */
class MirrorHousekeepingTaskToFirestore implements ShouldQueue
{
    use MirrorsToFirestore;

    public function handle(HousekeepingTaskChanged $event): void
    {
        $task = $event->task;
        $task->loadMissing(['room', 'assignedUser', 'reservation.guest']);

        $this->mirrorToFirestore(
            'ops_queue',
            OperationsQueueMirror::documentId($task),
            OperationsQueueMirror::payload($task),
        );
    }
}
