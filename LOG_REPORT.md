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
