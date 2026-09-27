---
phase: 06-housekeeping-guest-services
plan: 03
subsystem: housekeeping task writers and room/request coupling
requires: [06-02]
provides: [UpdateHousekeepingTaskStatusAction, AssignHousekeepingTaskAction, CloseOpenTurnoverTaskAction, HousekeepingTaskTransitionException, HousekeepingTaskClosedException, housekeeping lang keys]
key-files:
  created:
    - backend/app/Actions/Housekeeping/UpdateHousekeepingTaskStatusAction.php
    - backend/app/Actions/Housekeeping/AssignHousekeepingTaskAction.php
    - backend/app/Actions/Housekeeping/CloseOpenTurnoverTaskAction.php
    - backend/app/Exceptions/HousekeepingTaskTransitionException.php
    - backend/app/Exceptions/HousekeepingTaskClosedException.php
    - backend/tests/Unit/Housekeeping/UpdateHousekeepingTaskStatusActionTest.php
    - backend/tests/Unit/Housekeeping/AssignHousekeepingTaskActionTest.php
    - backend/tests/Feature/Housekeeping/RoomBoardClosesTurnoverTest.php
  modified:
    - backend/app/Actions/Cms/UpdateRoomStatusAction.php
    - backend/app/Actions/Operations/UpdateRequestStatusAction.php
    - backend/app/Services/Operations/OperationsQueueService.php
    - backend/tests/Feature/Housekeeping/RequestTaskOnServiceRequestTest.php
    - backend/lang/{en,ar,es,fr,tr}/custom.php
completed: 2026-09-27
---

# 06-03 — Task writers wired to rooms and requests

## Shipped
- `UpdateHousekeepingTaskStatusAction` (`ROOM_STATUS_REASON = 'turnover'`): `DB::transaction(fn, 3)`, room row locked then task row; D-05 transition table (rejects throw `housekeeping_task_transition_invalid` {from, to, allowed}, nothing written); one history row per accepted move; `in_progress` stamps `started_at` once and self-assigns; `done` stamps `completed_at/completed_by`; D-07 room hook (turnover `done` only: dirty → `UpdateRoomStatusAction(AVAILABLE, 'turnover')`, available → nothing, maintenance → activity `room_left_in_maintenance: true`); D-11 request task `done` completes its still-active service request (request action resolved lazily through the container to avoid recursive injection); dispatches `HousekeepingTaskChanged`.
- `AssignHousekeepingTaskAction`: room → task locks; pending → assigned with history (reason `assigned`); assigned/in_progress swap assignee only (activity log); closed → `housekeeping_task_closed` {status}.
- `CloseOpenTurnoverTaskAction` (reason `room_board`): the documented bypass of the transition table; moves an open turnover straight to done; never writes the room.
- `UpdateRoomStatusAction` now injects the closer and calls it on dirty → available unless the reason is `turnover`.
- `UpdateRequestStatusAction::handle(item, status, ?User $actor = null, ?string $reason = null)`: existing update/refresh/mirror lines unchanged (Pitfall B); service request → completed|cancelled cancels open tasks where `service_request_id` = request (reason `service_request_closed`). `OperationsQueueService::updateStatus` passes the actor.
- Lang: 2 error + 4 message keys in all five locales.

## Deviations from PLAN.md
- **Consultant override:** the request link and the D-11 cascade use `service_request_id` / `$task->serviceRequest` instead of a `source` morph.
- None otherwise.

## Flagged assumptions
- FA-6.03-1..4 as planned (room-board close = done; literal `turnover` reason skips the closer; lazy resolution; trashed rooms locked with `withTrashed()`, hook skips them).

## Verification
- Acceptance greps: 14 tests in the status-writer unit test, 7 in RoomBoardClosesTurnoverTest; no `RoomStatusHistory`/`status_changed_at` in housekeeping actions or listeners; no constructor injection of `UpdateRequestStatusAction` in the status writer; six keys in each locale.
- Targeted run (06-03 specs + room/queue/checkout regressions + locale tests): 217 passed.
- Full suite: run once after 06-04, 1626 passed (see 06-04-SUMMARY).
