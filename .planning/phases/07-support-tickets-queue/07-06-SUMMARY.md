---
phase: 07-support-tickets-queue
plan: 06
subsystem: ticket reply + escalation
requires: [07-05]
provides: [ReplyToTicketAction, EscalateTicketAction, ticket_escalation_invalid 422, ticket_escalation_limit 422, POST /support-tickets/{ticket}/reply, POST /support-tickets/{ticket}/escalate]
key-files:
  created:
    - backend/app/Actions/Tickets/ReplyToTicketAction.php
    - backend/app/Actions/Tickets/EscalateTicketAction.php
    - backend/app/Exceptions/TicketEscalationInvalidException.php
    - backend/app/Exceptions/TicketEscalationLimitException.php
    - backend/app/Http/Requests/Tickets/ReplyToTicketRequest.php
    - backend/app/Http/Requests/Tickets/EscalateTicketRequest.php
    - backend/tests/Unit/Tickets/EscalateTicketActionTest.php
    - backend/tests/Feature/Tickets/TicketReplyTest.php
    - backend/tests/Feature/Tickets/TicketEscalateTest.php
  modified:
    - backend/app/Services/Tickets/TicketService.php
    - backend/app/Http/Controllers/Admin/SupportTicketController.php
    - backend/routes/api.php
completed: 2026-10-02
---

# 07-06 — Ticket reply and escalation

## Shipped
- `ReplyToTicketAction`: ticket lock; closed → 422 `ticket_closed {status}`; one `reply` row (actor, body); status unchanged; **no** `TicketChanged`, no chat message, `message_id` stays null (D-16/D-17/D-21). Code 201.
- `EscalateTicketAction`: ticket lock; guards in order closed → `ticket_closed`, self → `ticket_escalation_invalid {reason: self}`, current assignee → `{reason: same_assignee}`, `escalation_level >= hotel.ticket_max_escalation_level` → `ticket_escalation_limit {level, max}`, eligibility (tickets.respond) → `assignee_not_eligible`. Effects: assignee := target, level + 1, open → assigned, one `escalation` row (body = reason, meta `{level, previous_assignee_uuid}`), `TicketChanged` once. No notification, nothing scheduled.
- Requests: reply `body` 1-5000; escalate `user_uuid` uuid+exists, `reason` 3-1000 (body `level` dropped by `validated()`).
- `TicketService::reply()` (201) / `escalate()`; controller messages `ticket_replied` (201) / `ticket_escalated` (200).
- Routes in the `permission:tickets.respond` group: `POST /support-tickets/{ticket}/reply`, `POST /support-tickets/{ticket}/escalate`.
- Lang keys (`errors.ticket_escalation_invalid|limit`, `messages.ticket_replied|escalated`) × 5 were added in the 07-05 pass.

## Deviations from PLAN.md
- Exceptions, requests and lang keys were created during 07-05 (see 07-05 SUMMARY); RED still observed because actions and routes did not exist.
- Previous assignee uuid is read with one `User::whereKey()->value('uuid')` query under the lock (only when the ticket had an assignee).

## Flagged assumptions
- FA-7.06-1 (reply 201, escalate 200) and FA-7.06-2 (guard order) as planned.

## Verification
- RED observed: 27/27 failing before implementation.
- `TicketReplyTest|TicketEscalateTest|EscalateTicketActionTest|LocaleFoundationTest|TicketShowTest`: 89 passed. Tests cover A→B→A within the cap, configured cap override, `Notification::assertNothingSent()`, no Firebase pushes, unchanged `messages` count, one mirror per escalation.
- Full suite: **1900 passed** (12366 assertions). `database.sqlite` sha1 unchanged.
