---
phase: 08-events-dining
plan: 05
status: complete
---

# 08-05 Summary — Ledger-backed event deposit

## Built
- `App\Actions\Events\RecordEventDepositAction` (copy of the `RecordFolioPaymentAction` shape): `DB::transaction(…, 3)` → inquiry lock → `FolioLedger::normalize` → `IdempotentWrite::run(find by getMorphClass()+id+key, match method/amount/note/recorded_by, write)`; inside `write` only: status guard (`quoted|confirmed`, else `inquiry_state` `{status, allowed}`), already-paid guard (`event_deposit_already_recorded` `{payment_uuid, paid_at}`), `RecordCashPaymentAction::handle(..., idempotencyKey)`, then `deposit_status = paid`, `deposit_paid_at = now()`. Always 200 (first write and replay). No auto-confirm, no folio touched.
- `EventDepositAlreadyRecordedException` (422).
- Trait `App\Http\Requests\Concerns\ReadsIdempotencyKey` (header merge + message). **Used by both** `RecordFolioPaymentRequest` (behaviour byte-equivalent; `FolioPaymentTest` green unchanged) and the new `RecordEventDepositRequest` (`amount_usd` decimal:0,2 min 0.01 max 99999.99, `method` sometimes|in:cash, `note` ≤ 1000, `idempotency_key` required|max:64).
- Controller `recordDeposit`; route `PATCH /cms/event-inquiries/{inquiry}/deposit` in its own `permission:events.deposit` group.
- Removed the two 08-02 TEMP inert entries for `events.deposit` (guide paragraph and `CmsAccessControlTest::$notYetBuilt`) — `CmsAccessControlTest` is back to its committed content.

## Tests
- `tests/Feature/Events/EventDepositTest.php` (26 incl. providers): happy path with FQCN `payable_type` (PR-8), confirmed accepted, replay 200 × 1 row, 409 on amount / note / other desk, missing/blank/whitespace key 422 with the `idempotency_key_required` message, amount × 5 invalid, cash only, note cap, second deposit 422 with context, new/in_review/cancelled 422 with `allowed`, no auto-confirm, folio balance unchanged, `assertLocksRow`, read budget ≤ 9, `events.manage` without `events.deposit` 403, 401.
- `tests/Unit/Events/RecordEventDepositActionTest.php`: status ⇔ ledger invariant across first write, replay and a refused second deposit.

## Verify
- `RecordCashPaymentAction`, `IdempotentWrite`, `PaymentMethod`, `AppServiceProvider` unchanged since 0961153 (`git diff --stat` empty).

## Deviations
- The action returns `$locked->load(DETAIL_RELATIONS)` (the in-memory row already carries the status flip) instead of `fresh()` — one read fewer, same data.
