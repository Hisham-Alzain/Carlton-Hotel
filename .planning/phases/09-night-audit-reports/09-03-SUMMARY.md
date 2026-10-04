---
phase: 09-night-audit-reports
plan: 03
status: complete
---

# 09-03 Summary — the five evaluators

## Built
- `app/Support/NightAuditEvaluator.php` (final): `evaluate(string $businessDate): array` keyed by `NightAuditCheckType` value in enum order → `{issue_count, evidence, evidence_truncated}`; one private method per type; shared `summarise()` runs one exact COUNT + one LIMIT-20 sample.
  - departures: `whereDate(check_out)`, confirmed/checked_in/checked_out, no folio OR open folio; evidence `{reservation_uuid, booking_code}` by booking_code, id.
  - arrivals: `whereDate(check_in)`, confirmed/checked_in, no lines OR a null-room line; same evidence.
  - dirty rooms: live (SoftDeletes scope), `is_active`, status dirty; `{room_uuid, number}` by number, id.
  - tickets: `TicketStatus::active()`, priority >= 3 (`HIGH_PRIORITY` const tied to `ServiceRequestPriority::fromTicketScale`); `{ticket_uuid}` by id.
  - disputes: status open, joined folio_items → folios; `{dispute_uuid, folio_uuid}` by dispute id.
- `tests/Concerns/CountsDomainQueries.php`: `domainQueries(callable): list<string>` and `countDomainQueries(callable): int` (DB::listen, skips `activity_log`) (R-4).

## Tests
- `tests/Feature/NightAudit/NightAuditEvaluatorTest.php` (26): every D-10 inclusion/exclusion pair, SQLite `Y-m-d 00:00:00` storage, ordering, 20-cap + truncation (25 → truncated, 20 → not), PII absence, read-only (every statement is a SELECT), constant budget.

## Budget (measured, pinned)
- Evaluation = **10 statements** with 0 issues and with 25 issues in every category (5 × COUNT + sample).

## Deviations
- LIMIT-21 optimisation **not used** (Claude's discretion): it would make the query count differ between ≤ 20 and > 20 issues, contradicting D-22's "identical counts" requirement.
