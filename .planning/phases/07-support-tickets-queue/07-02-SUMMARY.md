---
phase: 07-support-tickets-queue
plan: 02
subsystem: assignee eligibility + hardened assign writers
requires: [07-01]
provides: [AssigneeEligibility::assert, assignee_not_eligible 422, service_request_closed 422, UserFactory::withPermissions, locked SR assign]
key-files:
  created:
    - backend/app/Support/AssigneeEligibility.php
    - backend/app/Exceptions/AssigneeNotEligibleException.php
    - backend/app/Exceptions/ServiceRequestClosedException.php
    - backend/tests/Unit/Support/AssigneeEligibilityTest.php
    - backend/tests/Feature/Operations/QueueAssignEligibilityTest.php
  modified:
    - backend/app/Actions/Operations/AssignRequestAction.php
    - backend/app/Actions/Housekeeping/AssignHousekeepingTaskAction.php
    - backend/database/factories/UserFactory.php
    - backend/lang/{en,ar,fr,tr,es}/custom.php
    - backend/tests/Feature/Operations/OperationsQueueTest.php
    - backend/tests/Feature/Operations/ServiceRequestBoardTest.php
    - backend/tests/Feature/Operations/OperationsQueueHousekeepingTest.php
    - backend/tests/Feature/Notification/FirestoreMirrorResilienceTest.php
    - backend/tests/Feature/Housekeeping/AssignTest.php
    - backend/tests/Unit/Housekeeping/AssignHousekeepingTaskActionTest.php
completed: 2026-09-28
---

# 07-02 — Assignee eligibility and hardened assign writers

## Shipped
- `AssigneeEligibility::assert(User, string $workPermission)`: active + type staff|super_admin + `can()` (super admin via Gate::before), else `AssigneeNotEligibleException` → 422 `assignee_not_eligible {user_uuid, required_permission}`.
- `ServiceRequestClosedException` → 422 `service_request_closed {status}` (A1, PR-1).
- `AssignRequestAction` SR arm: `DB::transaction(fn, 3)`, `lockForUpdate()` on the request, closed check (registry `openStatuses()` complement), eligibility with the registry `statusPermission`, mirror via `DB::afterCommit` (once; none on refusal). Ticket arm unchanged (delegates in 07-08). HK arm unchanged (delegates).
- `AssignHousekeepingTaskAction`: eligibility (`housekeeping.update`) after the existing closed check → applies to `PATCH /housekeeping/tasks/{task}/assign` and the queue HK assign.
- `UserFactory::withPermissions(...)` (findOrCreate on guard `users`).
- Lang: `errors.assignee_not_eligible`, `errors.service_request_closed` × 5 locales.

## Re-pinned tests (PR-7; assignee construction only, no assertion changed)
- `Housekeeping/AssignTest`: `test_pending_task_becomes_assigned`, `test_assigned_and_in_progress_swap_the_assignee` (housekeeping.update)
- `Notification/FirestoreMirrorResilienceTest::test_a_failed_firestore_mirror_does_not_fail_assigning_a_request` (service_requests.update)
- `Operations/OperationsQueueHousekeepingTest`: `test_assign_via_queue_needs_housekeeping_assign`, `test_task_changes_are_mirrored_once_per_write` (housekeeping.update)
- `Operations/OperationsQueueTest`: `test_assigning_a_service_request_updates_and_mirrors` (service_requests.update), `test_assigning_a_ticket_with_tickets_assign_permission_succeeds` (tickets.respond, pre-emptive for 07-08)
- `Operations/ServiceRequestBoardTest::test_board_rows_are_progressed_through_the_queue` (service_requests.update)
- `Unit/Housekeeping/AssignHousekeepingTaskActionTest`: pending/assigned/in-progress swap, room-lock order, event-once (6 constructions, housekeeping.update)

## Deviations from PLAN.md
- Added `test_missing_user_uuid_is_422` to complete the route matrix.

## Flagged assumptions
- FA-7.02-1/2 as planned. Additive tightenings on existing routes: SR queue assign (`assignee_not_eligible`, `service_request_closed`), HK assign both routes (`assignee_not_eligible`).

## Verification
- RED observed (2 failures + errors before implementation).
- Filtered eligibility + locale tests: 108 passed.
- Full suite: **1748 passed** (11362 assertions). `database.sqlite` sha1 unchanged.
