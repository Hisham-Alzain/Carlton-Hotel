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
| 05-01-01 | 01 | 1 | FOLIO-02 | T-05-01 / — | Reconcile keeps posted rows/disputes; folio row locked on every generate path; decimal strings | unit + feature | `cd backend && php artisan test --filter=GenerateFolioReconcileTest` | ❌ W0 | ⬜ pending |
| 05-02-01 | 02 | 2 | FOLIO-01 | T-05-02 / — | folios.view gate; 404 folio_missing; balance counts reservation payments; ≤6 queries | feature | `cd backend && php artisan test --filter=FolioReadTest` | ❌ W0 | ⬜ pending |
| 05-03-01 | 03 | 3 | FOLIO-02 | T-05-03 / — | folios.post; settled 422; credit floors; replay 200 / conflict 409; SUM invariant under interleaving | feature + unit | `cd backend && php artisan test --filter=FolioLineItemTest` | ❌ W0 | ⬜ pending |
| 05-04-01 | 04 | 4 | FOLIO-04 | T-05-04 / — | folios.settle; Idempotency-Key required; overpayment 422; auto-settle logged; prepaid close without payment; legacy settle guard | feature | `cd backend && php artisan test --filter=FolioPaymentTest` | ❌ W0 | ⬜ pending |
| 05-05-01 | 05 | 5 | FOLIO-03 | T-05-05 / — | Guest own item 200 / foreign 404 / not checked-in 403; one open dispute; staff folios.dispute; never blocks check-out | feature | `cd backend && php artisan test --filter=FolioDisputeTest` | ❌ W0 | ⬜ pending |
| 05-06-01 | 06 | 6 | DOCS-01, XCUT-01 | — | n/a | manual + grep | tree/guide/Postman checks; seeder shows folios.post + folios.dispute | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*(Task IDs are provisional; the planner replaces this table with the real task list.)*

---

## Wave 0 Requirements

- [ ] `backend/tests/Unit/Folio/GenerateFolioReconcileTest.php` — reconcile semantics, uuid stability, frozen rows, MySQL-grammar `for update` assertion
- [ ] `backend/tests/Feature/Folio/FolioReadTest.php`, `FolioLineItemTest.php`, `FolioPaymentTest.php`, `FolioDisputeTest.php`
- [ ] `database/factories/FolioItemDisputeFactory.php` (new); `FolioItemFactory` states `manual()` / `credit()`; confirm `FolioFactory`/`PaymentFactory` exist
- [ ] Update `FolioTest::test_settling_already_settled_folio_returns_422` to `folio_settled`; update `SeederTest`/`PermissionsGroupedTest` baselines (21→23 permissions)
- [ ] Strengthen `ExpressCheckoutTest` exactly-once tests to uuid stability across two refreshes

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
