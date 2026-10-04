---
phase: 09-night-audit-reports
plan: 06
status: complete
---

# 09-06 Summary — check and blocker attestation (AUDIT-02, AUDIT-03)

## Built
- `PATCH /api/operations/night-audit/checks/{check}` → `NightAuditController::updateCheck` → `ResolveNightAuditCheckAction` (`status` resolved|overridden, note required).
- `PATCH /api/operations/night-audit/blockers/{blocker}` → `resolveBlocker` → `ResolveNightAuditBlockerAction` (note required, optional `status` must be `resolved`).
- Both routes: `auth:users` + `permission:night_audit.manage`; `{check}`/`{blocker}` bind by uuid (404 `not_found`).
- `NightAuditService::lockOpenAudit(int)`: locks state → audit, throws `night_audit_closed` `{business_date, closed_at}` first. Actions then lock the child, refuse non-pending/non-open with `night_audit_item_resolved` `{item, status}`, and write only status/note/acted_by/acted_at. Check and blocker are independent; no source row written.
- Requests `ResolveNightAuditCheckRequest` / `ResolveNightAuditBlockerRequest` (trim in `prepareForValidation`, `max:1000`); controller reads only `validated()`.

## Tests
- `tests/Feature/NightAudit/NightAuditAttestationTest.php` (17): resolve/override, forgery ignored (actor, snapshot columns), terminal + passed refused, blocker resolve leaves check pending, no blocker override, closed-first on all three, validation (8 check cases, 5 blocker cases), 1000-char boundary, 404s, lock order state → audit → child, budget, source tables unchanged, notes never in activity_log.
- `NightAuditPermissionsTest` matrix += routes `check`, `blocker` (401, 7 presets 403, unrelated 403, reports.view-only 403, manage-only passes, super admin passes).

## Budget (measured, pinned)
- Each PATCH **8** (≤ 12): state, audit, child locks; update; Spatie's post-update subject re-read; checks, blockers, users.

## Deviations
- None.
