---
phase: 07-support-tickets-queue
plan: 04
subsystem: ticket list
requires: [07-03]
provides: [GET /support-tickets, TicketFilter, TicketService::index]
key-files:
  created:
    - backend/app/Filters/TicketFilter.php
    - backend/tests/Feature/Tickets/TicketIndexTest.php
  modified:
    - backend/app/Services/Tickets/TicketService.php
    - backend/app/Http/Controllers/Admin/SupportTicketController.php
    - backend/routes/api.php
completed: 2026-09-28
---

# 07-04 — Ticket list

## Shipped
- `TicketFilter`: `status|department|source|category|priority` (eq, in; unknown values match nothing; priority labels mapped to 1-3, unknown label → -1), `created_at` (gte, lte; parsed to UTC, unparseable → 422); custom `assignee` (uuid | `unassigned`), `guest`, `reservation` (uuid via subquery), `escalated` (bool); malformed → 422 `validation_failed`. Sort `created_at|updated_at|priority|status`, tie-break `id desc`; default `created_at desc, id desc`. Reads no auth.
- `TicketService::index()`: shares `detailQuery()` with show (conversation_uuid + both totals as subselects, guest/reservation/room eager), paginates, one batched users preload. **Exactly 6 queries** per page.
- `SupportTicketController::index`: `assignee=me` → caller uuid before the service; `paginatedSuccess` with `TicketResource` (no actions keys on rows).
- Route `GET /support-tickets` (auth:users + tickets.view).

## Deviations from PLAN.md
- The default order lives in `TicketFilter::applySort()` (it always runs because the service always builds a filter), not in the service query.

## Flagged assumptions
- FA-7.04-1 as planned.

## Verification
- RED observed (20/20 failing before implementation).
- `TicketIndexTest|TicketShowTest|TicketCreateTest`: 51 passed; list budget pinned at 6.
- `grep` for `request()|auth()|->user(` in TicketFilter/TicketService: 0.
- `route:list --path=api/support-tickets`: 3 routes (GET list, POST, GET show) with the expected middleware.
- Full suite: **1805 passed** (11874 assertions). `database.sqlite` sha1 `babcdd27c9070101d4b7dc2bcfa42fdb8affd70d` unchanged from session start.
