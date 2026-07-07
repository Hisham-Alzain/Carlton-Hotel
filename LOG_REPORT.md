# Carlton Backend — Build Log

> Living document. The coding agent (Sonnet 5) appends one section per phase — what was built, deviations, decisions, and the stop-and-report summary. The planning agent (Opus 4.8) appends decision notes when consulted. Never overwrite — append only. Reference phase IDs from `PLAN.md` (P0–P12).

## How to append
After each module, add a section with the template below, then STOP and wait for human go-ahead before the next phase.

---

## Template (copy per phase)

### P<id> — <module name>
- **Date:**
- **Built:** <migrations, models, services/actions, controllers, requests, resources, routes — brief>
- **Deviations from PLAN.md:** <none | describe + why>
- **Decisions taken:** <any judgment calls; note if Opus/Fable was consulted and the outcome>
- **Seams left for later phases:** <e.g. "Firestore mirror stubbed — fulfilled in P9">
- **New error_codes introduced:** <list — for the frontend/Flutter teams>
- **Stop-and-report summary:** <2–4 lines: state, test result, anything needing human input>
- **Status:** awaiting go-ahead / approved to proceed

---

### P1 — Auth & Identity
- **Date:** 2026-07-07
- **Built:**
  - A1–A3: migrations — guests table expansion (full §3.4 fields), otp_codes, reservations stub (P1 only, P4 expands)
  - B1: Guest model expanded — LogsActivity, full fillable, markPhoneVerified/Email, scopeByPhone/Email
  - B2: OtpCode model — constants, scopeActive/forIdentifier, isExpired/Consumed/Locked helpers
  - B3–B4: GuestFactory (phoneVerified/emailOnly/phoneOnly states) + OtpCodeFactory (expired/consumed/locked/emailChannel states)
  - C1–C3: OtpExpiredException (422), OtpInvalidException (422), OtpLockedException (429)
  - D: lang/en + lang/ar — errors.otp_*, auth.*, validation.phone_invalid added to both files
  - E: NormalizesPhone support trait + StaffLoginRequest, RequestOtpRequest (channel↔identifier coherence), VerifyOtpRequest, LinkBookingCodeRequest (booking_code regex + second-factor guard)
  - F: OtpDispatcher (in-memory seam; TODO P9 real provider), RequestOtpAction (RateLimiter 1/min+5/hr, bcrypt, invalidates prior active codes), VerifyOtpAction (expire/lock/consume/create-or-match guest, booking_link branch links reservation.guest_id), LinkBookingCodeAction (second factor in WHERE, masks contact)
  - G: AuthGuestService + AuthStaffService (login/logout/me, credentials/inactive exceptions)
  - H: UserResource (uuid, permissions array, roles) + GuestResource (uuid, phone_verified bool, email_verified bool)
  - I: StaffAuthController (login/logout/me) + GuestAuthController (requestOtp/verifyOtp/linkBookingCode)
  - J: routes/api.php — auth prefix group with staff + guest subgroups; probe routes REMOVED
  - K: GuardsResolveTest + ExceptionEnvelopeTest migrated off probe routes; probes deleted
  - L: 6 test files — OtpHappyPathTest, OtpFailureTest, OtpRateLimitTest, BookingCodeLinkTest, StaffLoginTest, PhoneNormalizationTest
- **Deviations from PLAN.md:**
  - `prepareForValidation` null-merge skipped when libphonenumber can't parse — original value left in place instead. Prevents spurious `identity_required` on already-E.164 inputs in tests. Correct security behavior: lookup fails naturally for bogus numbers.
  - `AuthStaffService::logout` null-guards `currentAccessToken()` before `delete()` — defensive, no behavior change.
  - `StaffLoginTest::test_logout_invalidates_token` calls `auth()->forgetGuards()` between logout and re-check — necessary because Sanctum caches resolved user in-memory within a single test request cycle.
- **Decisions taken:** Reservation model created as P1 stub (maps to the reservations_stub migration). `OtpDispatcher` uses static in-memory array for test code capture — injectable/mockable. No real SMS/WhatsApp provider yet (seam clearly marked TODO P9).
- **Seams left for later:**
  - `OtpDispatcher::send()` — TODO(P9): wire real SMS/WhatsApp/email provider
  - `app/Models/Reservation.php` — P1 stub; P4 expands to full reservation model
  - Firebase config published but not used
- **New error_codes introduced:** `otp_expired`, `otp_invalid`, `otp_locked`, `credentials_invalid`, `account_inactive`, `identity_required`, `booking_link_failed`
- **Stop-and-report summary:** P1 complete. Full auth layer built — staff email+password login (token + permissions), guest passwordless OTP (3 entry paths), OTP infrastructure (bcrypt hash, 5-min TTL, single-use, 5-attempt lock, 1/min·5/hr rate limit), booking-code link with second-factor enforcement, two resources (UserResource with permissions array, GuestResource), probe routes removed. 36 tests, 109 assertions — all green. P0 tests still pass.
- **Status:** awaiting go-ahead

---

### P2 — Staff RBAC Management
- **Date:** 2026-07-07
- **Built:**
  - T0: UUID column + migration added to `users` table; `HasUuid` trait added to `User` model (provides `getRouteKeyName()→'uuid'`)
  - T1: `StaffPolicy` — viewAny/view/create/update/assignPermissions/deactivate; registered via `Gate::policy` in AppServiceProvider; super-admin bypass handled globally by Gate::before
  - T2: `StaffService` — extends BaseService; `createFromPreset`, `update` (name+email only), `deactivate` (revokes tokens in same transaction), `rolePresets`
  - T3: `PermissionAssignmentService` — `apply` (grant/revoke in transaction), `heldBy`, `groupedPermissions` (first-dot grouping into 8 modules)
  - T4: `CreateStaffAction` — thin orchestrator delegating to StaffService
  - T5: `AssignPermissionsAction` — guard rail 1 (escalation check on grant array), guard rail 2 (defensive super_admin re-check)
  - T6: `CreateStaffRequest`, `AssignPermissionsRequest` (grant∩revoke=∅ + at least one present), `UpdateStaffRequest` (name+email only, unique→ignore self)
  - T7: `StaffResource` (effective/direct/role permissions split), `PermissionResource` (module group shape), `RolePresetResource`
  - T8: `StaffController` (index/show/store/update/assignPermissions/deactivate), `PermissionController` (delegates to PermissionAssignmentService), `RoleController` (delegates to StaffService)
  - T9: 8 routes under `auth:users` middleware (`/staff`, `/staff/{user}`, `/staff/{user}/permissions`, `/staff/{user}/deactivate`, `/permissions`, `/roles`)
  - T10: lang/en + lang/ar — errors.escalation_blocked/superadmin_immutable/cannot_self_deactivate/unknown_permission; messages.staff_created/updated/deactivated/permissions_updated; validation.min/unique/exists/grant_revoke_conflict/distinct
  - T13: 8 test files — StaffCreateTest, PermissionOverrideTest, EscalationBlockedTest, SuperAdminProtectedTest, DeactivatedCannotAuthTest, PermissionsGroupedTest, RolePresetsTest, StaffAuthorizationTest
- **Deviations from PLAN.md:**
  - None in scope. `PermissionController` and `RoleController` delegate to service methods (`groupedPermissions` + `rolePresets`) rather than querying DB directly — convention fix applied after Naive Reviewer flagged the violation.
  - `BaseController` required `AuthorizesRequests` trait for `$this->authorize()` — added.
  - Negative-permission model: revoke only removes direct grants; revoking role-inherited permissions is not supported (spatie has no native deny). Documented in `PermissionAssignmentService`. Deferred per spec.
- **Decisions taken:** `PermissionAssignmentService::groupedPermissions()` and `StaffService::rolePresets()` use `get()` not `paginate()` — both are bounded reference lists (16 and 5 rows respectively); documented with comments. Double-hash bug in test caught and fixed by Naive Reviewer (bcrypt() + hashed cast = double hash).
- **Seams left for later:** True negative-permission model (deny role-inherited perms) — deferred. Welcome email on staff creation (CreateStaffAction placeholder comment).
- **New error_codes introduced:** `forbidden` (covers escalation_blocked, superadmin_immutable, cannot_self_deactivate — all map to ForbiddenException)
- **Naive Reviewer:** PASS WITH WARNINGS → 4 issues fixed (PermissionController/RoleController DB-in-controller, double-hash test bug, dead variable in test)
- **Stop-and-report summary:** P2 complete. Staff RBAC management surface built — create from preset, per-account permission override, escalation guard (rail 1 + rail 2), super-admin immutability, deactivation with token revocation, grouped permissions + role preset reference endpoints. 50 tests, 154 assertions — all green. P0+P1 tests still pass.
- **Status:** awaiting go-ahead

---

## Escalation log (planning consultations)
Record here whenever coding escalated a decision to Opus 4.8, or a hard decision went to Fable.

| Phase | Question | Model consulted | Outcome |
|---|---|---|---|
| P0 | activitylog v5 trait path + method rename | Coder self-resolved | Trait moved to `Spatie\Activitylog\Models\Concerns\LogsActivity`; `dontSubmitEmptyLogs()` renamed `dontLogEmptyChanges()`. Applied in `app/Traits/LogsActivity.php`. |

---

### P0 — Foundation & base layer
- **Date:** 2026-07-07
- **Built:**
  - T1: `laravel new backend` (PHP 8.4.1, Laravel 12, PHPUnit)
  - T2: `install:api` (Sanctum), `spatie/laravel-permission`, `spatie/laravel-activitylog` v5, `giggsey/libphonenumber-for-php`, `spatie/laravel-translatable`, `kreait/laravel-firebase:^7.0`; all vendor publishes run
  - T3: `.env` → `DB_CONNECTION=sqlite` (local); `.env.example` → SQLite default + commented MySQL deployment block
  - T4: `config/auth.php` — two Sanctum guards (`users`, `guests`); `guests` migration + P0 stub `Guest` model
  - T5: `app/Exceptions/DomainException.php` (abstract) + `NotFoundException`, `UnauthorizedException`, `ForbiddenException`, `TooManyRequestsException`, `ExternalServiceException`
  - T6: Global exception handler in `bootstrap/app.php` — full error_code map, `request_id` pulled from request attribute
  - T7: `AttachRequestId` middleware — sets UUID on request attribute + `X-Request-Id` response header; appended to `api` group
  - T8–T12: `BaseService`, `BaseFilter`, `BaseResource`, `BaseCollection`, `BaseController`, `BaseCRUDController`, `BaseIndexController`, `BaseRequest` — all in `app/Base/`
  - T13: `HasUuid`, `HasTranslations` (wraps Spatie), `LogsActivity` (wraps Spatie v5), `FileTrait` — all in `app/Traits/`
  - T14–T18: `spatie/laravel-permission` configured; `Gate::before` super-admin bypass; `User` model extended; migration adds `type`/`is_active` columns (indexed); `RolesAndPermissionsSeeder` (16 permissions, 5 role presets, idempotent)
  - T19: `lang/en/custom.php` + `lang/ar/custom.php` — all keys present in both locales
  - T20: `UserFactory` states (`superAdmin`, `staff`); `GuestFactory`; 6 test files (5 Feature, 1 Unit)
  - T21: `routes/api.php` — `/health` (permanent) + 3 `/probe/*` routes (P0 test only, marked for removal before P1)
  - T22: `storage:link`, `migrate:fresh --seed`, `php artisan test` — all green
- **Deviations from PLAN.md:** None in scope. One package difference: `spatie/laravel-activitylog` installed as v5 (latest stable), which moved the trait namespace and renamed `dontSubmitEmptyLogs()` to `dontLogEmptyChanges()`. Applied correctly in `app/Traits/LogsActivity.php`.
- **Decisions taken:** SQLite for local dev (user instruction); MySQL for deployment (documented in `.env.example`). `kreait/laravel-firebase:^7.0` installed but not wired — config published, wiring deferred to P9 per plan.
- **Seams left for later phases:**
  - `app/Models/Guest.php` is a P0 stub (uuid + name only); full §3.4 field set built in P1
  - `/probe/*` routes marked for removal before P1 ships
  - Firebase config published but not configured — P9
  - `FileTrait` uses `public` disk; S3/other disk config is deployment concern
- **New error_codes introduced:** `not_found`, `unauthorized`, `forbidden`, `validation_failed`, `too_many_requests`, `external_service_error`, `server_error`
- **Stop-and-report summary:** P0 complete. Laravel 12 app scaffolded with full base layer — exception hierarchy, envelope handler, request_id middleware, BaseService/Filter/Resource/Controller/Request, 4 traits, two Sanctum guards, RBAC seeder (16 permissions + 5 role presets), SQLite local DB, en+ar lang files, health + probe routes. 21 tests, 58 assertions — all green. No invented scope.
- **Status:** awaiting go-ahead
