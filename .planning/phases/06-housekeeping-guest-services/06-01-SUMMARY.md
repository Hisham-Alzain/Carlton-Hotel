---
phase: 06-housekeeping-guest-services
plan: 01
subsystem: housekeeping task foundation
requires: []
provides: [housekeeping_tasks, housekeeping_task_status_history, HousekeepingTaskType, HousekeepingTaskStatus, HousekeepingTask, ensureOpen, HousekeepingTaskChanged, housekeeping-reconcile]
key-files:
  created:
    - .planning/phases/06-housekeeping-guest-services/06-BASE.txt
    - backend/database/migrations/2026_09_27_100000_create_housekeeping_tasks_table.php
    - backend/database/migrations/2026_09_27_100100_create_housekeeping_task_status_history_table.php
    - backend/app/Enums/HousekeepingTaskType.php
    - backend/app/Enums/HousekeepingTaskStatus.php
    - backend/app/Models/HousekeepingTask.php
    - backend/app/Models/HousekeepingTaskStatusHistory.php
    - backend/database/factories/HousekeepingTaskFactory.php
    - backend/app/Events/HousekeepingTaskChanged.php
    - backend/app/Actions/Housekeeping/CreateHousekeepingTaskAction.php
    - backend/app/Actions/Housekeeping/ReconcileHousekeepingTasksAction.php
    - backend/app/Console/Commands/ReconcileHousekeepingTasks.php
    - backend/tests/Unit/Housekeeping/HousekeepingTaskStatusTest.php
    - backend/tests/Unit/Housekeeping/CreateHousekeepingTaskActionTest.php
    - backend/tests/Feature/Housekeeping/ReconcileHousekeepingTasksTest.php
  modified:
    - backend/app/Models/ServiceRequest.php
completed: 2026-09-27
---

# 06-01 — Housekeeping task foundation

## Shipped
- `06-BASE.txt` = `9791549e0e234aaf244e221926a5f6924b8260ae` (phase base for the folio no-touch diff).
- Migration `housekeeping_tasks` (D-01 + consultant override): uuid, room_id (cascade), reservation_id (nullOnDelete), type, status (default pending), priority (default normal), assigned_user_id, **`service_request_id` nullable UNIQUE FK nullOnDelete**, `dedupe_key` string(40) nullable UNIQUE, due_at/started_at/completed_at, completed_by, created_by, notes; indexes (room_id,status), (status,due_at), assigned_user_id, reservation_id, completed_by, created_by, type.
- Migration `housekeeping_task_status_history` (D-03), twin of room_status_history.
- Enums `HousekeepingTaskType` (`deduped()`, `isDeduped()`), `HousekeepingTaskStatus` (`allowedTargets()`, `canTransitionTo()`, `open()`, `isOpen()`).
- `HousekeepingTask` (HasUuid, LogsActivity): `saving` hook is the only writer of `dedupe_key` via `derivedDedupeKey()`; `scopeOpen()`; relations room (withTrashed), reservation, assignedUser, completedBy, createdBy, `serviceRequest()` BelongsTo, history. `ServiceRequest::housekeepingTask(): HasOne`.
- `CreateHousekeepingTaskAction::ensureOpen(Room, type, attrs, ?User)`: `DB::transaction(fn, 3)`, room row locked first, locked find-open (request: by `service_request_id`, any status) → 200; else insert + `null → pending` history + `HousekeepingTaskChanged` → 201; one caught `UniqueConstraintViolationException` answered by a locked re-read, rethrown when nothing is found (stale key).
- `HousekeepingTaskChanged` (ShouldDispatchAfterCommit).
- `housekeeping:reconcile` (not scheduled) + `ReconcileHousekeepingTasksAction`: re-saves rows whose stored key differs from the derived one; lists dirty rooms without an open turnover.

## Deviations from PLAN.md
- **Consultant override applied:** no `source_type`/`source_id` morph, no `source()` MorphTo, no `service_request` morph alias; `AppServiceProvider` is unchanged. `ensureOpen` attrs key is `service_request` (not `source`). Factory state `request(?ServiceRequest)` uses `for(..., 'serviceRequest')`. The test `test_request_tasks_dedupe_on_their_service_request` asserts `service_request_id` and `(new ServiceRequest)->getMorphClass() === ServiceRequest::class` (the regression the override asks for). FA-6.01-1 (alias side effect) is void.
- History index given an explicit short name (`hk_task_history_task_created_index`) to stay under MySQL's 64-char identifier limit.
- Reconcile tests assert the exact output lines rather than a bare `'1'`/`'0'` substring (stronger).

## Flagged assumptions
- FA-6.01-2 confirmed: the creation history row carries the caller's reason (`check_out`, `service_request`, `manual`).
- FA-6.01-3 confirmed: one task per service request ever (unique FK); a closed request task is returned, not replaced.

## Verification
- RED confirmed (18 errors) before implementation.
- Scratch sqlite `migrate:fresh --seed` / `migrate:rollback --step=2` / `migrate`: ok; `backend/database/database.sqlite` sha1 unchanged.
- Dedupe-column grep gate: no writer outside `app/Models/HousekeepingTask.php`.
- `php artisan test`: 1540 passed (baseline 1522 + 18).
