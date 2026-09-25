---
phase: 1
slug: access-settings
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-09-25
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5 (Laravel 13), SQLite `:memory:` via `backend/phpunit.xml` |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter=Auth` |
| **Full suite command** | `cd backend && php artisan test` |
| **Estimated runtime** | ~60–90 seconds (full suite, 250+ tests) |

---

## Sampling Rate

- **After every task commit:** Run `cd backend && php artisan test --filter=Auth`
- **After every plan wave:** Run `cd backend && php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 01-01-01 | 01 | 1 | ACCESS-01 | T-01-01 / — | Revoked token gets 401 on every guest route; optional device_token only deletes a row owned by the caller | feature | `cd backend && php artisan test --filter=GuestLogoutTest` | ❌ W0 | ⬜ pending |
| 01-01-02 | 01 | 1 | ACCESS-02 | T-01-02 / — | Email change requires current password; duplicate email → 422 validation_failed | feature | `cd backend && php artisan test --filter=StaffProfileTest` | ❌ W0 | ⬜ pending |
| 01-01-03 | 01 | 1 | ACCESS-03 | T-01-03 / — | Wrong current password → 422; other tokens revoked, current kept; 6th call/min → 429 | feature | `cd backend && php artisan test --filter=StaffPasswordChangeTest` | ❌ W0 | ⬜ pending |
| 01-01-04 | 01 | 1 | DOCS-01 | — | n/a | manual + grep | `grep -c "auth/profile" backend/docs/API_GUIDE_DASHBOARD.md` and tree node check | n/a | ⬜ pending |
| 01-01-05 | 01 | 1 | XCUT-01 | — | No new permissions introduced; stated in SUMMARY | manual | `cd backend && php artisan test --filter=RolesAndPermissions` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*(Task IDs are provisional until PLAN.md is written; the planner replaces this table with the real task list.)*

---

## Wave 0 Requirements

- [ ] `backend/tests/Feature/Auth/GuestLogoutTest.php` — stubs for ACCESS-01 (must use real bearer tokens via `withToken()`, never `actingAs()`, so `currentAccessToken()` is a real token)
- [ ] `backend/tests/Feature/Auth/StaffProfileTest.php` — stubs for ACCESS-02
- [ ] `backend/tests/Feature/Auth/StaffPasswordChangeTest.php` — stubs for ACCESS-03 incl. 429 throttle case
- Existing infrastructure (`backend/tests/TestCase.php` helpers, factories for `User`, `Guest`, `DeviceToken`) covers the rest.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| API guides, Postman and `docs/carlton-tree.html` nodes updated | DOCS-01 | Documentation content, not runtime behaviour | Open `docs/carlton-tree.html` in a browser; "guest sign out" and "dashboard settings" nodes show api:true with the new endpoints; API guide Auth modules list the 4 routes |
| Phase summary lists "no new permissions" and dashboard path changes | XCUT-01 | Summary format convention | Read `01-SUMMARY.md` after execution |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
