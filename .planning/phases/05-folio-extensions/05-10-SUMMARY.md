---
phase: 05-folio-extensions
plan: 10
subsystem: phase close (docs, Postman, tree, gate)
requires: [05-09]
provides: [folio-docs, folio-postman, folio-tree-node, phase-gate, phase-summary]
key-files:
  created:
    - .planning/phases/05-folio-extensions/SUMMARY.md
    - .planning/phases/05-folio-extensions/05-10-SUMMARY.md
  modified:
    - backend/docs/API_GUIDE_DASHBOARD.md
    - backend/docs/API_GUIDE_MOBILE.md
    - backend/docs/CHANGELOG_MOBILE_API.md
    - backend/docs/postman/carlton-api.postman_collection.json
    - docs/carlton-tree.html
    - backend/app/Support/FolioLedger.php
    - backend/app/Services/Payment/PaymentService.php
    - backend/tests/Unit/Folio/FolioLedgerTest.php
completed: 2026-09-27
---

# 05-10 — Phase close: docs, Postman, tree, gate

The phase view (permissions, path changes, Phase 9 hooks, production notes) is in `SUMMARY.md`.

## Shipped
- Docs (Tasks 1 and 2) had already been edited before the session restart. They were **verified, not redone**: every check in the plan's `<verify>` blocks passes, and the Folios module was read against the code (FolioResource/FolioItemResource/PaymentResource keys, request bounds, exception context keys, lang messages).
  - `guides ok`: seven Folios-module headings present exactly once; eight error rows; `23 permissions`; `has_open_disputes`; the `Contract change (Phase 5)` callout; every pre-existing `##`/`###` heading kept; mobile index `62 in total` with the dispute row and `### PATCH /api/folio/items/{uuid}/dispute`; changelog `## 11 — Folio extensions (Phase 5)` and inventory line; `reservation.s/folio` count 0; PermissionGuideAccuracyTest green.
  - `postman ok`: 19 folders; folder `10 - Folio & Express Checkout` holds 10 requests in the planned order; the line-item and payment requests send `Idempotency-Key: {{idempotency_key}}` set by a `{{$guid}}` pre-request script; the guest dispute uses `{{guest_token}}`; the read request captures `ahmad_folio_uuid` and `ahmad_folio_item_uuid`.
  - `tree ok`: node `folio line items · payments` is `api:true`, meta `charges · credits · disputes · payments`, exactly the five D-18 endpoints; 94 nodes (unchanged), `api:true` +1 against the phase base (84), every other node identical; the page's `<script>` parses as JS (`new Function`).
- Fixed one failing test in production code (see Deviations).
- Phase gate run (Task 3); results below.

## Deviations from PLAN.md
- **Production fix (Rule 1, bug):** `PaymentTest::test_numeric_amounts_the_rule_accepts_never_crash_or_truncate` failed: `POST /cms/reservations/{r}/settle` with `amount_usd: "1e3"` answered **500**. The legacy `SettleReservationRequest` validates `numeric` (not `decimal:0,2`), and 05-05 had routed the amount through `FolioLedger::normalize()`, whose `bcadd` throws `ValueError` on exponent notation and truncates `10.555` to `10.55`. Added `FolioLedger::fromNumeric()` (exact exponent expansion by string shifting, then half-away-from-zero rounding to 2dp, the way a DECIMAL(10,2) column stores it; no float) and used it in `PaymentService::settleReservation`. The request rule was left as `numeric` because D-14 defers any tightening of the legacy settle route. Two unit tests added to `FolioLedgerTest`. The test was correct against the locked decisions; it was not changed.
- No other deviations; Tasks 1 and 2 needed no edits.

## Gate results
- `routes ok`: the five new routes carry exactly their guard and permission (users + folios.view / folios.post / folios.settle / folios.dispute; guests + EnsureIsCheckedIn). 340 routes in total. No PUT/PATCH/DELETE route for a folio item or payment besides the two dispute PATCHes.
- `SeederTest|PermissionsGroupedTest|PermissionGuideAccuracyTest`: 18/18, 140 assertions (23 permissions, 10 groups).
- `protected files untouched`: Guest, Reservation, the three stay resources, CancelReservationAction, ApproveCheckInAction, CheckOutReservationAction, ApproveFolioAction, PaymentGatewayInterface, ManualDriver all identical to base `00192bd`.
- Float gate: 0 `(float)` in the nine folio money-path files; exactly 1 in `RecordCashPaymentAction.php` (gateway boundary).
- No config cache (`bootstrap/cache/config.php` absent).
- Scratch SQLite (`storage/framework/phase5-gate.sqlite`): `migrate:fresh --seed`, `migrate:rollback --step=3`, `migrate` all succeed; `backend/database/database.sqlite` sha1 `babcdd27…` identical before and after (the file already showed as modified in git before this session; it was not touched or staged).
- Full suite: **1513/1513 passing, 10037 assertions** (first run this session: 1510/1511, the one failure above).
