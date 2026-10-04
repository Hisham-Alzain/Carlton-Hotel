---
phase: 09-night-audit-reports
plan: 07
status: complete
---

# 09-07 Summary — close the business date (AUDIT-04)

## Built
- `POST /api/operations/night-audit/{audit}/close` (`night_audit.manage` group) → `NightAuditController::close` (no body read) → `CloseNightAuditAction`.
- Action: lock state → audit; already closed → 200 with the unchanged record (no writes); `business_date ≠ current_business_date` → 422 `night_audit_date_mismatch`; readiness from the children read under the audit lock → 422 `night_audit_not_ready {checks_pending, blockers_open}`; else `status` closed, `closed_by`, `closed_at` (UTC), state `last_closed_date = D`, `current_business_date = D + 1` via `CarbonImmutable::createFromFormat('!Y-m-d', D, HotelClock::timezone())->addDay()`. No reopen.

## Tests
- `tests/Feature/NightAudit/NightAuditCloseTest.php` (14): not ready (pending check / open blocker / each alone), success + state advance, body ignored, repeat close no-op (timestamps and state unchanged after 5 min), month end 10-31 → 11-01, Europe/London DST 10-25 → 10-26, stale-open mismatch, 404, next GET → null audit at D 23:30 then D+1 created at 01:00, one-open-audit invariant over two cycles, full open → attest → override → close cycle leaves 8 source tables identical, lock order, budget.
- `NightAuditPermissionsTest` matrix += route `close`.
- `--filter=NightAudit`: 149 green.

## Budget (measured, pinned)
- Close **9** (≤ 12): 2 locks, checks, blockers, users, audit update + Spatie re-read, state update + re-read. Repeat close **5**.

## Deviations
- None. Concurrent-close serialization remains a MySQL-only manual check.
