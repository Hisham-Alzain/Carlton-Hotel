---
phase: 06-housekeeping-guest-services
plan: 05
subsystem: operations queue, third item type
requires: [06-04]
provides: [OperationsQueueType, housekeeping-tasks queue arm, room_number, allowed_statuses, MirrorHousekeepingTaskToFirestore, ServiceRequest::scopeWithRoomNumber, ServiceRequestStatus::allowedTargets, TicketStatus::allowedTargets]
key-files:
  created:
    - backend/app/Support/OperationsQueueType.php
    - backend/app/Listeners/MirrorHousekeepingTaskToFirestore.php
    - backend/tests/Feature/Operations/OperationsQueueHousekeepingTest.php
  modified:
    - backend/app/Enums/ServiceRequestStatus.php
    - backend/app/Enums/TicketStatus.php
    - backend/app/Models/ServiceRequest.php
    - backend/app/Services/Operations/OperationsQueueService.php
    - backend/app/Http/Requests/Operations/UpdateRequestStatusRequest.php
    - backend/app/Http/Controllers/Admin/OperationsQueueController.php
    - backend/app/Http/Resources/Operations/OperationsQueueItemResource.php
    - backend/app/Actions/Operations/AssignRequestAction.php
    - backend/app/Actions/Operations/UpdateRequestStatusAction.php
    - backend/app/Support/OperationsQueueMirror.php
    - backend/app/Events/HousekeepingTaskChanged.php
    - backend/routes/api.php
    - backend/tests/Feature/Operations/OperationsQueueTest.php
    - backend/tests/Unit/Housekeeping/UpdateHousekeepingTaskStatusActionTest.php
completed: 2026-09-27
---

# 06-05 — Housekeeping tasks in the operations queue

## Shipped
- `App\Support\OperationsQueueType` registry (D-12): `service-requests`, `tickets`, `housekeeping-tasks` with item type, model, view/assign/status permissions, status enum, mirror prefix, summary key; `all()` (order = tie-break order), `tryFromSegment()`, `fromSegment()` (unknown → 404 `not_found`), `forModel()`, `openStatuses()`, `baseQuery()`, `queueQuery()`, `allowedStatuses()`, `roomNumber()` (never lazy-loads).
- `OperationsQueueService` reads the registry everywhere (no `match` left): index concatenates each viewable type (`latest()->orderByDesc('id')->limit(500)`), stable sort by created_at desc; summary adds `housekeeping_tasks` for `housekeeping.view` (event_inquiries still rides with tickets); assign/status check the registry permission, delegate, then re-read through `baseQuery()` so responses carry `room_number` without lazy loads; `updateStatus(..., ?string $reason)`.
- `UpdateRequestStatusRequest`: the segment's own enum (unknown segment keeps request enum → service 404); `reason` nullable ≤ 255. Controller passes `reason`.
- `OperationsQueueItemResource`: third type (`housekeeping_task`, subject = task type, department `housekeeping`), plus `room_number` and `allowed_statuses` on every row.
- `ServiceRequestStatus::allowedTargets()` / `TicketStatus::allowedTargets()` (advisory: every other value). `ServiceRequest::scopeWithRoomNumber()` (correlated subselect: first reservation line with a room, `withTrashed` rooms).
- `AssignRequestAction::handle(ServiceRequest|Ticket|HousekeepingTask, User, ?User $actor = null)` and `UpdateRequestStatusAction::handle(ServiceRequest|Ticket|HousekeepingTask, ...)`: the task arm delegates to `AssignHousekeepingTaskAction` / `UpdateHousekeepingTaskStatusAction` and never mirrors itself; request/ticket lines unchanged.
- `OperationsQueueMirror` widened: ids via the registry prefix (unchanged for requests/tickets); task payload = uuid, department, status, priority, guest_uuid (reservation guest or null), assigned_user_uuid, created_at, task_type, room_uuid, room_number. `MirrorHousekeepingTaskToFirestore` (ShouldQueue) on `HousekeepingTaskChanged` is the only task mirror writer.
- Route gate `GET /operations/queue` → `service_requests.view|tickets.view|housekeeping.view`.

## Deviations from PLAN.md
- Consultant override: nothing morph-related was needed; the request-task adjacency test links through `service_request_id`.
- `UpdateHousekeepingTaskStatusActionTest` (06-03): two assertions `assertSame([], $fake->mirrors)` narrowed to "no `service_request_{uuid}` mirror". Their intent (the request is left alone) is unchanged; the task's own mirror now exists by design (D-11b).
- Added `test_assign_via_queue_validates_the_body` (422 on the queue assign verb) beyond the plan's list.

## Flagged assumptions
- FA-6.05-1..4 as planned (advisory allowed_statuses for requests/tickets; optional assign actor; 3 × 500 per-type cap; dedicated + queue task verbs are a deliberate no-alias exception).

## Verification
- RED observed (13 failing of 31) before implementation.
- `OperationsQueueHousekeepingTest` (17) + `OperationsQueueTest` (14): 31 passed, 137 assertions.
- Full suite: see 06-09 / phase SUMMARY for the final green run.
