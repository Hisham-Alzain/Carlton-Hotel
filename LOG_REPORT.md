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

### P2.5 — Remediation & flow alignment
- **Date:** 2026-07-08
- **Built:**
  - R1.1: `BookingLinkUnavailableException` (`errorCode: booking_link_unavailable`, `statusCode: 422`)
  - R1.2: `VerifyOtpAction` booking_link branch replaced with guarded throw — rolls back consumed_at and guest create/verify; P4.R forward-reference comment added
  - R1.3: `errors.booking_link_unavailable` added to both `lang/en/custom.php` and `lang/ar/custom.php`
  - R2.1: `OtpHourlyCapTest` — 5/hr cap verified for `login` purpose (via HTTP) and `booking_verification` purpose (via action-level call, since RequestOtpRequest only allows login|register at HTTP layer)
  - R3.1: `BookingCodeLinkTest::test_booking_link_verify_purpose_now_throws_unavailable` — asserts 422 + error_code + OTP not consumed (transaction rolled back)
  - R3.2: `BookingCodeLinkE2ETest` created — `@group p4`, single `markTestSkipped('Will be completed in P4.R')`
  - R4.1: `SuperAdminProtectedTest` — two new methods: `test_super_admin_can_assign_permissions` and `test_super_admin_can_deactivate_staff` (both → 200)
  - R5.1: `OtpCode::PURPOSE_BOOKING_VERIFICATION = 'booking_verification'` added; `otp_codes.purpose` confirmed as unconstrained string column — no migration needed
- **Deviations from PLAN.md:** None. All 5 remediation tickets executed as specified.
- **Decisions taken:**
  - R2 Method 2 (booking_verification hourly cap): RequestOtpRequest validates purpose as `in:login,register`, so HTTP endpoint would 422. Test calls `RequestOtpAction::handle()` directly (action-level). This keeps P1 request validation unchanged and correctly exercises the limiter logic — behaviour identical to what P4 will use.
  - R5: confirmed `otp_codes.purpose` is a plain `string` column — no migration required.
- **Seams left for later:**
  - `BookingCodeLinkE2ETest` — skipped, fulfilled in P4.R
  - Real booking-code → guest linking built in P4.R (real Reservation model + `pending_verification`)
- **New error_codes introduced:** `booking_link_unavailable`
- **Naive Reviewer:** Not spawned — remediation phase with no new features; convention compliance verified by self-check.
- **Stop-and-report summary:** P2.5 complete. booking_link branch neutralized (guarded no-op, rolls back transaction); OTP 5/hr cap now tested for both login and booking_verification purposes; RBAC super_admin coverage extended (assignPermissions + deactivate → 200); PURPOSE_BOOKING_VERIFICATION constant added. 55 tests passed, 1 skipped (BookingCodeLinkE2ETest, intentional), 167 assertions. migrate:fresh --seed green, no new migrations. API docs backfilled (backend/docs/API_GUIDE_WEB_DASHBOARD.md + API_GUIDE_MOBILE.md).
- **Status:** awaiting go-ahead

---

### P2.5 — v3 re-sync verification (2026-07-08)
- Re-read ARCHITECTURE.md v3 and PLAN.md revision 3 in full.
- Verified all P2.5 done-condition items against the v3 wording — all satisfied, no code changes needed.
- API docs restructured to match the three-surface model in ARCHITECTURE §3.6:
  - Deleted `backend/docs/API_GUIDE_WEB_DASHBOARD.md` (wrong split).
  - Created `backend/docs/API_GUIDE_WEBSITE.md` — anonymous-only; health now; P3/P4/P6/P11 content noted; OTP framed as transaction verification not login.
  - Created `backend/docs/API_GUIDE_DASHBOARD.md` — staff auth + staff RBAC management (P0–P2 backfill).
  - Updated `backend/docs/API_GUIDE_MOBILE.md` — corrected verify-otp request body (phone/email not identifier); added missing purpose field to request-otp; added access tiers table.
- Note: `backend-developer-guide.md` does not exist in the repo (confirmed by search). Proceeding on established conventions from ARCHITECTURE.md and base layer.

---

### P3 — CMS / Content
- **Date:** 2026-07-09
- **Built:**
  - M0: `create_media_table` migration — polymorphic `mediable` morph columns + `sort_order`, `disk`, `path`, `file_name`, `mime_type`, `size`
  - M1–M7: migrations for `room_types`, `rooms`, `facilities`, `dining_venues`, `event_spaces`, `pages`, `promotions` — all with `uuid`, `is_active`, `sort_order`, bilingual JSON columns, FK indexes
  - Models: `RoomType`, `Room`, `Facility`, `DiningVenue`, `EventSpace`, `Page`, `Promotion` — all use `HasUuid`, `HasTranslations`, `LogsActivity`, `HasFactory`; `images()` morphMany on all except Page; `Media` uses `HasUuid`, `FileTrait`, `LogsActivity`
  - Services: 7 services extending `BaseService` — each exposes `indexPublic()` filtering `is_active=true`; `RoomService` overrides `store`/`update` to resolve `room_type_uuid` → integer FK before delegating to parent
  - `MediaService` — `attach()` (validates mime/size, stores file, writes `Media` row in transaction) and `destroy()` (deletes file + DB row in transaction)
  - Admin controllers (8): `RoomTypeController`, `RoomController`, `FacilityController`, `DiningVenueController`, `EventSpaceController`, `PageController`, `PromotionController`, `MediaController` — all call `paginatedSuccess()` or `respondFromService()`; permission middleware `cms.edit` applied at route group level
  - Public controllers (7): one per content type; `indexPublic()` + `showPublic()` (inactive → 404 via `NotFoundException`)
  - Requests (11): `Create*` / `Update*` for each type; `UploadMediaRequest` (mime + max:5120); `CreateRoomRequest` uses `room_type_uuid` (exists:room_types,uuid) — integer ID never exposed
  - Resources (8): `RoomTypeResource`, `RoomResource`, `FacilityResource`, `DiningVenueResource`, `EventSpaceResource`, `PageResource`, `PromotionResource`, `MediaResource` — all expose `uuid`, never `id`; images eager-loaded via `$with`
  - Factories (7): one per content model using `HasFactory`
  - Middleware: `SetLocale` — reads `Accept-Language` header, sets app locale; appended to `api` group in `bootstrap/app.php`
  - Infrastructure: `paginatedSuccess()` helper added to `BaseController`; Spatie permission middleware aliases registered in `bootstrap/app.php`; lang/en + lang/ar extended with `image_uploaded`, `image_deleted` keys
  - Routes: 60+ routes across `/api/cms/*` (admin, gated) and `/api/public/*` (anonymous)
- **Deviations from PLAN.md:** None in scope.
- **Decisions taken:**
  - `room_type_uuid` in request surface (not `room_type_id`) — convention compliance; service resolves to FK internally
  - `Media` included in `LogsActivity` — image upload/delete are auditable admin actions matching state-change convention
  - `Page` has no `images()` relation — spec has no image attachment for pages; not added
  - `paginatedSuccess()` added to `BaseController` (not scope creep — required by the resource-wrapped pagination pattern established in P0)
- **Seams left for later:** None from this phase. `Room.status` field (`available/occupied/maintenance`) will be updated by P4 reservation flow.
- **New error_codes introduced:** None (all content 404s map to existing `not_found`; image errors use existing `validation_failed`)
- **Naive Reviewer:** PASS WITH WARNINGS → 2 warnings fixed before commit:
  1. `CreateRoomRequest` / `UpdateRoomRequest`: `room_type_id` (integer) replaced with `room_type_uuid` (UUID, `exists:room_types,uuid`) — internal PK was leaking into API surface
  2. `Media` model: `LogsActivity` trait added — state-changing model was the only one in the phase missing it
- **Stop-and-report summary:** P3 complete. Full CMS content layer built — 7 content types with bilingual CRUD, polymorphic image management, public endpoints filtering inactive records, locale switching via `Accept-Language`. 48 tests, 107 assertions — all green. Full suite (P0–P3): 103 tests, all green.
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
