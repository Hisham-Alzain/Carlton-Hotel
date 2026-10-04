---
phase: 09-night-audit-reports
status: complete
completed: 2026-10-04
---

# Phase 9 — Night Audit & Reports: Summary

**Base:** 3886416. Opus 5.5 stood in for Fable (no council). Plans 09-01..09-10 sequential, tests-first; detail in 09-NN-SUMMARY.md.

## Deploy notes
- **[BLOCKING]** `php artisan migrate`: `2026_10_04_100000_create_night_audit_tables` (night_audit_states, night_audits, night_audit_checks, night_audit_blockers) and `2026_10_04_100100_add_report_indexes` (folio_items(created_at), payments(status, created_at)).
- **[BLOCKING]** `php artisan db:seed --class=RolesAndPermissionsSeeder` (adds night_audit.manage).
- **[BLOCKING]** Assign reports.view and/or night_audit.manage per account (no preset holds either).
- **[BLOCKING]** Initialize the business date: the first night auditor opens `GET /api/operations/night-audit?date=<night being closed>`. No default, no reset endpoint.
- No .env changes. Mobile guide/changelog unchanged (no guest routes).

## Endpoints (auth:users, /api, no /v1)
| Verb + path | Gate | Notes |
|---|---|---|
| GET /api/operations/night-audit?date= | reports.view\|night_audit.manage | lazy open/read; `{state, audit\|null}` |
| PATCH /api/operations/night-audit/checks/{check} | night_audit.manage | `{status: resolved\|overridden, note}` |
| PATCH /api/operations/night-audit/blockers/{blocker} | night_audit.manage | `{note, status?: resolved}` |
| POST /api/operations/night-audit/{audit}/close | night_audit.manage | no body; business date +1 calendar day |
| GET /api/reports/dashboard?date_from=&date_to= | reports.view | ≤31 days, default hotel today |

## Permissions: 30 / 13 (was 29 / 12)
New group night_audit = night_audit.manage; reports.view enforced for the first time. No preset changed (RolePresetsTest pins all 7). Night manager = reports.view + night_audit.manage; night auditor without revenue access = night_audit.manage only (403 on reports). `$notYetBuilt` = ['pricing.edit'].

## Error codes (422; `custom.errors.<code>` in en/ar/fr/tr/es)
night_audit_not_initialized {requires}; night_audit_date_mismatch {requested_date, current_business_date}; night_audit_date_in_future {requested_date, hotel_today}; night_audit_closed {business_date, closed_at}; night_audit_item_resolved {item, status}; night_audit_not_ready {checks_pending, blockers_open}. Existing: forbidden 403 {reason: night_audit_not_initialized}, validation_failed, not_found.

## Dashboard (React) note
Additive routes. Renames: property_day→business_date, done→status, mock in_progress→open; gate is reports.view|night_audit.manage, not FOLIOS_VIEW; send `date` on first use. Not provided: ADR, RevPAR, MTD/YTD, room-type/booking-source revenue, daily breakdown, revenue_today/kpis, handoff notes, audit history, severity, exports. Tree api:true 88→90 is endpoint coverage, not React wiring.

## Risks / limits
- SQLite proves lock intent and uniques only. MySQL-only manual checks: concurrent first open, concurrent close, close-vs-resolve race, MoneyAggregateSqlTest on MySQL.
- Snapshot = current state at first open; blocker resolution is an attestation, not a live re-check.
- Revenue = posted folio lines; collections = completed payments, refunds not netted; occupancy uses present inventory; no no-show status.
- No tickets(status, priority) index (R-1).

## Query budgets (measured, volume-invariant)
Evaluator 10; first open 19 (init 21) ≤24; re-read 5 ≤7; PATCH 8 ≤12; close 9 (repeat 5) ≤12; reports 7 ≤8.

## Tests / gate
Baseline 2167 → final 2376 green. Scratch migrate/rollback --step=2/migrate clean; database.sqlite sha256 unchanged; no diff in AppServiceProvider, FolioLedger, Folio, Actions/Payment; seeder +4 lines, presets untouched.

## Deviations
1) report_period_too_long landed in 09-04 (R-2 said 09-08). 2) setRelation batch loading + one INSERT…SELECT for blockers. 3) No LIMIT-21 shortcut. 4) SeederTest role-less list and guide inert paragraph updated in 09-05. 5) Reports: after() order check, one conditional arrivals/departures query, array shaping (no Resource). 6) Budgets include Spatie's post-write re-read.

## Decision coverage
D-01..D-25 and R-1..R-6 each covered by the plan summaries and named tests (NightAudit*Test, MoneyAggregate*Test, Report*Test, SeederTest, PermissionsGroupedTest, RolePresetsTest, CmsAccessControlTest, Docs tests).
