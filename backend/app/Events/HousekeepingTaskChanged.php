<?php

namespace App\Events;

use App\Models\HousekeepingTask;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by every housekeeping single writer (create, assign, status,
 * room-board close) and deferred until the outermost transaction commits, so a
 * rolled-back write never emits it (D-11b). Handled by the queued
 * MirrorHousekeepingTaskToFirestore (`ops_queue/housekeeping_task_{uuid}`).
 */
class HousekeepingTaskChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly HousekeepingTask $task) {}
}
