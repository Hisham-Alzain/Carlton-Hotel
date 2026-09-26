---
phase: 5
slug: folio-extensions
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-09-26
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5 (Laravel 13), SQLite `:memory:` via `backend/phpunit.xml` (lockForUpdate is a no-op; lock presence asserted via query listener) |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter=Folio` |
| **Full suite command** | `cd backend && php artisan test` |
| **Estimated runtime** | ~6 minutes (full suite) |

---

## Sampling Rate

- **After every task commit:** Run `cd backend && php artisan test --filter=Folio`
- **After every plan wave:** Run `cd backend && php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 360 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 01 | 1 | FOLIO-02 | T-05-02, T-05-04, T-05-06 | RED: reconcile, freeze, exact totals, ledger balance, lock recorder, uuid stability; phase base recorded | unit + feature | `cd backend && ! php artisan test --filter='FolioLedgerTest\|GenerateFolioReconcileTest\|ExpressCheckoutTest'` | ❌ W0 (created by this task) | ⬜ pending |
| 05-01-02 | 01 | 1 | FOLIO-02 | T-05-04 | ledger columns, restrictOnDelete reversal FK, unique keys accept seeded rows; scratch migrate/rollback | migration + regression | scratch `migrate:fresh --seed` / `rollback --step=1` / `migrate`, then `php artisan test --filter='FolioTest\|CheckOutTest\|CheckOutReservationActionTest\|ServiceCatalogTest\|StayTest'` | ✅ | ⬜ pending |
| 05-01-03 | 01 | 1 | FOLIO-02 | T-05-02, T-05-04, T-05-06 | own folio lock on every generate path; referenced rows frozen; bcmath totals; receipt on shared balance | unit + feature + grep | `cd backend && php artisan test --filter='FolioLedgerTest\|GenerateFolioReconcileTest\|ExpressCheckoutTest\|FolioTest\|CheckOutTest\|StayTest\|ConcurrencyTest'` + float/delete-all grep gates | ✅ after 05-01-01 | ⬜ pending |
| 05-02-01 | 02 | 2 | FOLIO-01 | T-05-01, T-05-06 | RED: staff read shape, signed balance, empty/ordering/adjacency probes, 404 folio_missing, gates, ≤6 queries, receipt agreement | feature | `cd backend && ! php artisan test --filter=FolioReadTest` | ❌ W0 (created by this task) | ⬜ pending |
| 05-02-02 | 02 | 2 | FOLIO-01 | — | folio_missing exception + five locales | lang + regression | `cd backend && php artisan test --filter='LocaleFoundationTest\|ValidationMessageLocalizationTest'` | ✅ | ⬜ pending |
| 05-02-03 | 02 | 2 | FOLIO-01 | T-05-01, T-05-04, T-05-08 | folios.view gate; pure read; one shape; bounded eager loads | feature + route check | `cd backend && php artisan test --filter='FolioReadTest\|FolioTest\|ExpressCheckoutTest\|StayTest\|PermissionGuideAccuracyTest'` + route:list check | ✅ after 05-02-01 | ⬜ pending |
| 05-03-01 | 03 | 3 | FOLIO-02, XCUT-01 | T-05-01, T-05-02, T-05-03, T-05-04 | RED: charges, settled 422, replay 200 / conflict 409, replay-before-settled, lock, gates, no edit route, IdempotentWrite race, 22 permissions | feature + unit | `cd backend && ! php artisan test --filter='FolioLineItemTest\|IdempotentWriteTest\|SeederTest\|PermissionsGroupedTest'` | ❌ W0 (created by this task) | ⬜ pending |
| 05-03-02 | 03 | 3 | FOLIO-02, XCUT-01 | T-05-01 | folios.post on reception; decimal mapping (tinker-checked); exceptions; lang | seeder + lang | `cd backend && php artisan test --filter='SeederTest\|PermissionsGroupedTest\|LocaleFoundationTest\|ValidationMessageLocalizationTest\|RolePresetsTest'` | ✅ | ⬜ pending |
| 05-03-03 | 03 | 3 | FOLIO-02 | T-05-02, T-05-03, T-05-04, T-05-06 | lock + replay first + settled guard + bcmul; 201/200/409 | feature + unit + route check | `cd backend && php artisan test --filter='FolioLineItemTest\|IdempotentWriteTest\|FolioReadTest\|GenerateFolioReconcileTest\|PermissionGuideAccuracyTest'` + route:list check | ✅ after 05-03-01 | ⬜ pending |
| 05-04-01 | 04 | 4 | FOLIO-02 | T-05-02, T-05-04, T-05-05 | RED: credits, item floor, balance floor (adjacency), floor order, credit replay, interleaved invariant (D-07), credited-row freeze | feature | `cd backend && ! php artisan test --filter=FolioLineItemTest` | ✅ (extends 05-03 file) | ⬜ pending |
| 05-04-02 | 04 | 4 | FOLIO-02 | — | floor exceptions; uuid and prohibited_unless mappings; lang | lang + regression | `cd backend && php artisan test --filter='LocaleFoundationTest\|ValidationMessageLocalizationTest'` | ✅ | ⬜ pending |
| 05-04-03 | 04 | 4 | FOLIO-02 | T-05-04, T-05-05, T-05-06 | credit path under the lock; folio-scoped reversal; bcmath floors | feature | `cd backend && php artisan test --filter='FolioLineItemTest\|IdempotentWriteTest\|FolioReadTest\|GenerateFolioReconcileTest'` | ✅ after 05-04-01 | ⬜ pending |
| 05-05-01 | 05 | 5 | FOLIO-04 | T-05-01, T-05-02, T-05-03, T-05-06 | RED: payment, deposits count, auto-settle log, 0.01 boundary, overpayment, zero-balance refusal, replay incl. recorded_by, key required, lock, gates | feature | `cd backend && ! php artisan test --filter=FolioPaymentTest` | ❌ W0 (created by this task) | ⬜ pending |
| 05-05-02 | 05 | 5 | FOLIO-04 | T-05-03 | payments idempotency unique index round-trips over seeded payments; exception; lang | migration + lang | scratch `migrate:fresh --seed` / `rollback --step=1` / `migrate`, then `php artisan test --filter='PaymentTest\|LocaleFoundationTest\|ValidationMessageLocalizationTest'` | ✅ | ⬜ pending |
| 05-05-03 | 05 | 5 | FOLIO-04 | T-05-02, T-05-03, T-05-04, T-05-06 | lock, replay first, settled, overpayment, auto-settle; one float at the gateway | feature + grep + route check | `cd backend && php artisan test --filter='FolioPaymentTest\|PaymentTest\|FolioTest\|FolioLineItemTest\|CheckOutTest\|CheckOutReservationActionTest'` + float grep gate + route:list check | ✅ after 05-05-01 | ⬜ pending |
| 05-06-01 | 06 | 6 | FOLIO-04 | T-05-02, T-05-03, T-05-04 | RED: payment-free close, negative balance close, prepaid check-out, auto-settle check-out, legacy settle guard + lock order, folio_settled in FolioTest | feature | `cd backend && ! php artisan test --filter='FolioPaymentTest\|FolioTest\|PaymentTest'` | ✅ (extends existing files) | ⬜ pending |
| 05-06-02 | 06 | 6 | FOLIO-04 | — | required_with mapping; close-path message | lang + regression | `cd backend && php artisan test --filter='LocaleFoundationTest\|ValidationMessageLocalizationTest'` | ✅ | ⬜ pending |
| 05-06-03 | 06 | 6 | FOLIO-04 | T-05-02, T-05-03, T-05-04, T-05-06 | close path logged; folio_settled everywhere; reservation then folio lock | feature + grep | `cd backend && php artisan test --filter='FolioPaymentTest\|FolioTest\|PaymentTest\|CheckOutTest\|CheckOutReservationActionTest\|FolioReadTest'` + grep gates | ✅ after 05-06-01 | ⬜ pending |
| 05-07-01 | 07 | 7 | FOLIO-03 | T-05-05, T-05-02, T-05-04 | RED: guest dispute matrix (own 200, foreign 404 = unknown 404, 403 not checked in, double-open 422, latest wins, settled allowed, no money), guest causer, lock, dispute freeze | feature + unit | `cd backend && ! php artisan test --filter='FolioDisputeTest\|GenerateFolioReconcileTest'` | ❌ W0 (created by this task) | ⬜ pending |
| 05-07-02 | 07 | 7 | FOLIO-03 | — | dispute table round-trips; enum, model, factory, exception, lang | migration + lang | scratch `migrate:fresh --seed` / `rollback --step=1` / `migrate`, then `php artisan test --filter='LocaleFoundationTest\|ValidationMessageLocalizationTest'` | ✅ | ⬜ pending |
| 05-07-03 | 07 | 7 | FOLIO-03 | T-05-05, T-05-02, T-05-04 | ownership in authorize() → 404; one open dispute under lock; freeze on any dispute | feature + route check | `cd backend && php artisan test --filter='FolioDisputeTest\|GenerateFolioReconcileTest\|FolioReadTest\|FolioLineItemTest\|ExpressCheckoutTest'` + route:list check | ✅ after 05-07-01 | ⬜ pending |
| 05-08-01 | 08 | 8 | FOLIO-03, XCUT-01 | T-05-01, T-05-05, T-05-04 | RED: staff raise/resolve/reject, folio_dispute_state, scoped 404, no money, gates, lock, 23 permissions | feature | `cd backend && ! php artisan test --filter='FolioDisputeTest\|SeederTest\|PermissionsGroupedTest'` | ✅ (extends existing files) | ⬜ pending |
| 05-08-02 | 08 | 8 | FOLIO-03, XCUT-01 | T-05-01 | folios.dispute on reception; exception; lang | seeder + lang | `cd backend && php artisan test --filter='LocaleFoundationTest\|ValidationMessageLocalizationTest\|RolePresetsTest'` | ✅ | ⬜ pending |
| 05-08-03 | 08 | 8 | FOLIO-03 | T-05-01, T-05-05, T-05-02 | scopeBindings + service check; resolution stamps only | feature + route check | `cd backend && php artisan test --filter='FolioDisputeTest\|SeederTest\|PermissionsGroupedTest\|PermissionGuideAccuracyTest\|FolioLineItemTest'` + route:list check | ✅ after 05-08-01 | ⬜ pending |
| 05-09-01 | 09 | 9 | FOLIO-03 | T-05-11, T-05-12 | RED: open_disputes_count, check-out not blocked, folio_unsettled only, has_open_disputes filter, Phase 9 hooks | feature | `cd backend && ! php artisan test --filter=FolioDisputeTest` | ✅ (extends existing file) | ⬜ pending |
| 05-09-02 | 09 | 9 | FOLIO-03 | T-05-11, T-05-12, T-05-08 | count as SQL subselect; filter whitelisted; CheckOutReservationAction untouched | feature + git gate | `cd backend && php artisan test --filter='FolioDisputeTest\|FolioReadTest\|CheckOutTest\|ReservationIndexFilterTest\|BaseFilterTest'` + `git diff --quiet $BASE -- app/Actions/Booking/CheckOutReservationAction.php` | ✅ after 05-09-01 | ⬜ pending |
| 05-10-01 | 10 | 10 | DOCS-01 | T-05-01 | guides and changelog match the router; inert list honest | grep + feature | heading/error-row greps + `php artisan test --filter=PermissionGuideAccuracyTest` | ✅ | ⬜ pending |
| 05-10-02 | 10 | 10 | DOCS-01 | T-05-03 | Postman Idempotency-Key pre-request; tree node flipped, others identical to base | node checks | `postman ok` / `tree ok` node scripts | ✅ | ⬜ pending |
| 05-10-03 | 10 | 10 | DOCS-01, XCUT-01 | T-05-06, T-05-13, T-05-14 | route gate, 23 permissions, scratch rollback --step=3, protected files untouched, float gate, full suite, summary contract | full suite + gates | `cd backend && php artisan test` + the plan's gate command | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Every plan is RED-first: Task 1 of each plan writes the failing tests that the plan's GREEN task must turn green, so no three consecutive tasks lack an automated verify.*

---

## Wave 0 Requirements

Created RED-first inside the plan that needs them (Task 1 of each plan), never in a separate scaffolding wave:

- [ ] `backend/tests/Concerns/RecordsRowLocks.php` (05-01-01) — records the MySQL lock clause (`for update`) each select would compile; used by 05-01, 05-03, 05-05, 05-06, 05-07, 05-08
- [ ] `backend/tests/Unit/Folio/FolioLedgerTest.php` and `GenerateFolioReconcileTest.php` (05-01-01; dispute freeze case added in 05-07-01) — bcmath helpers, ledger OR-grouping, reconcile, freeze, uuid stability, lock on every generate path
- [ ] `ExpressCheckoutTest` exactly-once tests assert uuid stability across two refreshes and zero re-inserts (05-01-01)
- [ ] `backend/tests/Feature/Folio/FolioReadTest.php` (05-02-01)
- [ ] `backend/tests/Feature/Folio/FolioLineItemTest.php` (05-03-01, credits + interleaved invariant in 05-04-01) and `backend/tests/Unit/Support/IdempotentWriteTest.php` (05-03-01, real SQLite unique violation)
- [ ] `backend/tests/Feature/Folio/FolioPaymentTest.php` (05-05-01, close path in 05-06-01)
- [ ] `backend/tests/Feature/Folio/FolioDisputeTest.php` (05-07-01 guest, 05-08-01 staff, 05-09-01 flags)
- [ ] Factories: `FolioItemFactory` states `manual()` / `credit()` (05-01-02); `FolioItemDisputeFactory` with `resolved()`, `rejected()`, `byStaff()` (05-07-02); `FolioFactory` and `PaymentFactory` already exist (verified)
- [ ] Baselines: `SeederTest`/`PermissionsGroupedTest` 21 → 22 permissions (05-03-01) → 23 (05-08-01), still 10 groups; `FolioTest::test_settling_already_settled_folio_returns_422` asserts `folio_settled` (05-06-01); `PaymentTest` legacy-guard methods (05-06-01)
- [ ] No framework install needed (PHPUnit 12 and Laravel test infrastructure present; bcmath loaded)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Guides, changelog, Postman (Idempotency-Key pre-request) and tree node updated | DOCS-01 | Documentation content | Open `docs/carlton-tree.html`; "folio line items · payments" is api:true with five endpoints; dashboard guide Folios module lists the four staff routes and the credit-not-edit rule |
| Summary lists `folios.post`, `folios.dispute`, presets, [BLOCKING] notes (migrate, seeder) and Phase 9 read hooks | XCUT-01 | Summary format | Read the phase SUMMARY.md |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 360s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
