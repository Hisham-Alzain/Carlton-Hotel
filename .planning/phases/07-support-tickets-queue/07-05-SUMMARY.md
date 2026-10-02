---
phase: 07-support-tickets-queue
plan: 05
subsystem: ticket status + assign
requires: [07-04]
provides: [UpdateTicketStatusAction, AssignTicketAction, ticket_transition_invalid 422, ticket_closed 422, PATCH /support-tickets/{ticket}/status, PATCH /support-tickets/{ticket}/assign]
key-files:
  created:
    - backend/app/Actions/Tickets/UpdateTicketStatusAction.php
    - backend/app/Actions/Tickets/AssignTicketAction.php
    - backend/app/Exceptions/TicketTransitionException.php
    - backend/app/Exceptions/TicketClosedException.php
    - backend/app/Http/Requests/Tickets/UpdateTicketStatusRequest.php
    - backend/app/Http/Requests/Tickets/AssignTicketRequest.php
    - backend/tests/Unit/Tickets/UpdateTicketStatusActionTest.php
    - backend/tests/Unit/Tickets/AssignTicketActionTest.php
    - backend/tests/Feature/Tickets/TicketStatusTest.php
    - backend/tests/Feature/Tickets/TicketAssignTest.php
  modified:
    - backend/app/Services/Tickets/TicketService.php
    - backend/app/Http/Controllers/Admin/SupportTicketController.php
    - backend/routes/api.php
    - backend/lang/{en,ar,fr,tr,es}/custom.php
completed: 2026-09-28
---

# 07-05 — Ticket status and assign

## Shipped
- `UpdateTicketStatusAction` (single status writer): `DB::transaction(fn, 3)`, `Ticket::whereKey()->lockForUpdate()`, status re-read under the lock; `assigned` or any pair outside `allowedTransitions()` → 422 `ticket_transition_invalid {from, to, allowed = allowedTargets()}`; blank reason on → closed (from non-resolved) or resolved → in_progress → 422 `validation_failed` on `reason`; self-assign on → in_progress when unassigned (recorded as `target_user_id` on the single status_change row, FA-7.05-2); `resolved_at` stamp / clear on reopen; `closed_at` stamp; reason trimmed into `body`; `TicketChanged` once.
- `AssignTicketAction` (single assignee writer): same lock; non-active → 422 `ticket_closed {status}`; `AssigneeEligibility::assert(..., OperationsQueueType::forModel()->statusPermission)` (tickets.respond) **before** the no-op; current assignee → 200 no-op (no row, no event); open → assigned (row open/assigned) or swap (row with null from/to, FA-7.05-1); `TicketChanged` once.
- `UpdateTicketStatusRequest` (status enum incl. `assigned` on purpose, reason ≤ 1000), `AssignTicketRequest` (user_uuid uuid + exists).
- `TicketService::updateStatus()/assign()` delegate then return `show()`; controller `updateStatus`/`assign` with messages `ticket_status_updated` / `ticket_assigned`.
- Routes: `PATCH /support-tickets/{ticket}/status` (tickets.respond), `PATCH /support-tickets/{ticket}/assign` (new `permission:tickets.assign` sub-group).
- Lang (× 5 locales): `errors.ticket_transition_invalid`, `errors.ticket_closed`, `validation.ticket_reason_required`, `messages.ticket_status_updated`, `messages.ticket_assigned`.

## Deviations from PLAN.md
- Lang keys for 07-06/07-07 (`ticket_replied`, `ticket_escalated`, `ticket_recovery_recorded`, `ticket_escalation_invalid`, `ticket_escalation_limit`, `ticket_recovery_folio_invalid`, `ticket_recovery_amount_mismatch`) and their five exception classes were added in the same pass (one scripted insert per locale); `ReplyToTicketRequest`/`EscalateTicketRequest` were also written here. No behaviour depends on them until 07-06/07-07.
- Feature helper named `patchStatus()` (TestCase already has a public `patch()`).
- `UserFactory::superAdmin()` target used for the super-admin case (no permission rows needed, Gate::before).

## Flagged assumptions
- FA-7.05-1 / FA-7.05-2 as planned.

## Verification
- RED observed: 68/68 specs failing before implementation (missing classes / 404 routes).
- `UpdateTicketStatusActionTest` (36-pair data provider + reasons, stamps, self-assign, stale model, `assertLocksRow('tickets')`), `AssignTicketActionTest`, `TicketStatusTest`, `TicketAssignTest` (10 methods), `LocaleFoundationTest`, `TicketShowTest`, `TicketCreateTest`: 151 passed.
- Full suite: **1873 passed** (12258 assertions). `database.sqlite` sha1 `babcdd27c9070101d4b7dc2bcfa42fdb8affd70d` unchanged.
