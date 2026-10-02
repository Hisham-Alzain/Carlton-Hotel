---
phase: 07-support-tickets-queue
plan: 03
subsystem: ticket create + detail
requires: [07-02]
provides: [POST /support-tickets, GET /support-tickets/{ticket}, CreateTicketAction, TicketChanged, MirrorTicketToFirestore, TicketService::store/show, TicketResource, TicketActionResource]
key-files:
  created:
    - backend/app/Actions/Tickets/CreateTicketAction.php
    - backend/app/Events/TicketChanged.php
    - backend/app/Listeners/MirrorTicketToFirestore.php
    - backend/app/Services/Tickets/TicketService.php
    - backend/app/Http/Resources/Tickets/TicketResource.php
    - backend/app/Http/Resources/Tickets/TicketActionResource.php
    - backend/app/Http/Requests/Tickets/CreateTicketRequest.php
    - backend/app/Http/Controllers/Admin/SupportTicketController.php
    - backend/tests/Feature/Tickets/TicketCreateTest.php
    - backend/tests/Feature/Tickets/TicketShowTest.php
    - backend/tests/Unit/Tickets/CreateTicketActionTest.php
  modified:
    - backend/app/Models/Ticket.php
    - backend/routes/api.php
    - backend/lang/{en,ar,fr,tr,es}/custom.php
completed: 2026-09-28
---

# 07-03 — Ticket create and detail

## Shipped
- `CreateTicketAction` (single creator): source STAFF, status OPEN, created_by actor, priority label → 1-3, department = body ?? `Department::forTicketCategory()`; guest derived from reservation, mismatch → `ValidationException` on `guest_uuid`; ticket + `created` action (null → open, null body) in `DB::transaction(fn, 3)`; `TicketChanged` dispatched (after commit). Code 201.
- `TicketChanged` (`ShouldDispatchAfterCommit`) + queued `MirrorTicketToFirestore` → `ops_queue/ticket_{uuid}`, unchanged payload shape.
- `TicketService::store()` (resolves uuids, delegates, returns the show shape) and `show()` in **exactly 6 queries**: ticket (+ `conversation_uuid` subselect + `withSum` on the HasManyThrough for both totals — worked, no addSelect fallback needed), guest, reservation, room (withTrashed), actions (`limit(201)`, left-joined recovery + folio item uuid, hydrated into a `recovery` relation), one batched users query (`preloadUsers()`, reused by 07-04).
- `TicketResource` / `TicketActionResource`: D-13 keys; totals via `FolioLedger::fromNumeric` ("0.00" default); `actions`, `actions_truncated`, `latest_escalation` only when actions are loaded; `message_id` never emitted; no queries.
- `CreateTicketRequest` (no source/status/created_by/conversation rules), `SupportTicketController::store/show` (`success()` directly, no `respondFromService`), routes `POST /support-tickets` (tickets.respond), `GET /support-tickets/{ticket}` (tickets.view).
- Lang: `messages.ticket_created`, `validation.ticket_guest_mismatch` × 5.

## Deviations from PLAN.md
- `Ticket::$actionsTruncated` is a declared public (non-persisted) property rather than an attribute, so it can never be written to the DB.
- Show message key is `custom.messages.success` (what `respondFromService` default gives HousekeepingTaskController::show).
- Added `test_trashed_room_is_rejected` (create) beyond the plan.
- RED for `CreateTicketActionTest` was not run separately (the action was written before the first run); feature specs were observed RED (29/31 failing) before routes existed.

## Flagged assumptions
- FA-7.03-1..4 as planned (show matrix is happy/401/403/404).

## Verification
- `TicketCreateTest|TicketShowTest|CreateTicketActionTest|LocaleFoundationTest|ValidationMessageLocalizationTest`: 126 passed.
- Query budget pinned at 6 (`expectsDatabaseQueryCount(6)`).
- Resources grep for queries: 0 / 0.
- Full suite: **1785 passed** (11777 assertions). `database.sqlite` sha1 unchanged.
