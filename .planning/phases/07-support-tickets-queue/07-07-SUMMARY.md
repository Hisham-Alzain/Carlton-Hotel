---
phase: 07-support-tickets-queue
plan: 07
subsystem: ticket service recovery
requires: [07-06]
provides: [RecordTicketRecoveryAction, ticket_recovery_folio_invalid 422, POST /support-tickets/{ticket}/recovery-actions]
key-files:
  created:
    - backend/app/Actions/Tickets/RecordTicketRecoveryAction.php
    - backend/app/Exceptions/TicketRecoveryFolioInvalidException.php
    - backend/app/Http/Requests/Tickets/RecordTicketRecoveryRequest.php
    - backend/tests/Unit/Tickets/RecordTicketRecoveryActionTest.php
    - backend/tests/Feature/Tickets/TicketRecoveryTest.php
  modified:
    - backend/app/Services/Tickets/TicketService.php
    - backend/app/Http/Controllers/Admin/SupportTicketController.php
    - backend/routes/api.php
completed: 2026-10-02
---

# 07-07 — Ticket service recovery (record-only)

## Shipped
- `RecordTicketRecoveryAction`: locks the ticket row only (leaf lock); closed → 422 `ticket_closed`. `folio_credit` checks in order `no_stay` → `not_credit` → `other_stay` (ticket reservation, or guest fallback for guest-only tickets) → `already_linked`, each 422 `ticket_recovery_folio_invalid {folio_item_uuid, reason}`. Amount = `abs` of the negative credit through `FolioLedger::normalize()` (string compare, no floats); a differing body amount → 422 `validation_failed` on `amount_usd`. Other types: optional normalised amount, no item. Writes one `recovery` action (body null, FA-7.07-3) + one `ticket_recoveries` row; `UniqueConstraintViolationException` on the insert → `already_linked` and the action row rolls back. No `TicketChanged`, no folio/payment write or lock.
- `RecordTicketRecoveryRequest`: type enum; description 3-1000; amount_usd `decimal:0,2` 0-99999.99; folio_item_uuid `required_if`/`prohibited_unless` folio_credit, uuid, `exists:folio_items,uuid` (unknown → 422, FA-7.07-2).
- `TicketService::recordRecovery()` resolves the item and returns the show shape (201); controller message `ticket_recovery_recorded`.
- Route `POST /support-tickets/{ticket}/recovery-actions` (tickets.respond). No DELETE route under `support-tickets` (both DELETEs → 405 `method_not_allowed`).
- Lang keys (`errors.ticket_recovery_folio_invalid`, `validation.ticket_recovery_amount_mismatch`, `messages.ticket_recovery_recorded`) × 5 were added in the 07-05 pass.

## Deviations from PLAN.md
- Exception class and lang keys were created during 07-05 (see 07-05 SUMMARY); RED still observed (32/32) because the action and route did not exist.
- `FolioItem::source_type` has no enum cast, so the credit check compares through `FolioItemSource::tryFrom()` (works whether or not a cast is added later).

## Flagged assumptions
- FA-7.07-1..3 as planned.

## Verification
- RED observed: 32/32 failing before implementation.
- `TicketRecoveryTest|RecordTicketRecoveryActionTest|TicketShowTest|LocaleFoundationTest`: 94 passed. Two-step flow posts a real Phase 5 credit with `Idempotency-Key` + `folios.post`, links it, asserts `"25.00"` and `folio_credit_total_usd` "25.00"; split totals 25.00 / 65.00; race backstop via a one-shot `TicketRecovery::creating` listener; `lockedSelects` shows a tickets lock and none on folios/folio_items/payments.
- Folio gate: `git diff --quiet $(07-BASE) -- app/Actions/Folio app/Models/Folio*.php app/Models/Payment.php app/Services/Folio app/Http/{Resources,Requests}/Folio app/Http/Controllers/{Admin,Api}/FolioController.php app/Support/FolioLedger.php` → unchanged. `Folio` filtered suites: 168 passed.
- `route:list --path=api/support-tickets`: 8 routes, no DELETE.
- Full suite: 1932 run, **1931 passed** — `ValidationMessageLocalizationTest` flagged `between` (no BaseRequest message mapping → raw key in ar/fr/tr/es). Fixed by `numeric|decimal:0,2|min:0|max:99999.99` (all mapped); `TicketRecoveryTest|ValidationMessageLocalizationTest|LocaleFoundationTest` 109 passed. The full suite is re-run green at the end of 07-08. `database.sqlite` sha1 unchanged.

## Deviation (fix during verification)
- `amount_usd` uses `min`/`max` instead of the plan's `between` so every reachable rule keeps a localized message.
