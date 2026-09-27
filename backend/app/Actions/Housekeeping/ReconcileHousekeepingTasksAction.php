<?php

namespace App\Actions\Housekeeping;

use App\Enums\HousekeepingTaskType;
use App\Enums\RoomStatus;
use App\Models\HousekeepingTask;
use App\Models\Room;

/**
 * Repair pass behind `housekeeping:reconcile` (Phase 6, D-02).
 *
 *  - Re-saves every task whose stored dedupe key differs from the derived one
 *    (a row written around the model), so the saving hook rewrites it; the
 *    column itself is never written here.
 *  - Restores the key on open deduped tasks stored with NULL, and reports any
 *    open duplicate that cannot take its key.
 *  - Lists dirty rooms with no open turnover task (a failed check-out listener,
 *    or a room dirtied by hand), for staff to create one with
 *    `POST /housekeeping/tasks`.
 */
class ReconcileHousekeepingTasksAction
{
    public function handle(): array
    {
        $cleared = 0;

        HousekeepingTask::whereNotNull('dedupe_key')->chunkById(200, function ($tasks) use (&$cleared) {
            foreach ($tasks as $task) {
                if ($task->getRawOriginal('dedupe_key') !== $task->derivedDedupeKey()) {
                    $task->save();
                    $cleared++;
                }
            }
        });

        [$restored, $conflicts] = $this->restoreMissingKeys();

        $dirty = Room::where('status', RoomStatus::DIRTY->value)
            ->whereNotIn('id', HousekeepingTask::open()
                ->where('type', HousekeepingTaskType::TURNOVER->value)
                ->select('room_id'))
            ->orderBy('number')
            ->pluck('number')
            ->map(fn ($number) => (string) $number)
            ->values()
            ->all();

        return [
            'data' => [
                'cleared'                      => $cleared,
                'restored'                     => $restored,
                'duplicate_open_tasks'         => $conflicts,
                'dirty_rooms_without_turnover' => $dirty,
            ],
            'code' => 200,
        ];
    }

    /**
     * QA minor 2: an open turnover/stayover/inspection task whose key is NULL
     * (written around the model) does not block a duplicate. Re-saving lets the
     * saving hook re-derive the key. When another row already holds that key
     * the task is a duplicate: it is reported by uuid for staff to close, not
     * closed here, and its key stays NULL (the column is UNIQUE).
     *
     * @return array{0: int, 1: list<string>}
     */
    private function restoreMissingKeys(): array
    {
        $restored  = 0;
        $conflicts = [];

        HousekeepingTask::open()
            ->whereNull('dedupe_key')
            ->whereNotNull('room_id')
            ->whereIn('type', array_map(static fn (HousekeepingTaskType $t) => $t->value, HousekeepingTaskType::deduped()))
            ->chunkById(200, function ($tasks) use (&$restored, &$conflicts) {
                foreach ($tasks as $task) {
                    $key = $task->derivedDedupeKey();

                    if ($key === null) {
                        continue;
                    }

                    if (HousekeepingTask::where('dedupe_key', $key)->exists()) {
                        $conflicts[] = $task->uuid;

                        continue;
                    }

                    $task->save();
                    $restored++;
                }
            });

        return [$restored, $conflicts];
    }
}
