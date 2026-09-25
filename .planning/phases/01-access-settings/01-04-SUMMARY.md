---
phase: 01-access-settings
plan: 04
subsystem: auth
tags: [laravel, sanctum, docs, postman, rbac, activitylog]

requires:
  - phase: 01-access-settings (01-01, 01-02, 01-03)
    provides: guest logout, staff profile read/update, staff password change
provides:
  - API guide sections for the four Phase 1 routes (dashboard + mobile)
  - Postman requests for the four routes in folder "02 - Auth"
  - carlton-tree.html nodes "guest sign out" and "dashboard settings" flipped to api:true
  - Phase Summary Contract (DOCS-01 / XCUT-01) in .planning/codebase/CONVENTIONS.md
affects: [every later phase-closing SUMMARY, dashboard settings screen, Flutter sign-out flow, audit tooling]

tech-stack:
  added: []
  patterns:
    - "Phase-closing SUMMARY carries Permissions / Dashboard & App Path Changes / Docs Updated, never omitted"
    - "Secrets excluded from the audit trail globally via activitylog.default_except_attributes, not per model"

key-files:
  created:
    - backend/app/Http/Requests/Auth/GuestLogoutRequest.php
    - backend/app/Http/Requests/Auth/UpdateStaffProfileRequest.php
    - backend/app/Http/Requests/Auth/ChangePasswordRequest.php
    - .planning/phases/01-access-settings/01-04-SUMMARY.md
  modified:
    - backend/routes/api.php
    - backend/app/Services/Auth/AuthGuestService.php
    - backend/app/Services/Auth/AuthStaffService.php
    - backend/app/Http/Controllers/Auth/GuestAuthController.php
    - backend/app/Http/Controllers/Auth/StaffAuthController.php
    - backend/app/Base/BaseRequest.php
    - backend/config/activitylog.php
    - backend/lang/{en,ar,fr,tr,es}/custom.php
    - backend/docs/API_GUIDE_DASHBOARD.md
    - backend/docs/API_GUIDE_MOBILE.md
    - backend/docs/postman/carlton-api.postman_collection.json
    - docs/carlton-tree.html
    - .planning/codebase/CONVENTIONS.md

key-decisions:
  - "PUT /auth/password returns data:null (plan-q2)"
  - "PUT /auth/password uses the plain throttle:5,1 alias; the framework keys it per authenticated user, so no named limiter was added (plan-q3)"
  - "docs/carlton-tree.html is added to the repo with the phase commit (plan-q4)"

patterns-established:
  - "Phase Summary Contract: see .planning/codebase/CONVENTIONS.md § Phase Summary Contract (DOCS-01 / XCUT-01)"

requirements-completed: [DOCS-01, XCUT-01]

duration: n/a
completed: 2026-09-25
status: complete
---

# Phase 1 Plan 04: Docs and contract gate summary

**The four self-service auth routes are documented in both guides, Postman and the feature tree. The phase adds zero permissions, and every later phase now closes with the same three contract sections.**

## Permissions (XCUT-01)

None — no new permissions this phase.

## Dashboard & App Path Changes (DOCS-01)

| Client (dashboard / app) | Method | Path | Change (added / changed / removed) | Notes |
|---|---|---|---|---|
| app | POST | `/auth/guest/logout` | added | `auth:guests`, unthrottled; optional `device_token` (string, max 500) deregisters that device when this guest owns it; `data: null` |
| dashboard | GET | `/auth/profile` | added | `auth:users`, unthrottled; same `UserResource` shape as `GET /auth/me` |
| dashboard | PUT | `/auth/password` | added | `auth:users`, `throttle:5,1` keyed per account; revokes every other token of the account, keeps the current one; `data: null` |
| dashboard | PUT | `/auth/profile` | added | `auth:users`, unthrottled; `name` and/or `email`; `current_password` required only when email changes |

No existing path, field or error_code changed; 401 responses keep error_code unauthorized.

## Docs Updated (DOCS-01)

- [x] `backend/docs/API_GUIDE_DASHBOARD.md`: added `### GET /api/auth/profile`, `### PUT /api/auth/profile` and `### PUT /api/auth/password` after `### GET /api/auth/me`. The login section's false claim of no throttle was corrected to 10 req/min per IP with `too_many_requests`.
- [x] `backend/docs/API_GUIDE_MOBILE.md`: added `### POST /api/auth/guest/logout` after `### PUT /api/auth/guest/profile`. Also added its Endpoint index row, and the index total now reads 59 in total.
- [x] Postman `02 - Auth` (now 18 items): `Profile (staff) — Get`, `Profile (staff) — Update` and `Change Password (staff)` sit after `Me (staff profile + permissions)`, and `[Guest] Logout` is the last item.
- [x] `docs/carlton-tree.html`: nodes "guest sign out" and "dashboard settings" are now `api:true` with the D-15 `ep`/`meta` values, and their stale notes are removed. Totals are 94 nodes and 68 `api:true`, and the sha1 of the untouched nodes matches baseline `b71ef1ee2200ae19c50b1d07db4cd50ba427e9a6`. The file was untracked before this phase and is added to the repo with the phase commit, which closes the FA-04-1 "local-only" caveat.
- [x] `.planning/codebase/CONVENTIONS.md`: new `## Phase Summary Contract (DOCS-01 / XCUT-01)` section added after `## Localization`.

## Audit-trail change (from 01-03)

Spatie `LogsActivity` ignores `$hidden`, so it was copying the bcrypt hash of `password` into `activity_log.attribute_changes` on every staff create and password change (spatie/laravel-activitylog v5 records model changes in `attribute_changes`; `properties` only holds custom data). `backend/config/activitylog.php` now sets `default_except_attributes => ['password', 'remember_token']`. After this change the audit trail no longer records password or remember-token changes on any model, including rows written by the existing staff-creation flow. Audit tooling that looked for `password` in `attribute_changes.attributes` or `attribute_changes.old` will find nothing. The trait uses `logOnlyDirty()->dontLogEmptyChanges()`, so a password-only update (`PUT /auth/password`) now writes no activity row at all — the "no hash in activity_log" behaviour holds because no row is written, not because a row is filtered. The audit trail does not record that a password changed (flagged below).

## Phase gate

- Route contract (`route:list --path=auth --json`): the check printed `routes ok`. All four routes sit on the right guards, `/auth/password` carries `ThrottleRequests:5,1`, `/auth/profile` has no throttle, and none carries Permission middleware.
- `RolesAndPermissionsSeeder.php`: no uncommitted diff, and no `(01-` commit touches it.
- Docs verifications (01-04 Task 1 and Task 2 automated checks) all pass: `postman ok` and `tree ok`.
- Full suite: **966/966 green** (`php artisan test`, 4537 assertions). This includes the 42 new QA tests (`GuestLogoutTest` 12, `StaffProfileTest` 16, `StaffPasswordChangeTest` 14) and the pre-existing-repair fix below.
- Pre-existing repair (out of scope, test-only): `Tests\Feature\Cms\SiteSettingTest::test_seeder_loads_the_real_site_copy` asserted 20 settings across groups `['booking','contact','footer','hero','seo','social']`; commit 4126b7e (2026-08-10) added `site.is_coming_soon` under a new `site` group, making the real count 21 across `['booking','contact','footer','hero','seo','site','social']` (alphabetical, matching `orderBy('group')`). Both assertions were updated in `backend/tests/Feature/Cms/SiteSettingTest.php`; no production code (`CmsContentSeeder`) touched. Committed as its own `test:` commit immediately before the phase commit, per the recorded decision.
- The QA feature specs `GuestLogoutTest`, `StaffProfileTest` and `StaffPasswordChangeTest` are owned by QA; they are part of the 966/966 total above.

## Flagged carry-forwards

- Password change and token revocation currently leave no `activity_log` row; the only trace is the reduced `personal_access_tokens` count. A dedicated auth-event audit entry (e.g. `activity()->performedOn($user)->event('password_changed')->log(...)`) is a candidate for a later phase; not added here (decision recorded, no code change).
- FA-01-1 (ACCESS-01) and FA-03-1 (ACCESS-03) edge-probe rows remain category `unclassified` / status `unresolved` in the Edge-Probe Coverage Ledger (01-04-PLAN.md lines 271 and 276). Their behaviours were manually mapped to concrete truths and are covered by GuestLogoutTest (idempotent second logout → 401, sibling tokens survive, ownership cases) and StaffPasswordChangeTest (current token survives/others revoked, throttle per account, min-8/equal-to-current/confirmation boundaries). The probe rows are not closed by this phase; the verifier decides.
- ACCESS-02 concurrency is a backstop only: two concurrent writes of the same email hit the DB unique index and return a 500, not a 422 (T-01-09, accepted).
- The contract's rule that authenticated rate limits use a named limiter applies from the next phase on. `PUT /auth/password` keeps the plain `throttle:5,1` because it already keys on user id here (plan-q3).

## Deviations

- The localization tasks 01-02-T3 and 01-03-T3 were done by the engineer rather than a delegate, with the same keys and sentences in all five locales.
- In the CONVENTIONS contract, the empty-form lines are now labelled "Empty form …", so a reader can't mistake the template for a statement about Phase 1.
