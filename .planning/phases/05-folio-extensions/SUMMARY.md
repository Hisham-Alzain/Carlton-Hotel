---
phase: 05-folio-extensions
status: complete
completed: 2026-09-27
requirements-completed: [FOLIO-01, FOLIO-02, FOLIO-03, FOLIO-04, DOCS-01, XCUT-01]
---

# Phase 5: Folio Extensions — Summary

Staff manage a reservation's folio end to end: read it (`GET /cms/reservations/{reservation}/folio`, pure read, 404 `folio_missing`), post charges and credits (`POST /cms/folios/{folio}/line-items`), take manual payments (`POST /cms/folios/{folio}/payments`, `Idempotency-Key` required, auto-settle at zero) and raise/resolve/reject line-item disputes (`PATCH /cms/folios/{folio}/line-items/{item}/dispute`); guests dispute their own lines (`PATCH /folio/items/{item}/dispute`). The folio is an append-only ledger: no PATCH/DELETE of money rows, corrections are signed credit rows, every writer takes the folio row lock, totals are recomputed from the rows under that lock, and all folio money is bcmath on 2dp strings (one `(float)` left, at the `PaymentGatewayInterface` boundary). `GenerateFolioAction` reconciles instead of rebuilding (stable item uuids; desk, credited and disputed rows survive). `balance_due_usd` = total − completed payments on the folio or its reservation, signed and never clamped, from one helper shared by the folio resource and the receipt. The folio settle route gains a payment-free close path; the legacy reservation settle refuses once the folio is settled. Three additive, reversible migrations; two new permissions; eight new error codes. Ten waves (05-01 to 05-10).

## Endpoints delivered

| Endpoint | Guard/Permission | Notes |
|---|---|---|
| `GET /api/cms/reservations/{reservation}/folio` | `auth:users`, `permission:folios.view` | Pure read, any status, never generates; 404 `folio_missing` `{reservation_uuid, reservation_status}`; ≤ 6 queries. |
| `POST /api/cms/folios/{folio}/line-items` | `auth:users`, `permission:folios.post` | kind charge/credit, description, quantity 1–999, `unit_price_usd` decimal:0,2 0.01–99999.99, `reason` (credit), `reverses_item_uuid` (credit, same folio). Optional `Idempotency-Key`. Item floor then balance floor. 201 / 200 replay / 409. |
| `POST /api/cms/folios/{folio}/payments` | `auth:users`, `permission:folios.settle` | `Idempotency-Key` required (422 `errors.idempotency_key`); amount ≤ balance (422 `folio_overpayment`); auto-settles at 0.00 (`folio.auto_settled`). 201 / 200 replay (recorder is part of the payload). |
| `PATCH /api/cms/folios/{folio}/line-items/{item}/dispute` | `auth:users`, `permission:folios.dispute`, `scopeBindings()` | action raise/resolve/reject; one open dispute per item; resolution never moves money. |
| `PATCH /api/folio/items/{item}/dispute` | `auth:guests`, `is_checked_in` | Own items only (foreign/unknown → identical 404 `not_found`); open and settled folios; re-dispute after a decision. |
| `POST /api/cms/folios/{folio}/settle` (changed) | unchanged | amount optional; balance ≤ 0 closes with no payment (`folio.settled_no_payment`); double settle → 422 `folio_settled` (was `reservation_state`). |
| `POST /api/cms/reservations/{reservation}/settle` (changed) | unchanged | 422 `folio_settled` once the folio is settled; reservation → folio lock order; amount exact (bcmath, exponent input accepted, half-up rounding). |
| `GET /api/cms/reservations` (changed) | unchanged | `has_open_disputes=1|0` (other values 422). |
| `POST /api/cms/reservations/{reservation}/check-out` (changed) | unchanged | `folio.open_disputes_count`; disputes never gate check-out. |
| Every folio response (changed) | unchanged | Additive `payments[]` (no `recorded_by`), `paid_usd`, `balance_due_usd`, `open_disputes_count`; items gain `quantity`, `unit_price_usd`, `posted_by`, `posted_at`, `reason`, `reverses_item_uuid`, `dispute`; stable item uuids. |

## Waves

1. 05-01 Ledger foundation (ledger columns, `FolioItemSource`, `FolioLedger`, Folio ledger methods, reconcile generate with own lock, receipt on shared helper, `RecordsRowLocks`).
2. 05-02 Staff folio read (`adminShow`, shared `display()` loader, one shape, `FolioMissingException`, all Phase 5 lang keys and exceptions).
3. 05-03 Manual charges + idempotency (`IdempotentWrite`, `PostFolioItemRequest/Action`, `folios.post`, 22 permissions).
4. 05-04 Credits and floors.
5. 05-05 Payments (payments `idempotency_key`, `RecordFolioPaymentAction`, overpayment, auto-settle, decimal-string `RecordCashPaymentAction`).
6. 05-06 Settle close path and reservation-settle guard.
7. 05-07 Guest disputes (`folio_item_disputes`, `FolioItemDispute`, `RaiseFolioDisputeAction`, dispute freeze).
8. 05-08 Staff disputes (`ResolveFolioDisputeAction`, `folios.dispute`, 23 permissions).
9. 05-09 Open-dispute flags (scopes, `open_disputes_count`, `has_open_disputes`, check-out count).
10. 05-10 Close (docs, Postman, tree, gate, this summary; legacy-settle `numeric` amount fix).

## Key decisions (with sources)

| # | Decision | Source |
|---|---|---|
| 1 | D-01 … D-19 implemented as locked. | Fable consultant + councils `wf_681ece81-b59`, `wf_ab8560d3-cb5` |
| 2 | `recalculateTotals()` = bcmath fold over re-queried amounts under the lock. | consultant plan-q1 |
| 3 | Payment-free close inline in `SettleFolioAction`. | consultant plan-q2 |
| 4 | `Idempotency-Key` merged in `prepareForValidation()` (trim, blank → null, overwrites body). | consultant plan-q3 |
| 5 | Missing key on payments = `validation_failed` on `errors.idempotency_key`. | FA-5.05-2 |
| 6 | Payment replay compares method, amount, note, recorded_by; item replay ignores poster. | D-08, FA-5.03-2 |
| 7 | Frozen rows never deleted, repriced or re-described. | FA-5.01-2 |
| 8 | `payments[]` omit `recorded_by`; `posted_by` and `resolution_note` guest-visible. | FA-5.02-1/2, FA-5.07-3 |
| 9 | `folio_dispute_state` context.status null when never disputed. | FA-5.08-1 |
| 10 | `has_open_disputes` accepts only "1"/"0". | FA-5.09-1 |
| 11 | Receipt balance stays a JSON number. | FA-5.01-4 |
| 12 (build) | Legacy reservation settle keeps `numeric`; amounts go through `FolioLedger::fromNumeric()` (fixes 500 on `1e3`, truncation of `10.555`). | 05-10 |
| 13 | Other flagged assumptions confirmed as written. | build |

## Permissions (XCUT-01)

| Permission | Seeded role presets | Routes it gates | Notes |
|---|---|---|---|
| `folios.dispute` | reception | PATCH /cms/folios/{folio}/line-items/{item}/dispute | same presets as folios.post (D-11) |
| `folios.post` | reception | POST /cms/folios/{folio}/line-items | every preset holding folios.settle (D-05) |

23 permissions in 10 groups; `folios.view` gates the new staff read; `folios.settle` gates payments; the guest dispute route uses the guest token and `is_checked_in`.

## Dashboard & App Path Changes (DOCS-01)

| Client (dashboard / app) | Method | Path | Change (added / changed / removed) | Notes |
|---|---|---|---|---|
| app | GET | `/folio` | changed: additive | payments, paid_usd, balance_due_usd, open_disputes_count, item fields; stable item uuids |
| app | POST | `/folio/approve` | changed: additive | same folio shape |
| app | PATCH | `/folio/items/{item}/dispute` | added | guest line-item dispute |
| dashboard | POST | `/cms/folios/{folio}/line-items` | added | mock `POST /folios/{id}/line-items` |
| dashboard | PATCH | `/cms/folios/{folio}/line-items/{item}/dispute` | added | mock `PATCH …/line-items/{id}/dispute` |
| dashboard | POST | `/cms/folios/{folio}/payments` | added | mock `POST /folios/{id}/payments`; Idempotency-Key required |
| dashboard | POST | `/cms/folios/{folio}/settle` | changed, BREAKING | double settle `reservation_state` → `folio_settled`; amount optional; nothing due closes without payment (D-14, consultant + council) |
| dashboard | POST | `/cms/folios/{reservation}/generate` | changed: additive | extended shape, stable uuids |
| dashboard | GET | `/cms/reservations` | changed: additive | `has_open_disputes` |
| dashboard | POST | `/cms/reservations/{reservation}/check-out` | changed: additive | `folio.open_disputes_count` |
| dashboard | GET | `/cms/reservations/{reservation}/folio` | added | mock `GET /reservations/{id}/folio` |
| dashboard | POST | `/cms/reservations/{reservation}/settle` | changed | new 422 `folio_settled` once the folio is settled |

Changed paths: `POST /cms/folios/{folio}/settle` (breaking; `reservation_state` no longer returned for a double folio settle) and `POST /cms/reservations/{reservation}/settle`; fields added on every folio response, the check-out summary and the reservation filter; error codes added `folio_missing` 404; `folio_settled`, `folio_credit_exceeds_item`, `folio_credit_exceeds_balance`, `folio_overpayment`, `folio_item_dispute_open`, `folio_dispute_state` 422; `idempotency_conflict` 409.

## Docs Updated (DOCS-01)

- [x] API_GUIDE_DASHBOARD.md: Folios module retitled (4 permissions), Ledger rules, `### Idempotency-Key`, `### GET /cms/reservations/{reservation}/folio`, folio shape, `### POST /cms/folios/{folio}/line-items`, `### POST /cms/folios/{folio}/payments`, `### PATCH /cms/folios/{folio}/line-items/{item}/dispute`, rewritten settle with contract-change callout; has_open_disputes; check-out open_disputes_count; reservation-settle folio_settled; 8 error rows; catalog 23 permissions; reception preset.
- [x] API_GUIDE_MOBILE.md: index 61 → 62; extended `### GET /api/folio`; `### PATCH /api/folio/items/{uuid}/dispute`.
- [x] CHANGELOG_MOBILE_API.md: `## 11 — Folio extensions (Phase 5)`, breaking dashboard settle row, non-breaking GET /folio and POST /folio/approve rows, inventory lines.
- [x] Postman `10 - Folio & Express Checkout`: read folio, post a line item, guest dispute, resolve a dispute, record a payment; settle description updated; `{{$guid}}` Idempotency-Key pre-request.
- [x] carlton-tree.html: `folio line items · payments` api:true, five endpoints (94 nodes, 84 api:true).

## Phase 9 read hooks (D-16)

`Folio::unsettled()`, `Folio::withOpenDisputes()`, `Folio::openDisputesCount()`, `Folio::openDisputes()`, `FolioItemDispute::open()`, `Folio::ledgerPayments()/paidUsd()/balanceDueUsd()`, `FolioResource.open_disputes_count`, `has_open_disputes` filter. Suggested queries: unsettled departures `GET /cms/reservations?status=checked_out&folio_status=open` (or `Folio::unsettled()` joined to checked-out reservations); open disputes on in-house/checked-out stays `GET /cms/reservations?status[in]=checked_in,checked_out&has_open_disputes=1` (or `Folio::withOpenDisputes()` joined the same way).

## Edge and prohibition coverage

FOLIO-01 adjacency/empty/ordering (05-02, FolioReadTest); FOLIO-02 empty/ordering (05-03) and adjacency (05-04), FolioLineItemTest; FOLIO-03 empty/ordering/adjacency (05-07, 05-08, FolioDisputeTest); FOLIO-04 unclassified unresolved and flagged (FA-5.05-1). 10 = 9 authored + 1 flagged. Prohibitions/backstops: 16, all kept; three are MySQL-only lock backstops proven via compiled-SQL `for update` assertions.

## Test counts

Phase 5 specs: FolioReadTest 11, FolioLineItemTest 33, FolioPaymentTest 24, FolioDisputeTest 27, FolioLedgerTest 10, GenerateFolioReconcileTest 11, IdempotentWriteTest 7, FolioWriterActionsTest 9 = 132. Full suite: 1522/1522, 10092 assertions, 0 failures (Phase 4 closed at 1383).

## Deviations from PLAN.md

05-02 added all lang keys/exceptions in one pass; 05-03 shipped directly in the 05-04 shape; 05-01 engineer wrote RecordsRowLocks and the ExpressCheckoutTest rewrite; 05-06 no-amount check also refuses a null method; pins widened (ExpressCheckoutTest FOLIO_KEYS, CheckOutTest summary; FolioTest double-settle → folio_settled per D-14); 05-10 fixed the legacy-settle 500 on `1e3`/truncation of `10.555` with `FolioLedger::fromNumeric()`; 05-10 docs/Postman/tree verified, not redone. The crew run was interrupted by a session restart after 05-09 and finished with directly spawned engineer/QA agents (the Workflow tool was unavailable in the new session).

QA added tests/Unit/Folio/FolioWriterActionsTest.php (9 tests); closer applied max:99999.99 on settle amounts and localized the settle validation attribute.

## Flagged carry-forwards

FOLIO-04 unclassified probe; FA-06-1 still deferred (avoided by the dispute ownership rule); MySQL-only lock backstops (generate, post, pay, settle, dispute) + no MySQL CI; FA-5.03-4 "characters" wording for numeric min/max; refunds not subtracted (D-03); receipt balance JSON number (FA-5.01-4); settle tightening and Idempotency-Key on settle routes deferred; CONTEXT deferred ideas (refunds, overpayment credit, online payments, reopen, write-off, localized descriptions, taxes, categories, dispute withdraw/under_review/notifications/re-raise cap, freeze-after-payment, per-night postings).

QA minors: folio `total_usd` DECIMAL(10,2) can overflow on MySQL with several very large lines (needs a total ceiling or wider column); receipt balance float JSON (FA-5.01-4, tracked); migration 130000 explicit indexes after `constrained()` may duplicate FK indexes on MySQL (no MySQL CI).

## Production deploy notes

- [BLOCKING] Run php artisan migrate — three additive, reversible migrations (130000 ledger columns, narrows `folio_items.source_type` to 32 chars, stored values ≤ 15; 130100 payments idempotency_key; 130200 folio_item_disputes).
- [BLOCKING] Run php artisan db:seed --class=RolesAndPermissionsSeeder — adds folios.post and folios.dispute to reception; clears the permission cache.
- [BLOCKING] Confirm the bcmath extension is loaded for both PHP-FPM and the CLI (`php -m | grep -i bcmath`).
- [BLOCKING] Idempotency-Key contract: clients send `Idempotency-Key` (≤ 64 chars, one UUID per user action, reused on retries) on folio payments or get 422; replay → 200 current folio; different payload → 409; keys never expire.
- Notify React (settle breaking change, mocks now live) and Flutter (additive GET /folio, dispute route).

## Files touched

Three migrations; FolioItemFactory, FolioItemDisputeFactory; RolesAndPermissionsSeeder; enums FolioItemSource, FolioDisputeStatus; models Folio, FolioItem, FolioItemDispute, Payment; Support FolioLedger, IdempotentWrite; eight exceptions; actions GenerateFolio, SettleFolio, PostFolioItem, RecordFolioPayment, RaiseFolioDispute, ResolveFolioDispute, RecordCashPayment; services FolioService, ReceiptService, PaymentService, ReservationService; ReservationFilter; BaseRequest; five Folio requests; FolioResource, FolioItemResource, ReceiptResource, ReservationResource; Admin + Api FolioController; routes/api.php; five lang files; tests (RecordsRowLocks, the Phase 5 specs, FolioTest, PaymentTest, CheckOutTest, ExpressCheckoutTest, SeederTest, PermissionsGroupedTest); three backend docs, Postman collection, docs/carlton-tree.html. See 05-10-SUMMARY.md for gate output.
