<?php

namespace App\Actions\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Events\HousekeepingTaskChanged;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Room-board closer (Phase 6, D-04). Called by UpdateRoomStatusAction while it
 * holds the room lock (so the room → task lock order holds) when staff mark a
 * dirty room available on the room board.
 *
 * It records the physical fact the board reports — the room is clean — so it
 * moves the open turnover task straight to `done` from pending, assigned or in
 * progress, bypassing the staff transition table on purpose (the one
 * documented bypass), with history reason `room_board`. It never writes the
 * room and never depends on UpdateRoomStatusAction.
 */
class CloseOpenTurnoverTaskAction
{
    public const REASON = 'room_board';

    public function handle(Room $room, ?User $actor): ?HousekeepingTask
    {
        return DB::transaction(function () use ($room, $actor) {
            $task = HousekeepingTask::open()
                ->where('room_id', $room->getKey())
                ->where('type', HousekeepingTaskType::TURNOVER->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $task) {
                return null;
            }

            $from = $task->status;

            $task->status       = HousekeepingTaskStatus::DONE;
            $task->completed_at = now();
            $task->completed_by = $actor?->getKey();
            $task->save();

            HousekeepingTaskStatusHistory::create([
                'housekeeping_task_id' => $task->id,
                'from_status'          => $from->value,
                'to_status'            => HousekeepingTaskStatus::DONE->value,
                'changed_by'           => $actor?->getKey(),
                'reason'               => self::REASON,
            ]);

            HousekeepingTaskChanged::dispatch($task);

            return $task;
        });
    }
}
