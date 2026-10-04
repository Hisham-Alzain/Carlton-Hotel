---
phase: 09-night-audit-reports
plan: 01
status: complete
---

# 09-01 Summary — night audit storage + report indexes

## Baseline (R-5)
- Full suite on the untouched base `3886416`: **2167 passed** (13475 assertions). This is the phase gate baseline (the Phase 8 SUMMARY's 2165 is superseded).

## Built
- `database/migrations/2026_10_04_100000_create_night_audit_tables.php`: `night_audit_states` (singleton unique, no uuid), `night_audits` (business_date unique, status indexed, opened_by/closed_by FK indexed), `night_audit_checks` (unique `(night_audit_id, type)`, acted_by indexed), `night_audit_blockers` (`night_audit_check_id` unique, night_audit_id + acted_by indexed). Every FK `restrictOnDelete`; no soft deletes; `down()` drops in reverse FK order.
- `database/migrations/2026_10_04_100100_add_report_indexes.php`: `folio_items_created_at_index`, `payments_status_created_at_index`; `down()` drops only those (D-08). No ticket index (R-1).
- Enums: `NightAuditStatus`, `NightAuditCheckType` (fixed order, `isBlocking()`, `label()` → `custom.night_audit.checks.*`), `NightAuditCheckStatus` (`isTerminal()`), `NightAuditBlockerStatus`.
- Models `NightAuditState`, `NightAudit` (`SNAPSHOT_BASIS` const, `checks/blockers/opener/closer`, `isClosed()`), `NightAuditCheck` (`audit/actor/blocker`), `NightAuditBlocker` (`audit/check/actor`). Each overrides `getActivitylogOptions()` with `logOnly` (checks/blockers: status, acted_by; audit: status, closed_by, closed_at; state: the two dates) — note/evidence never logged (D-14).
- Factories for all four (`NightAuditStateFactory::on()`, `NightAuditFactory::closed()`, `NightAuditCheckFactory::ofType()/passed()`, `NightAuditBlockerFactory::forCheck()`).

## Tests
- `tests/Feature/Database/NightAuditSchemaTest.php` (11): columns, 4 unique constraints, restrict FKs (users + audit→checks), index column lists, relations/casts, no SoftDeletes, activity log excludes note/evidence.
- `tests/Unit/NightAudit/NightAuditEnumsTest.php` (4).
- Scratch-DB proof (scratchpad `p9.sqlite`): `migrate:fresh` → `migrate:rollback --step=2` → `migrate` all clean; `database/database.sqlite` untouched.

## Deviations
- None.
