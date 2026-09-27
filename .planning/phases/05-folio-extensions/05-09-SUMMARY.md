---
phase: 05-folio-extensions
plan: 09
subsystem: open-dispute flags (never a gate)
requires: [05-08]
provides: [Folio-dispute-hooks, open_disputes_count, has_open_disputes-filter, checkout-summary-count]
key-files:
  modified:
    - backend/app/Models/Folio.php
    - backend/app/Services/Folio/FolioService.php
    - backend/app/Http/Resources/Folio/FolioResource.php
    - backend/app/Filters/ReservationFilter.php
    - backend/app/Services/Booking/ReservationService.php
    - backend/app/Http/Resources/Booking/ReservationResource.php
    - backend/tests/Feature/Reservations/CheckOutTest.php
    - backend/tests/Feature/Reservations/ExpressCheckoutTest.php
completed: 2026-09-26
---

# 05-09 — Open-dispute flags, not a gate (FOLIO-03)

## Shipped
- `Folio::disputes()` / `openDisputes()` (HasManyThrough via FolioItem, `folio_item_disputes.status = open`), `scopeUnsettled()`, `scopeWithOpenDisputes()`, `openDisputesCount(): int` (Phase 9 read hooks, D-16).
- `FolioService::adminShow()` selects with `withCount('openDisputes')` (the count rides in the first query; the read stays at 6); `display()` calls `loadCount('openDisputes')` only when the attribute is absent.
- `FolioResource.open_disputes_count` via `whenCounted('openDisputes')`.
- `ReservationService::checkOut()` calls `$result['data']->folio?->loadCount('openDisputes')` after the unchanged action; `ReservationResource.folio` adds `open_disputes_count` only when that attribute exists (no query in the resource).
- `ReservationFilter`: `applyFolioStatus()` (behaviour unchanged) + `applyOpenDisputes()`: `1` whereHas('folio.openDisputes'), `0` whereDoesntHave (folio-less stays included), blank means no filter, anything else 422 `custom.validation.in` on `has_open_disputes`.
- `CheckOutReservationAction.php` byte-identical to the phase base (git diff gate passes).

## Deviations from PLAN.md
- Two existing pins widened for the additive shape: `CheckOutTest::test_check_out_with_a_settled_folio` summary keys now include `open_disputes_count` (asserted 0); `ExpressCheckoutTest::FOLIO_KEYS` includes `open_disputes_count`. The FolioDisputeTest flag cases are QA's.

## Flagged assumptions
- FA-5.09-1 confirmed (strings "1"/"0" only). FA-5.09-2 confirmed.

## Verification
- Engineer smoke: count 0 -> 1 on guest and staff reads; filter =1 / =0 / blank / yes (422); staff read at most 6 queries with a manual row present; `Folio::withOpenDisputes()` and `openDisputesCount()`.
- Full suite at this point: 1391 passed (including a temporary engineer smoke class, since removed).
