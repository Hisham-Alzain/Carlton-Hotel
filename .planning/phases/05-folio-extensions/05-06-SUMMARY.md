---
phase: 05-folio-extensions
plan: 06
subsystem: settle close path + legacy guard
requires: [05-05]
provides: [payment-free-close, folio_settled-everywhere, reservation-settle-guard, required_with-mapping]
key-files:
  modified:
    - backend/app/Actions/Folio/SettleFolioAction.php
    - backend/app/Http/Requests/Folio/SettleFolioRequest.php
    - backend/app/Services/Folio/FolioService.php
    - backend/app/Http/Controllers/Admin/FolioController.php
    - backend/app/Services/Payment/PaymentService.php
    - backend/app/Base/BaseRequest.php
    - backend/tests/Feature/Folio/FolioTest.php
completed: 2026-09-26
---

# 05-06 — Payment-free settle close path (FOLIO-04)

## Shipped
- `SettleFolioAction::handle(Folio, ?string $method, ?string $amount, User $recorder, ?string $notes = null)`: folio lock; settled -> `FolioSettledException` (context folio_uuid, settled_at); `balanceDueUsd() <= 0` -> settle with no Payment row (sent amount ignored) + `folio.settled_no_payment` {folio_uuid, balance_due_usd, paid_usd}; money due without amount -> `ValidationException` on `amount_usd`; else record via RecordCashPaymentAction and settle. Returns `payment_recorded`.
- `SettleFolioRequest`: method `required_with:amount_usd|nullable|enum`; amount_usd `nullable|decimal:0,2|min:0.01`.
- Controller picks `custom.messages.folio_settled` or `custom.messages.folio_settled_no_payment` and answers through `success()`.
- `PaymentService::settleReservation`: transaction, reservation lock then folio lock, 422 `folio_settled` once the folio is settled; otherwise unchanged.
- `BaseRequest::messages()['required_with']` (Laravel text: `The method field is required when amount usd is present.`).
- **Deliberate contract change:** `FolioTest::test_settling_already_settled_folio_returns_422` now asserts `folio_settled` + `context.folio_uuid` (was `reservation_state`), per D-14.

## Deviations from PLAN.md
- The no-amount check also refuses a null method while money is due (only reachable when amount is null, since the request enforces required_with). FolioPaymentTest close-path cases and PaymentTest guard cases are QA's.

## Flagged assumptions
- FA-5.06-1..4 confirmed as written.

## Carry-forwards
- Activity description `folio.settled_no_payment` lives only in SettleFolioAction.php (plan-q2 ruling: close path inline, no MarkFolioSettledAction).
- Lock order proven by engineer smoke: first locked select in legacy settle is `from "reservations"`, then `folios`.

## Verification
- FolioTest, PaymentTest, CheckOutTest, CheckOutReservationActionTest, ExpressCheckoutTest, StayTest, ValidationMessageLocalizationTest: 130 passed. Smoke: prepaid deposit -> generate balance 0.00 -> settle {} 200 no-payment message -> check-out 200; auto-settled folio -> check-out 200; legacy settle after folio settled 422.
