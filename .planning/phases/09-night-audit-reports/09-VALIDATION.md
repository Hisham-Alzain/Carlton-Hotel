---
phase: 9
slug: night-audit-reports
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-10-04
---

# Phase 9 — Validation Strategy

> Per-phase validation contract. Source: `09-CONTEXT.md` D-01..D-25 (final, Opus stand-in for Fable, no council) and planner rulings R-1..R-6. Supersedes the Codex draft validation of 2026-10-03.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12 via `php artisan test` (Laravel 13), SQLite `:memory:` |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter='NightAudit|Report|MoneyAggregate'` |
| **Full suite command** | `cd backend && php artisan test` (serial) |
| **Baseline** | 2167 per orchestrator at `3886416` (Phase 8 SUMMARY: 2165) — re-measured in 09-01 Task 1 (R-5) |
| **Lock proofs** | `tests/Concerns/RecordsRowLocks.php` (`for update` intent; serialization MySQL-only) |
| **Query budgets** | `tests/Concerns/CountsDomainQueries.php` (09-03; `DB::listen`, ignores `activity_log`, wraps the action/service call) — reports ≤ 8, first open ≤ 24, re-read ≤ 7, mutation ≤ 12; exact measured numbers pinned (D-22, R-4) |
| **Time** | `travelTo(...)`; `config(['hotel.timezone' => 'Europe/London'])` for DST (2026-10-25) |
| **Auth** | real Sanctum bearer tokens; `RolesAndPermissionsSeeder` seeded in permission tests |

## Sampling Rate

Every task has an automated verify. Task 1 of each plan is RED (`! php artisan test --filter=…`), Task 2 GREEN with a sentinel echo. Plans 09-05, 09-07, 09-09 and 09-10 run the full suite.

## Task map

| Task | Plan | Wave | Requirement | Threats | Behaviour | Type | Command / sentinel | File exists | Status |
|---|---|---|---|---|---|---|---|---|---|
| 09-01-01 | 01 | 1 | all | T-09-01, 02 | Baseline green; schema/enum specs RED | feature+unit | `! php artisan test --filter='NightAuditSchemaTest\|NightAuditEnumsTest'` | ❌ W0 | ⬜ pending |
| 09-01-02 | 01 | 1 | all | T-09-01, 02 | 4 tables, 2 indexes, enums, models; scratch rollback | feature+unit | `foundation ok` | ✅ after 01-01 | ⬜ pending |
| 09-02-01 | 02 | 2 | REPORT-01 | T-09-03 | Exact-cents specs RED | unit+feature | `! php artisan test --filter='MoneyAggregateTest\|MoneyAggregateSqlTest'` | ❌ W0 | ⬜ pending |
| 09-02-02 | 02 | 2 | REPORT-01 | T-09-03, 04 | MoneyAggregate | unit+feature | `money ok` | ✅ | ⬜ pending |
| 09-03-01 | 03 | 3 | AUDIT-01 | T-09-05, 06 | Evaluator specs + counter RED | feature | `! php artisan test --filter=NightAuditEvaluatorTest` | ❌ W0 | ⬜ pending |
| 09-03-02 | 03 | 3 | AUDIT-01 | T-09-05, 06 | Five evaluators | feature | `evaluators ok` | ✅ | ⬜ pending |
| 09-04-01 | 04 | 4 | AUDIT-01 | T-09-07..09 | Open-action specs RED | feature | `! php artisan test --filter=OpenNightAuditActionTest` | ❌ W0 | ⬜ pending |
| 09-04-02 | 04 | 4 | AUDIT-01 | T-09-07..09 | Lazy idempotent open; 6 exceptions; lang | feature | `open ok` | ✅ | ⬜ pending |
| 09-05-01 | 05 | 5 | AUDIT-01, XCUT | T-09-10..12 | Show contract + matrix + re-pins RED | feature | `! php artisan test --filter='NightAuditShowTest\|…'` | ❌ W0 | ⬜ pending |
| 09-05-02 | 05 | 5 | AUDIT-01, XCUT | T-09-10..12 | Route 1, seed 30/13, presets unchanged | feature | `audit read ok` (+ full suite) | ✅ | ⬜ pending |
| 09-06-01 | 06 | 6 | AUDIT-02, 03 | T-09-13..15 | Attestation specs RED | feature | `! php artisan test --filter='NightAuditAttestationTest\|NightAuditPermissionsTest'` | ❌ W0 | ⬜ pending |
| 09-06-02 | 06 | 6 | AUDIT-02, 03 | T-09-13..15 | Routes 2–3 | feature | `attestation ok` | ✅ | ⬜ pending |
| 09-07-01 | 07 | 7 | AUDIT-04 | T-09-16, 17 | Close specs + invariants RED | feature | `! php artisan test --filter='NightAuditCloseTest\|NightAuditPermissionsTest'` | ❌ W0 | ⬜ pending |
| 09-07-02 | 07 | 7 | AUDIT-04 | T-09-16, 17 | Route 4, state advance | feature | `close ok` (+ full suite) | ✅ | ⬜ pending |
| 09-08-01 | 08 | 8 | REPORT-01 | T-09-18, 19 | Period + occupancy specs RED | feature | `! php artisan test --filter='ReportDashboardPeriodTest\|ReportOccupancyTest'` | ❌ W0 | ⬜ pending |
| 09-08-02 | 08 | 8 | REPORT-01 | T-09-18, 19 | Route 5, occupancy, movement | feature | `occupancy ok` | ✅ | ⬜ pending |
| 09-09-01 | 09 | 9 | REPORT-01 | T-09-20..22 | Money/open-work/budget specs RED | feature | `! php artisan test --filter='ReportRevenueTest\|…'` | ❌ W0 | ⬜ pending |
| 09-09-02 | 09 | 9 | REPORT-01 | T-09-20..22 | Revenue, collections, open work ≤ 8 | feature | `reports ok` (+ full suite) | ✅ | ⬜ pending |
| 09-10-01 | 10 | 10 | DOCS-01 | T-09-23 | Guide, Postman, tree | docs+feature | `docs ok` | ✅ | ⬜ pending |
| 09-10-02 | 10 | 10 | XCUT-01 | — | Gate + SUMMARY coverage | script | `gate ok` | ✅ | ⬜ pending |

## Decision coverage (D-01..D-25, R-1..R-6 → plan)

| Id | Plan(s) | Id | Plan(s) | Id | Plan(s) |
|---|---|---|---|---|---|
| D-01 | 05, 06, 07, 08 | D-10 | 03 | D-19 | 09 |
| D-02 | 02–09 | D-11 | 04 | D-20 | 09, 10 |
| D-03 | 04, 05 | D-12 | 06, 07 | D-21 | 02, 09 |
| D-04 | 04, 05, 07 | D-13 | 05, 06, 07 | D-22 | 03, 04, 05, 06, 07, 09 |
| D-05 | 03, 07, 08, 09 | D-14 | 01, 06 | D-23 | 04 (audit codes/labels), 08 (`report_period_too_long`) |
| D-06 | 01, 04, 06, 07 | D-15 | 05, 06, 07, 09, 10 | D-24 | 01–09 (tests), 10 (gate) |
| D-07 | 01 | D-16 | 08 | D-25 | 10 |
| D-08 | 01 | D-17 | 08 | R-1 | 01 (no ticket index), 10 (SUMMARY) |
| D-09 | 03, 04 | D-18 | 09 | R-2 | 04, 08 |
| R-3 | 05 | R-4 | 03 (+ all budget tests) | R-5 | 01, 10 |
| R-6 | 01–10 | | | | |

D-24 test list → plan: contract gate per route (05, 06, 07, 08, 09); creation/state (04, 05); evaluators (03); snapshot (04); attestation (06); close (07); no source mutation (04, 06, 07); reports (08, 09); permissions catalogue/presets/matrix/enforced-somewhere/`night_audit.manage`-only (05, 06, 07, 09); migrations up/down on scratch (01, 10).

Claude's Discretion items: evaluator placement (03), LIMIT-21 (03), report shaping (09), locale wording (04, 08), evidence key order (03), movement query form (08), test split / factory names (all).

## Wave 0 Requirements

Every plan's Task 1 creates its own failing test file(s) before code. Shared test helpers: factories (09-01), `CountsDomainQueries` (09-03). No other fixtures.

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Concurrent first open creates one audit | AUDIT-01 | SQLite ignores `FOR UPDATE` | On MySQL, fire two `GET ?date=D` simultaneously on an uninitialized and on an initialized state; exactly 1 audit, 5 checks |
| Concurrent close advances once | AUDIT-04 | same | Two simultaneous POST close; one advance, both 200 |
| Close vs resolve race | AUDIT-02..04 | same | Resolve the last blocker while closing; close either sees it resolved or returns `night_audit_not_ready` |
| MySQL money exactness | REPORT-01 | driver | Run `MoneyAggregateSqlTest` against MySQL; strings identical to SQLite |

## Validation Sign-Off

- [x] All tasks have `<automated>` verify
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 300s per targeted run
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
