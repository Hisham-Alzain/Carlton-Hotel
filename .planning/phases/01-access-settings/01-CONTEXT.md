# Phase 1: Access & Settings - Context

**Gathered:** 2026-09-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Guests can end their session with a real logout that revokes the Sanctum token, and staff can view/update their own profile and change their own password, all under the existing `/auth` route group. Four public routes (no `/api` prefix in docs): `POST /auth/guest/logout`, `GET /auth/profile`, `PUT /auth/profile`, `PUT /auth/password`. Requirements ACCESS-01..03 plus the cross-cutting DOCS-01 / XCUT-01 gates. No new tables, no new permissions, no new error codes. Anything about roles, activation, other users' accounts, phone/locale/avatar on staff, or OTP providers is out of scope.

</domain>

<decisions>
## Implementation Decisions

### Profile fields & email change (user-confirmed)
- **D-01:** Staff self-service profile edits `name` and `email` only. No migration: the `users` table stays `name, email, password, type, is_active`. `type`, `is_active`, roles and permissions remain admin-only via `PUT /staff/{staff}`. The dashboard language switch stays client-side; no `locale` column (Accept-Language controls only strings, per the API guides).
- **D-02:** Changing `email` requires `current_password` in the same `PUT /auth/profile` request, validated with Laravel's `current_password:users` rule. When the submitted email equals the stored one (or is omitted), `current_password` is not required. Email uniqueness uses `Rule::unique('users','email')->ignore($currentUserId)` exactly like `UpdateStaffRequest`.
- **D-03:** `GET /auth/profile` returns the same `StaffResource` that `GET /auth/me` returns (uuid, name, email, type, is_active, roles, effective/direct/role permissions). `PUT /auth/profile` returns the updated `StaffResource` with message key `custom.messages.profile_updated` (already exists).

### Password change policy (user-confirmed)
- **D-04:** `PUT /auth/password` body: `current_password`, `password`, `password_confirmation`. Rules: `current_password` → `required|string|current_password:users`; `password` → `required|string|min:8|confirmed|different:current_password`. `min:8` matches `CreateStaffRequest`; no stricter complexity rule.
- **D-05:** On success, delete every Sanctum token of the user except the current access token (revoke other devices, keep the current session). Response `data: null`, message key `custom.messages.password_changed` (new, en + ar).
- **D-06:** `PUT /auth/password` is throttled `throttle:5,1` (keyed per authenticated user). Rationale from the developer guide's throttle rule for secret-accepting endpoints (login/OTP): this endpoint verifies a secret and reveals whether it was right, so it is a brute-force vector against the highest-value accounts. `PUT /auth/profile` inherits the same throttle only for the email-change path is NOT required; keep profile un-throttled.
- **D-07:** Both writes run through the service (`AuthStaffService`), not the controller; password hashing relies on the `User` model's `hashed` cast. Activity logging stays on the existing `LogsActivity` trait; `password` must never appear in activity properties (it is already in `$hidden`; keep it excluded from `$logExcept`/`$logAttributes`).

### Guest logout scope (derived from repo docs, not re-asked)
- **D-08:** `POST /auth/guest/logout` lives inside the existing `auth:guests` block under `prefix('guest')` (tier-2: any guest token, no `has_booking`/`is_checked_in` gate, same tier as `POST /device-tokens`). It revokes the current access token only, mirroring `AuthStaffService::logout` including its null-guard fallback (delete all tokens when `currentAccessToken()` is null). Response `data: null`, message key `custom.auth.logged_out` (already exists). Source: `docs/carlton-tree.html` node "guest sign out" ("clears storage · token never revoked"), `API_GUIDE_WEBSITE.md` §session endpoints, `LOG_REPORT.md` P1 notes.
- **D-09:** Optional `device_token` (string, max 500) in the body. When present and a `device_tokens` row with that token belongs to the authenticated guest, delete the row so FCM pushes to that device stop; unknown tokens or tokens owned by another guest are ignored silently (still 200). Without `device_token`, device tokens are untouched, because `RegisterDeviceTokenAction` upserts by token and reassigns ownership on the next login anyway. Source: `API_GUIDE_MOBILE.md` §POST /api/device-tokens ("register on app launch/login whenever the stored token differs"), `LOG_REPORT.md` P9 (upsert-by-token, token reassignment fix), `device_tokens` migration (`token` unique, `guest_id` FK). This is the symmetric deregister-on-logout; it is derived, so the planner may keep it but must not widen it (no "delete all device tokens" variant).
- **D-10:** A second logout with an already-revoked token returns the guard's normal 401 `unauthenticated`; no idempotency special-casing.

### Error codes & responses (derived from repo docs, not re-asked)
- **D-11:** Wrong `current_password` on either endpoint → 422 `validation_failed` with `errors.current_password` carrying a localized message (new key `custom.validation.current_password`, en + ar: "The current password is incorrect." / Arabic equivalent). No new domain exception and no new `error_code`: the API guides define 422 `validation_failed` + field-path `errors` as the field-error contract that both clients already render under the field.
- **D-12:** Email already taken → 422 `validation_failed` with `errors.email`. Missing/expired token → 401 `unauthenticated`. Deactivated staff cannot reach these routes because `StaffService::deactivate` revokes tokens in the same transaction (LOG_REPORT P2), so no extra `is_active` check is added here.
- **D-13:** No new `error_code` values and no new permissions in this phase (routes are gated by the guard only). XCUT-01 is satisfied by stating "no new permissions" in the phase summary; DOCS-01 still applies.
- **D-14:** Route placement: `GET/PUT /auth/profile` and `PUT /auth/password` go inside the existing `Route::middleware('auth:users')` group under `Route::prefix('auth')` (routes/api.php ~lines 102-105); `POST /auth/guest/logout` inside the existing `auth:guests` group under `prefix('guest')` (~lines 112-115). Controllers: `StaffAuthController` gains `profile`, `updateProfile`, `changePassword`; `GuestAuthController` gains `logout`. Requests under `app/Http/Requests/Auth/` (`UpdateStaffProfileRequest`, `ChangePasswordRequest`, `GuestLogoutRequest`).

### Docs & tree (DOCS-01)
- **D-15:** Update `backend/docs/API_GUIDE_DASHBOARD.md` (Auth module: add `GET/PUT /auth/profile`, `PUT /auth/password` with request/response/failure tables in the existing format), `backend/docs/API_GUIDE_MOBILE.md` (Auth module: add `POST /auth/guest/logout` incl. the optional `device_token`), the Postman collection under `backend/docs/postman/`, and flip two nodes in `docs/carlton-tree.html`: "guest sign out" → `api:true`, `ep:["POST /auth/guest/logout"]`, meta "token revoked on sign out"; "dashboard settings" → `api:true`, `ep:["GET/PUT /auth/profile","PUT /auth/password"]`, meta "profile + password endpoints · language switch stays client-side".

### Claude's Discretion
- Whether `PUT /auth/password` returns `data: null` (default) or `{ "revoked_sessions": n }`.
- Exact new lang key names, as long as they sit in the existing `messages` / `validation` / `auth` groups of `lang/{en,ar}/custom.php` and both locales are filled.
- Keep the logic in `AuthStaffService` / `AuthGuestService` (both are small) rather than new Actions, unless the service would exceed the guide's ~300-line threshold.
- Test file naming, e.g. `tests/Feature/Auth/StaffProfileTest.php`, `StaffPasswordChangeTest.php`, `GuestLogoutTest.php`, following the existing `test_snake_case` PHPUnit style and the `withToken($this->staffToken(...))` helpers.
- Whether the throttle on `PUT /auth/password` keys by user id via `throttle:5,1` default resolution or an explicit named limiter; either is acceptable if the test proves the 6th call in a minute returns 429 `too_many_requests`.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Conventions (hard gate)
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` — layered architecture, envelope, exceptions, localization, PR checklist summary
- `backend/.claude/skills/tupcode-laravel-backend/references/developer-guide.md` — full guide; §envelope/error_code contract, §17 PR checklist, throttle rule for secret-accepting endpoints
- `.claude/skills/laravel-conventions/SKILL.md`, `.claude/skills/module-slice/SKILL.md`, `.claude/skills/test-discipline/SKILL.md`, `.claude/skills/naive-reviewer/SKILL.md` — project skills every implementer loads
- `.planning/codebase/CONVENTIONS.md`, `.planning/codebase/TESTING.md` — mapped conventions and test layout

### API contract (what the clients already expect)
- `backend/docs/API_GUIDE_DASHBOARD.md` — headers/locale, envelope, 422 `errors` keyed by field path, Auth module (`POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`) — new endpoints are documented in this format
- `backend/docs/API_GUIDE_MOBILE.md` — headers, `preferred_locale`, envelope, Auth module (OTP, `GET /auth/guest/me`, `PUT /auth/guest/profile`), §POST /api/device-tokens (token registration semantics behind D-09)
- `backend/docs/postman/` — collection to extend
- `docs/carlton-tree.html` — `var TREE` nodes "guest sign out" and "dashboard settings" to flip (D-15)

### Planning artifacts
- `.planning/REQUIREMENTS.md` — ACCESS-01, ACCESS-02, ACCESS-03, DOCS-01, XCUT-01
- `.planning/ROADMAP.md` — Phase 1 goal, success criteria, contract gate
- `.planning/research/PITFALLS.md` — API-contract breakage and permission-sprawl pitfalls
- `LOG_REPORT.md` (repo root, gitignored, local only) — P1 auth notes (`auth()->forgetGuards()` needed in logout tests), P2 (`deactivate` revokes tokens), P9 (device-token upsert-by-token)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Services/Auth/AuthStaffService.php` — `logout()` (current-token revoke with null-guard fallback) is the template for guest logout and for the "revoke others" loop; `me()` shows the roles/permissions load used by `StaffResource`
- `app/Services/Auth/AuthGuestService.php` + `app/Actions/Auth/UpdateGuestProfileAction.php` + `app/Http/Requests/Auth/UpdateGuestProfileRequest.php` — guest self-service pattern to mirror for staff profile
- `app/Http/Requests/Staff/UpdateStaffRequest.php` — `Rule::unique('users','email')->ignore($userId)` pattern; `app/Http/Requests/Staff/CreateStaffRequest.php` — password `min:8`
- `app/Http/Resources/StaffResource.php` — response shape for profile endpoints
- `app/Models/User.php` — `HasApiTokens`, `HasRoles`, `LogsActivity`, `hashed` password cast, `$fillable = ['name','email','password','type','is_active']`
- `app/Models/DeviceToken.php`, `app/Actions/Notification/RegisterDeviceTokenAction.php` — device-token ownership model for D-09
- `lang/en/custom.php`, `lang/ar/custom.php` — groups `messages` (has `profile_updated`), `auth` (has `logged_out`), `validation`, `errors`
- `tests/TestCase.php` (`withToken`, `staffToken`, fake disk), `tests/Feature/Auth/StaffLoginTest.php` (`test_logout_invalidates_token` with `auth()->forgetGuards()`), `tests/Feature/Auth/GuestProfileTest.php`

### Established Patterns
- Controllers only wire request → service → `success()/sendResponse()` with a lang key; services return `['data' => ..., 'code' => ...]`; no `request()` in services
- Field errors are 422 `validation_failed` with `errors` keyed by field path; clients branch on `error_code`
- Secret-accepting endpoints carry `throttle` middleware with a comment explaining the cap
- Feature tests per route: happy, 401, 403 (where a permission/guard mismatch applies), 422; factories only
- Every user-facing string exists in both `lang/en/custom.php` and `lang/ar/custom.php`

### Integration Points
- `routes/api.php` `Route::prefix('auth')` group (~lines 86-118): staff block `auth:users` (logout, me) and guest block `auth:guests` (me, profile)
- `app/Http/Controllers/Auth/StaffAuthController.php`, `app/Http/Controllers/Auth/GuestAuthController.php`
- Docs: `backend/docs/API_GUIDE_DASHBOARD.md` Auth module, `backend/docs/API_GUIDE_MOBILE.md` Auth module, Postman, `docs/carlton-tree.html`

</code_context>

<specifics>
## Specific Ideas

- Keep the staff self-service endpoints deliberately narrow (name + email) so Phase 1 stays a no-migration warm-up that validates the crew workflow before the heavier modules.
- Mirror existing shapes rather than inventing: profile = `/auth/me` resource; guest logout = staff logout behaviour; field errors = the 422 contract the clients already render.

</specifics>

<deferred>
## Deferred Ideas

- Staff `phone`, `locale` (server-side persistence of the dashboard language switch) and `avatar` on the profile — would need a migration and FileTrait wiring; revisit if the dashboard settings screen grows beyond name/email.
- Returning a `revoked_sessions` count from the password change (left to Claude's discretion; default is `data: null`).
- "Sign out everywhere" for guests (delete all tokens and all device tokens) — not requested by the app today.

</deferred>

---

*Phase: 01-access-settings*
*Context gathered: 2026-09-25*
