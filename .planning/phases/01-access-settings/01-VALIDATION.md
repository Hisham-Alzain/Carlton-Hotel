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
| 01-01-01 | 01 | 1 | ACCESS-01 | T-01-01 | Failing spec (RED): revoked token 401 on every `auth:guests` route; device_token ownership; real bearer tokens only | feature (RED) | `cd backend && php -l tests/Feature/Auth/GuestLogoutTest.php && ! php artisan test --filter=GuestLogoutTest` | ❌ W0 (created by this task) | ⬜ pending |
| 01-01-02 | 01 | 1 | ACCESS-01 | T-01-01, T-01-01b | Only the current token revoked; device-token delete scoped to the caller; staff token untouched | feature | `cd backend && php artisan test --filter=GuestLogoutTest` | ✅ after 01-01-01 | ⬜ pending |
| 01-02-01 | 02 | 2 | ACCESS-02 | T-01-02, T-01-08 | Failing spec (RED): email change needs current_password; privileged fields ignored; empty/encoding/idempotency edges | feature (RED) | `cd backend && php -l tests/Feature/Auth/StaffProfileTest.php && ! php artisan test --filter=StaffProfileTest` | ❌ W0 (created by this task) | ⬜ pending |
| 01-02-02 | 02 | 2 | ACCESS-02 | T-01-02, T-01-08 | Email change gated by `current_password:users`; only name/email reach the model; same shape as /auth/me | feature | `cd backend && php artisan test --filter=StaffProfileTest` (15 of 16 green; the localized-message test waits for 01-02-03) | ✅ after 01-02-01 | ⬜ pending |
| 01-02-03 | 02 | 2 | ACCESS-02 | — | `current_password` message localized in en/ar/fr/tr/es | feature + locale guards | `cd backend && php artisan test --filter='StaffProfileTest\|ValidationMessageLocalizationTest\|LocaleFoundationTest' && php artisan test` | ✅ | ⬜ pending |
| 01-03-01 | 03 | 3 | ACCESS-03 | T-01-03, T-01-04 | Failing spec (RED): others revoked/current kept; 429 on 6th call per account; no hash in activity_log | feature (RED) | `cd backend && php -l tests/Feature/Auth/StaffPasswordChangeTest.php && ! php artisan test --filter=StaffPasswordChangeTest` | ❌ W0 (created by this task) | ⬜ pending |
| 01-03-02 | 03 | 3 | ACCESS-03 | T-01-03, T-01-04, T-01-11, T-01-12 | `throttle:5,1` keyed per user; transaction revokes other tokens; `activitylog.default_except_attributes` excludes password | feature | `cd backend && php artisan test --filter=StaffPasswordChangeTest` (13 of 14 green; the localized-message test waits for 01-03-03) | ✅ after 01-03-01 | ⬜ pending |
| 01-03-03 | 03 | 3 | ACCESS-03 | — | `confirmed`/`different`/`password_changed` localized in five locales | feature + locale guards | `cd backend && php artisan test --filter='StaffPasswordChangeTest\|StaffProfileTest\|ValidationMessageLocalizationTest\|LocaleFoundationTest' && php artisan test` | ✅ | ⬜ pending |
| 01-04-01 | 04 | 4 | DOCS-01 | T-01-15 | Guides document only shipped behaviour with real error codes | grep | Heading/order/count greps in the 01-04 Task 1 `<verify>` | n/a | ⬜ pending |
| 01-04-02 | 04 | 4 | DOCS-01 | T-01-14 | Postman order safe (revoking requests last); tree parses, only two nodes changed | node JSON checks | Postman + tree node one-liners in the 01-04 Task 2 `<verify>` (tree baseline sha1 `b71ef1ee…`) | n/a | ⬜ pending |
| 01-04-03 | 04 | 4 | DOCS-01, XCUT-01 | T-01-16 | No new permission; four routes on correct guards; password throttled, profile not | route:list JSON + full suite | Route-contract checker in the 01-04 Task 3 `<verify>`, then `cd backend && php artisan test` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Sampling continuity: every task has an automated command, and no three consecutive tasks lack one. RED tasks (01-0N-01) are expected to exit non-zero on the test run itself; their `<automated>` inverts that with `!` after a `php -l` syntax gate.*

---

## Wave 0 Requirements

- [ ] `backend/tests/Feature/Auth/GuestLogoutTest.php`: 12 tests for ACCESS-01, created RED by task 01-01-01 (real bearer tokens via `withToken()`, never `actingAs()`, so `currentAccessToken()` is a real token; includes the all-`auth:guests`-routes scan)
- [ ] `backend/tests/Feature/Auth/StaffProfileTest.php`: 16 tests for ACCESS-02, created RED by task 01-02-01
- [ ] `backend/tests/Feature/Auth/StaffPasswordChangeTest.php`: 14 tests for ACCESS-03 including the 429 per-user throttle case and the activity-log leak check, created RED by task 01-03-01
- Existing guards that this phase must keep green and that pin its localization contract: `tests/Feature/ValidationMessageLocalizationTest.php` (every reachable rule mapped; mapped keys translated in en/ar/fr/tr/es; FormRequest `rules()` called with **no user**) and `tests/Feature/Cms/LocaleFoundationTest.php` (identical `custom.php` key sets across all five locales).
- Existing infrastructure (factories for `User`, `Guest`, `DeviceToken`; `RolesAndPermissionsSeeder`) covers the rest. Staff-token helpers are per-test private methods, not in `tests/TestCase.php`.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| API guides, Postman and `docs/carlton-tree.html` nodes updated | DOCS-01 | Documentation content, not runtime behaviour | Open `docs/carlton-tree.html` in a browser; "guest sign out" and "dashboard settings" nodes show api:true with the new endpoints; API guide Auth modules list the 4 routes |
| Phase summary lists "no new permissions" and dashboard path changes | XCUT-01 | Summary format convention | Read `01-04-SUMMARY.md` after execution: `## Permissions (XCUT-01)`, `## Dashboard & App Path Changes (DOCS-01)` and `## Docs Updated (DOCS-01)` are present in that order, per the contract in `.planning/codebase/CONVENTIONS.md` |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
