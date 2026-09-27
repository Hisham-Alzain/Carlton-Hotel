---
phase: 05-folio-extensions
plan: 01
subsystem: folio ledger foundation
requires: []
provides: [folio-ledger-columns, FolioLedger, Folio-ledger-methods, reconcile-generate, RecordsRowLocks]
key-files:
  created:
    - backend/database/migrations/2026_09_26_130000_add_ledger_columns_to_folio_items_table.php
    - backend/app/Enums/FolioItemSource.php
    - backend/app/Support/FolioLedger.php
    - backend/tests/Concerns/RecordsRowLocks.php
    - .planning/phases/05-folio-extensions/05-BASE.txt
  modified:
    - backend/app/Models/Folio.php
    - backend/app/Models/FolioItem.php
    - backend/database/factories/FolioItemFactory.php
    - backend/app/Actions/Folio/GenerateFolioAction.php
    - backend/app/Services/Folio/ReceiptService.php
    - backend/app/Http/Resources/Folio/ReceiptResource.php
    - backend/tests/Feature/Reservations/ExpressCheckoutTest.php
completed: 2026-09-26
---

# 05-01 — Ledger foundation

## Shipped
- Migration `2026_09_26_130000_add_ledger_columns_to_folio_items_table`: quantity, unit_price_usd, source_line, posted_by (FK users nullOnDelete), reason, reverses_item_id (self FK restrictOnDelete), idempotency_key; source_type -> string(32); unique (folio_id, idempotency_key), unique (folio_id, source_type, source_id, source_line).
- `App\Enums\FolioItemSource` (RESERVATION, SERVICE_BOOKING, SERVICE_REQUEST, MANUAL, CREDIT; `generated()`, `isGenerated()`); source_type stays uncast.
- `App\Support\FolioLedger` (`normalize`, `sum`, `paid`, `balance`), bcmath scale 2, never returns "-0.00".
- `Folio::ledgerPayments(): Builder` (grouped OR folio|reservation payable), `paidUsd()`, `balanceDueUsd()`, `recalculateTotals()` (bcmath fold over re-queried `items()->pluck('amount_usd')`, consultant ruling plan-q1).
- `FolioItem` fillable/casts, `postedBy()`, `reversesItem()`, `reversals()`, `scopeWithLedgerReferences()` (`is_reversed`), `isFrozen()`.
- `GenerateFolioAction` reconcile: own `lockForUpdate` on the folio on every path (first build re-selects under lock after firstOrCreate), keyed updateOrCreate on (source_type, source_id, source_line), frozen rows neither deleted nor repriced, unreferenced dropped rows deleted, `recalculateTotals()`; no float cast, no array_sum, no delete-all.
- `ReceiptService` on `ledgerPayments()` ordered by created_at, id + `FolioLedger::balance`; private `paymentsFor()` removed; `ReceiptResource` converts the exact string to a JSON number at the boundary only.
- Factory states `FolioItemFactory::manual()` (+25.00) and `credit()` (-10.00, reason "Service recovery").
- `Tests\Concerns\RecordsRowLocks` (`lockedSelects(callable): array`, `assertLocksRow(string $table, callable)`).
- `ExpressCheckoutTest` exactly-once tests now assert 0 inserts on refresh and uuid stability across two GETs + approve.

## Deviations from PLAN.md
- The engineer wrote `RecordsRowLocks` and the `ExpressCheckoutTest` rewrite (plan Task 1 lists them as QA work) because the existing test broke under the intended contract change and the helper is shared infra. `FolioLedgerTest` and `GenerateFolioReconcileTest` are left to QA.

## Flagged assumptions
- FA-5.01-1 confirmed (bcmath fold, not SQL SUM). FA-5.01-2 confirmed (frozen = never deleted/repriced/re-described). FA-5.01-3 confirmed (source_type uncast). FA-5.01-4 confirmed (receipt balance JSON number).

## Carry-forwards
- `FolioLedger::normalize(string|int|float): string`, `sum(iterable): string`, `paid(iterable $payments): string` (completed only), `balance(string $total, string $paid): string`.
- `RecordsRowLocks::assertLocksRow('folios', fn () => ...)`: swaps the query grammar for a SQLiteGrammar subclass emitting `/* for update */`.
- Permission count unchanged (21). No lang keys, no BaseRequest mappings.

## Verification
- Scratch `migrate:fresh --seed` / `rollback --step=1` / `migrate`: ok, dev DB untouched.
- `php artisan test --filter='FolioTest|CheckOutTest|CheckOutReservationActionTest|ServiceCatalogTest|StayTest|ExpressCheckoutTest|ConcurrencyTest|PaymentTest'`: 112 passed.
