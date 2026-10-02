---
phase: 07-support-tickets-queue
plan: 08
subsystem: queue ticket arm + queue_type
requires: [07-02, 07-05]
provides: [queue ticket arms delegate to ticket single writers, council A2 LogicException, queue_type on every queue row, ticket room_number on queue rows]
key-files:
  created:
    - backend/tests/Feature/Operations/OperationsQueueTicketArmTest.php
  modified:
    - backend/app/Actions/Operations/UpdateRequestStatusAction.php
    - backend/app/Actions/Operations/AssignRequestAction.php
    - backend/app/Support/OperationsQueueType.php
    - backend/app/Http/Resources/Operations/OperationsQueueItemResource.php
    - backend/tests/Feature/Operations/OperationsQueueTest.php
completed: 2026-10-02
---

# 07-08 — Queue ticket arm and `queue_type`

## Shipped
- `UpdateRequestStatusAction` ticket arm → `UpdateTicketStatusAction` (`TicketStatus::from($status)`, reason, actor); a null actor throws `LogicException` (A2). The inline ticket mirror is gone and `mirror()` is narrowed to `ServiceRequest`. Null actors stay valid for the SR and HK arms.
- `AssignRequestAction` ticket arm → `AssignTicketAction`; a null actor throws `LogicException`. No bare ticket update or inline ticket mirror remains (the remaining mirror calls are the 07-02 SR arm).
- `OperationsQueueType`: explicit ticket `baseQuery()` with `['assignedUser', 'room']` (trashed rooms included through `Ticket::room()`); `roomNumber()` reads the loaded ticket room; `allowedStatuses()` docblock says tickets now advertise enforced targets (`allowedTargets()`, no `assigned`).
- `OperationsQueueItemResource`: `queue_type` = registry segment next to `type` (OPS-03, D-24; no `{id}` alias routes).

## Additive contract changes on existing routes (FA-7.08-1)
- `PATCH /operations/queue/tickets/{uuid}/status` now enforces the D-06 table: `ticket_transition_invalid` (incl. `assigned`) and `validation_failed` on a missing `reason` (close from non-resolved / reopen). It writes the ticket timeline and self-assigns on → in_progress.
- `PATCH /operations/queue/tickets/{uuid}/assign` now returns `assignee_not_eligible` (tickets.respond) and `ticket_closed`, and moves open → assigned.
- Queue reason length stays 255 on the queue route (FA-7.08-2); `/support-tickets/{ticket}/status` allows 1000. Both reach the same writer.

## Deviations from PLAN.md
- `test_ticket_queue_writes_mirror_exactly_once` creates its three users before the first request. When a user was created and granted a permission inline between two requests in one test, the second actor was refused (403). This is a test-ordering artefact around permission caching, not a product path; every HTTP request in production resolves a fresh actor.
- Specs that the earlier plans already satisfy passed on the RED run (active-status list, enforced `allowed_statuses`, SR null actor, summary keys `in_progress`/`waiting_guest`). 9 of 26 were RED (queue_type ×2, room_number, timeline, assigned 422, reason, assign delegation, mirror once, A2).

## Verification
- `OperationsQueueTicketArmTest` (12 methods) and `OperationsQueueTest` (still 14 methods, now with `queue_type` asserted against `type`), plus `OperationsQueueHousekeepingTest`, `QueueAssignEligibilityTest`, `DashboardSummaryTest`, `FirestoreMirrorResilienceTest` and `ServiceRequestBoardTest`: 81 passed.
- Full suite: **1944 passed** (12553 assertions). `database.sqlite` sha1 `babcdd27c9070101d4b7dc2bcfa42fdb8affd70d` unchanged from session start.
