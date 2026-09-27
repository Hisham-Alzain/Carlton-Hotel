---
phase: 05-folio-extensions
plan: 05
subsystem: folio payments
requires: [05-04]
provides: [RecordFolioPaymentAction, payments-idempotency-key, auto-settle, decimal-string-cash-payment]
key-files:
  created:
    - backend/database/migrations/2026_09_26_130100_add_idempotency_key_to_payments_table.php
    - backend/app/Http/Requests/Folio/RecordFolioPaymentRequest.php
    - backend/app/Actions/Folio/RecordFolioPaymentAction.php
    - backend/app/Exceptions/FolioOverpaymentException.php
  modified:
    - backend/app/Models/Payment.php
    - backend/app/Actions/Payment/RecordCashPaymentAction.php
    - backend/app/Services/Payment/PaymentService.php
    - backend/app/Services/Folio/FolioService.php
    - backend/app/Http/Controllers/Admin/FolioController.php
    - backend/routes/api.php
completed: 2026-09-26
---

# 05-05 — Manual payments with idempotency and auto-settle (FOLIO-04)

## Shipped
- Migration `2026_09_26_130100_add_idempotency_key_to_payments_table` (string(64) nullable after status; unique (payable_type, payable_id, idempotency_key)); `Payment` fillable + `idempotency_key`.
- `POST /api/cms/folios/{folio}/payments` (auth:users, permission:folios.settle) -> `recordPayment` -> `FolioService::adminRecordPayment(Folio, array, User)` -> `RecordFolioPaymentAction::handle(Folio $folio, User $recorder, array $data, string $key): array`. 201 "Folio payment recorded.", 200 replay.
- Order under the folio lock: replay (method, amount bccomp, note, recorded_by) -> settled guard -> `FolioOverpaymentException` when `bccomp(amount, balanceDueUsd()) === 1` -> `RecordCashPaymentAction` (payable = folio, key in the same insert) -> auto-settle when `bccomp(balanceDueUsd(), '0') <= 0` with `activity()->...->log('folio.auto_settled')` {folio_uuid, paid_usd}.
- `RecordFolioPaymentRequest`: method enum, amount_usd decimal:0,2|min:0.01|max:99999.99, note max:1000, idempotency_key required|string|max:64 with message `custom.errors.idempotency_key_required`; header merged in prepareForValidation (plan-q3 shape).
- `RecordCashPaymentAction::handle(Model $payable, string $method, string $amount, User $recorder, ?string $note = null, ?string $idempotencyKey = null)`; exactly one `(float)` at `PaymentGatewayInterface::charge()`.
- `PaymentService::settleReservation` passes `FolioLedger::normalize($data['amount_usd'])` (no float).

## Deviations from PLAN.md
- (b) PaymentService uses `FolioLedger::normalize()` instead of a bare `(string)` cast (exact 2dp string either way). FolioPaymentTest is QA's.

## Flagged assumptions
- FA-5.05-1 stays flagged (FOLIO-04 unclassified probe). FA-5.05-2 confirmed (approved deviation: 422 validation_failed on errors.idempotency_key). FA-5.05-3 confirmed (sixth named param). FA-5.05-4 confirmed (gateway keeps float; PaymentGatewayInterface/ManualDriver untouched).

## Carry-forwards
- Header-merge shape identical in PostFolioItemRequest and RecordFolioPaymentRequest: `trim((string) $this->header('Idempotency-Key', ''))`, blank -> null, `merge(['idempotency_key' => ...])`.
- Activity description string: `folio.auto_settled` (in RecordFolioPaymentAction.php only).

## Verification
- Scratch migrate cycle ok (dev DB untouched). PaymentTest, FolioTest, CheckOutTest, CheckOutReservationActionTest green; engineer smoke: missing key 422 text, overpayment context, 0.01 boundary, replay 200, amount/recorder conflict 409, auto-settle + log, replay after settle 200, lock recorder.
