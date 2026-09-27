<?php

namespace App\Actions\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ServiceRequestPriority;
use App\Events\HousekeepingTaskChanged;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only creator of housekeeping tasks (Phase 6, D-02).
 *
 * `ensureOpen()` is find-or-create: it returns the existing open task of the
 * type for the room (or, for a request task, the task of that service request
 * in any status) with code 200, else inserts a pending task with one
 * `null → pending` history row and returns code 201.
 *
 * Lock order (D-05), shared by every task writer: the room row first, then the
 * task rows. Two concurrent creators for one room therefore serialise on the
 * room lock; should one still lose the insert race (a writer that did not take
 * the room lock), the unique `dedupe_key` / `service_request_id` rejects the
 * duplicate and the one caught violation is answered by a locked re-read. If
 * the re-read finds nothing the key is stale (a closed row written around the
 * model) and the violation is rethrown; `housekeeping:reconcile` repairs it.
 * `DB::transaction(fn, 3)` retries deadlocks only, never unique violations.
 *
 * Never assigns the dedupe column: HousekeepingTask's saving hook derives it.
 *
 * Accepted `$attrs`: reservation_id, priority (enum or string, default normal),
 * due_at, notes, reason (history reason), service_request (a ServiceRequest;
 * required for REQUEST, refused for every other type).
 */
class CreateHousekeepingTaskAction
{
    public function ensureOpen(Room $room, HousekeepingTaskType $type, array $attrs, ?User $actor): array
    {
        $serviceRequest = $attrs['service_request'] ?? null;

        if ($type === HousekeepingTaskType::REQUEST && ! $serviceRequest instanceof ServiceRequest) {
            throw new InvalidArgumentException('A request task needs its service request.');
        }

        if ($type !== HousekeepingTaskType::REQUEST && $serviceRequest !== null) {
            throw new InvalidArgumentException('Only request tasks carry a service request.');
        }

        return DB::transaction(function () use ($room, $type, $attrs, $actor, $serviceRequest) {
            $lockedRoom = Room::withTrashed()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            if ($existing = $this->findExisting($lockedRoom, $type, $serviceRequest)) {
                return ['data' => $existing, 'code' => 200];
            }

            $priority = $attrs['priority'] ?? ServiceRequestPriority::NORMAL;

            $task = new HousekeepingTask([
                'room_id'        => $lockedRoom->id,
                'reservation_id' => $attrs['reservation_id'] ?? null,
                'type'           => $type,
                'status'         => HousekeepingTaskStatus::PENDING,
                'priority'       => $priority instanceof ServiceRequestPriority ? $priority : ServiceRequestPriority::from($priority),
                'due_at'         => $attrs['due_at'] ?? null,
                'notes'          => $attrs['notes'] ?? null,
                'created_by'     => $actor?->getKey(),
            ]);

            if ($serviceRequest) {
                $task->serviceRequest()->associate($serviceRequest);
            }

            try {
                $task->save();
            } catch (UniqueConstraintViolationException $e) {
                // Lost the insert race: return the winner, or rethrow a stale key.
                $winner = $this->findExisting($lockedRoom, $type, $serviceRequest);

                if ($winner === null) {
                    throw $e;
                }

                return ['data' => $winner, 'code' => 200];
            }

            HousekeepingTaskStatusHistory::create([
                'housekeeping_task_id' => $task->id,
                'from_status'          => null,
                'to_status'            => HousekeepingTaskStatus::PENDING->value,
                'changed_by'           => $actor?->getKey(),
                'reason'               => $attrs['reason'] ?? null,
            ]);

            HousekeepingTaskChanged::dispatch($task);

            return ['data' => $task->refresh(), 'code' => 201];
        }, 3);
    }

    /** Called with the room row already locked. */
    private function findExisting(Room $room, HousekeepingTaskType $type, ?ServiceRequest $serviceRequest): ?HousekeepingTask
    {
        if ($type === HousekeepingTaskType::REQUEST) {
            return HousekeepingTask::where('service_request_id', $serviceRequest->getKey())->lockForUpdate()->first();
        }

        return HousekeepingTask::open()
            ->where('room_id', $room->id)
            ->where('type', $type->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
