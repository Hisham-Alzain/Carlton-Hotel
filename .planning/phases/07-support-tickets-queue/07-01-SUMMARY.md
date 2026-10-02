---
phase: 07-support-tickets-queue
plan: 01
subsystem: support-ticket domain foundation
requires: []
provides: [tickets support columns, ticket_actions, ticket_recoveries, TicketStatus::allowedTransitions, TicketActionType, TicketRecoveryType, Department::forTicketCategory, ServiceRequestPriority::toTicketScale, hotel.ticket_max_escalation_level]
key-files:
  created:
    - .planning/phases/07-support-tickets-queue/07-BASE.txt
    - backend/database/migrations/2026_09_28_100000_add_support_columns_to_tickets_table.php
    - backend/database/migrations/2026_09_28_100100_create_ticket_actions_table.php
    - backend/database/migrations/2026_09_28_100200_create_ticket_recoveries_table.php
    - backend/app/Enums/TicketActionType.php
    - backend/app/Enums/TicketRecoveryType.php
    - backend/app/Models/TicketAction.php
    - backend/app/Models/TicketRecovery.php
    - backend/database/factories/TicketActionFactory.php
    - backend/database/factories/TicketRecoveryFactory.php
    - backend/tests/Unit/Tickets/TicketStatusTransitionTest.php
    - backend/tests/Unit/Tickets/TicketEnumsTest.php
    - backend/tests/Unit/Tickets/TicketSchemaTest.php
  modified:
    - backend/app/Enums/TicketStatus.php
    - backend/app/Enums/TicketSource.php
    - backend/app/Enums/ServiceRequestPriority.php
    - backend/app/Enums/Department.php
    - backend/app/Models/Ticket.php
    - backend/database/factories/TicketFactory.php
    - backend/config/hotel.php
    - backend/app/Actions/Operations/RouteRequestAction.php
    - backend/tests/Feature/Operations/OperationsQueueHousekeepingTest.php
completed: 2026-09-28
---

# 07-01 — Support-ticket domain foundation

## Shipped
- Baseline gate (A10): full suite green on the untouched tree, **1709 passed**. `07-BASE.txt` = `563a65cd21904d9e511df780cc7f81124ea70ad3` (HEAD; docs commit on top of a17c293).
- Migrations: `tickets` += description, reservation_id/room_id/created_by (nullOnDelete, indexed), resolved_at, closed_at, escalation_level (default 0); indexes source, (status, priority), created_at. `ticket_actions` (D-01; user_id/target_user_id **restrictOnDelete**, message_id reserved nullOnDelete, meta json, created_at only). `ticket_recoveries` (D-02; unique ticket_action_id cascade, DECIMAL(10,2) amount_usd, unique nullable folio_item_id nullOnDelete).
- `TicketStatus`: 6 cases, `allowedTransitions()` (D-06 table), `allowedTargets()` = transitions minus ASSIGNED, `canTransitionTo()`, `active()` = 4 working statuses.
- `TicketSource::STAFF`; `TicketActionType` with `allowedMetaKeys()` (A8); `TicketRecoveryType` (7 values); `ServiceRequestPriority::toTicketScale()`; `Department::forTicketCategory()` (used by `RouteRequestAction`).
- `TicketAction`: HasUuid, no LogsActivity, `UPDATED_AT = null`; saving hook rejects non-whitelisted meta keys (InvalidArgumentException); updating/deleting throw LogicException. `message_id` not fillable.
- `TicketRecovery`: snapshot docblock (A7/A8). `Ticket`: new relations (reservation, room withTrashed, createdBy, actions, recoveries HasManyThrough), `logExcept(['description'])` (A6).
- Factories: Ticket states staff/withReservation/assignedTo/inProgress/waitingGuest/resolved/closed/escalated; TicketActionFactory (statusChange/reply/escalation/recovery); TicketRecoveryFactory (folioCredit = abs of the credit).
- Config `hotel.ticket_max_escalation_level` (default 3).

## Deviations from PLAN.md
- Scratch migration DB lives in the session scratchpad (orchestrator rule), not `backend/storage/framework/`.
- Added `TicketStatus::canTransitionTo()` (mirrors HousekeepingTaskStatus; used by 07-02 writer).
- Added an index on `ticket_actions.message_id` and `ticket_recoveries.type` (FK / filter columns — convention: index every FK).
- `ticket_actions` tests assert `(ticket_id, created_at)` behaviour only indirectly (no index introspection).

## Flagged assumptions
- FA-7.01-1..3 as planned.

## Re-pins
- `OperationsQueueHousekeepingTest:167` open ticket `allowed_statuses` → `['in_progress','resolved','closed']`. No other assertion needed a re-pin.

## Verification
- RED observed: 20 specs failing before implementation.
- Scratch cycle `migrate:fresh --seed` → `rollback --step=3` → `migrate` OK.
- Filtered (`TicketStatusTransitionTest|TicketEnumsTest|TicketSchemaTest|RouteRequestActionTest`): 24 passed.
- Full suite: **1729 passed** (11302 assertions). `database.sqlite` sha1 `babcdd27…d70d` unchanged.
