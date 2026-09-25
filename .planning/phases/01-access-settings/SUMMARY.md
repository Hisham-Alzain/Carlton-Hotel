---
phase: 01-access-settings
status: complete
completed: 2026-09-25
requirements-completed: [ACCESS-01, ACCESS-02, ACCESS-03, DOCS-01, XCUT-01]
---

# Phase 1: Access & Settings — Summary

Four additive, no-migration, no-new-permission endpoints shipped under the existing `auth:guests` / `auth:users` route groups, built as four TDD waves (01-01 -> 01-04, ordered by shared-file edits, not logical dependency), closed with the full suite green.

## Endpoints delivered

| Endpoint | Guard | Throttle | Notes |
|---|---|---|---|
| `POST /api/auth/guest/logout` | `auth:guests` | none | Deletes only the current token; falls back to all tokens only when there is no current token. `device_token` deletion goes through `$guest->deviceTokens()` - a foreign/unknown/absent token is a silent 200. |
| `GET /api/auth/profile` | `auth:users` | none | Same `UserResource` shape as `GET /auth/me`. |
| `PUT /api/auth/profile` | `auth:users` | none | Only `name`/`email` persisted via `safe()->only`. `current_password` required only when the email differs by strict string compare (case-only change still requires it). `data: null`. |
| `PUT /api/auth/password` | `auth:users` | `throttle:5,1`, keyed per authenticated user (plain alias - no named `RateLimiter` needed) | `hashed` cast does the hashing (no `Hash::make`); revokes every other token via `whereKeyNot(currentToken)`, keeps the current one; `data: null`. |

## Waves

1. **01-01 - Guest logout** (`GuestLogoutTest`, 12 methods): `GuestLogoutRequest`, `AuthGuestService::logout`, `GuestAuthController::logout`, route.
2. **01-02 - Staff profile** (`StaffProfileTest`, 16 methods): `UpdateStaffProfileRequest`, `AuthStaffService::{profile,updateProfile}`, `StaffAuthController::{profile,updateProfile}`, routes.
3. **01-03 - Password change** (`StaffPasswordChangeTest`, 14 methods): `ChangePasswordRequest`, `AuthStaffService::changePassword`, `StaffAuthController::changePassword`, route; plus the activity-log secret leak fix (see Security fix below).
4. **01-04 - Docs & contract closure**: API guides (dashboard + mobile), Postman collection, `docs/carlton-tree.html`, and the new "Phase Summary Contract" section in `.planning/codebase/CONVENTIONS.md` that all later phases follow.

Each wave: QA writes a failing feature-test spec first (real bearer tokens via `createToken()->plainTextToken` + `withToken()`, never `actingAs()`; `auth()->forgetGuards()` between a revoking request and the next), the engineer implements to green, and a delegate localizes new strings into all five locales (en/ar/fr/tr/es), keyed in `BaseRequest::messages()`.

## Security fix (found during planning, fixed in 01-03)

Spatie's `LogsActivity` ignores Laravel's `$hidden`, so it copied the bcrypt password hash into `activity_log.attribute_changes` on every staff create and password change (spatie/laravel-activitylog v5 records model diffs in `attribute_changes`, not `properties`). Fixed via a **global** config exclusion - `config/activitylog.php`: `default_except_attributes => ['password', 'remember_token']` - not a per-model `logExcept()`, since the trait's options are fixed. Combined with the trait's `dontLogEmptyChanges()`, a password-only update now writes **no** activity row at all; the "no hash in the log" test passes because no row is written, not because a row is filtered.

## Key decisions (with sources)

| # | Decision | Source |
|---|---|---|
| 1 | FA-01-1 / FA-03-1 edge-probe ledger rows stay `unclassified`/`unresolved`; behaviors are covered by tests, but the ledger itself is not closed by this phase - the verifier decides. | consultant |
| 2 | `PUT /auth/password` returns `data: null`, not `{revoked_sessions: n}`. | consultant |
| 3 | No named `RateLimiter` for password-change throttle; plain `throttle:5,1` alias already keys per authenticated user in this codebase. | consultant |
| 4 | `docs/carlton-tree.html` (previously untracked) is staged and committed with the phase commit, closing the FA-04-1 "local-only" caveat. | consultant |
| 5 | Pre-existing `SiteSettingTest` seeder-count failure (20->21 rows, `site` group added by commit 4126b7e) is fixed in Phase 1, as its own `test:`-prefixed commit immediately before the phase commit, test-file only. | consultant |
| 6 | Password change / token revocation intentionally leave no `activity_log` row; a dedicated auth-event audit entry is deferred to a later phase (flagged, not built). | consultant |

## Test counts

- Full suite: **966/966 passing**, 4537 assertions (`php artisan test`).
- New QA specs: `GuestLogoutTest` (12), `StaffProfileTest` (16), `StaffPasswordChangeTest` (14) - 42 total.
- Pre-existing repair: `SiteSettingTest::test_seeder_loads_the_real_site_copy` updated (count 20->21; groups now include `site`) - test-only, no seeder change.

## Deviations from PLAN.md

- Localization tasks 01-02-T3 / 01-03-T3 done by the engineer rather than a delegate (same keys/sentences across all five locales).
- CONVENTIONS.md empty-form template lines relabeled "Empty form ..." so a reader can't mistake the template for a Phase 1 statement.
- `config/activitylog.php` comment and `01-03-PLAN.md` corrected from "`activity_log.properties`" to "`activity_log.attribute_changes`" (the column spatie/laravel-activitylog v5 actually uses) - wording-only, no behavior change; the fix itself was already correct and verified by mutation.

## Flagged carry-forwards (not blockers)

- FA-01-1 (ACCESS-01) / FA-03-1 (ACCESS-03) edge-probe ledger rows - see decision 1.
- ACCESS-02 concurrency is a DB-unique-index backstop only: two concurrent writes of the same email produce a 500, not a 422 (accepted).
- Password-change/token-revocation audit gap - see decision 6.
- The contract's "authenticated rate limits use a named limiter" rule applies from the next phase on; `PUT /auth/password` keeps the plain alias (decision 3).

## Files touched

See `01-04-SUMMARY.md` "key-files" front-matter for the full list; highlights: `routes/api.php`, `AuthGuestService.php`, `AuthStaffService.php`, `GuestAuthController.php`, `StaffAuthController.php`, `BaseRequest.php`, `config/activitylog.php`, all five `lang/*/custom.php`, the three new `Http/Requests/Auth/*Request.php`, the three new `tests/Feature/Auth/*Test.php`, `docs/API_GUIDE_DASHBOARD.md`, `docs/API_GUIDE_MOBILE.md`, `docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html`, `.planning/codebase/CONVENTIONS.md`.
