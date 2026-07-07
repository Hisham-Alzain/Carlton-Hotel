# P0 — Coder Ticket List (Foundation & Base Layer)

> **Executor:** Coder agent (Sonnet). **Source of truth:** `PLAN.md` P0 + `ARCHITECTURE.md` §3, §4, §7, §9.
> **App root:** `D:\TupCode\Carlton\backend\` (created in Ticket 1). All file paths below are **relative to `backend/`**.
> **Golden rule:** Implement exactly these tickets, in order. No business logic, no invented scope. Every layer obeys the conventions in §9 (envelope, `['data','code']` returns, thrown exceptions, `DB::transaction`, `$with`, pagination, `__('custom.*')`, indexes, UTC).
> **PHP 8.4.1 / Composer 2.8.9.** Pin Laravel to latest stable (12.x). Use **PHP 8 constructor promotion, typed properties, and return types** on every class.

---

## Envelope contract (the single most-referenced spec — implement in the base layer, Tickets 8–11)

- **Success:** `{ "success": true, "message": string, "data": mixed, "request_id": string }`
- **Paginated:** `data: { "items": [...], "meta": { current_page, per_page, total, last_page } }`
- **Error:** `{ "success": false, "message": string, "error_code": string, "context": object|null, "request_id": string }`
- **Validation error:** error envelope with `"error_code": "validation_failed"` **plus** `"errors": { field: [messages] }`.
- Controllers/services **never** hand-build these. The base controller + global handler shape them. `request_id` is attached by middleware (Ticket 7) to **every** response, success or error.

---

# P0.1 — Project init

## Ticket 1 — Scaffold Laravel app
**Run (from `D:\TupCode\Carlton\`):**
```
laravel new backend
```
- Accept defaults; no starter kit (no Breeze/Jetstream). Pest **or** PHPUnit — this plan uses **PHPUnit** (Laravel 12 default). If `laravel new` offers a test choice, pick PHPUnit.
- Confirm app boots: `php artisan --version`.

**Files to verify exist (created by installer, do not author):** `backend/artisan`, `backend/composer.json`, `backend/.env`, `backend/config/`, `backend/routes/api.php` (if missing in L12, create via `php artisan install:api` — see Ticket 2).

**Note:** Laravel 12 has **no `app/Http/Kernel.php`** and **no `app/Exceptions/Handler.php`**. Middleware and exceptions are registered in **`bootstrap/app.php`**. All "register X" instructions below mean edit `bootstrap/app.php`.

---

## Ticket 2 — Install packages & API scaffolding
**Run (in `backend/`):**
```
php artisan install:api
composer require spatie/laravel-permission spatie/laravel-activitylog giggsey/libphonenumber-for-php spatie/laravel-translatable kreait/laravel-firebase
```
- `install:api` publishes Sanctum + creates `routes/api.php` + the `personal_access_tokens` migration.
- Do **not** run `composer require laravel/sanctum` separately — `install:api` handles it.

**Vendor publishes (run in this order):**
```
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider" --tag="config"
```

**Files touched:** `config/permission.php`, `config/activitylog.php`, `config/firebase.php`, `config/sanctum.php`, new migrations under `database/migrations/`.

---

## Ticket 3 — Configure `.env` and `config/app.php`
**Local dev uses SQLite. MySQL is for deployment only.**

**Edit `backend/.env`:**
```
APP_NAME="Carlton"
APP_LOCALE=en
APP_FALLBACK_LOCALE=ar
APP_TIMEZONE=UTC
DB_CONNECTION=sqlite
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD — omit or leave commented for local
```
- Laravel 12 defaults `DB_DATABASE` to `database/database.sqlite` when `DB_CONNECTION=sqlite`. The file is auto-created on first migrate. No server needed locally.
- **`.env.example`** — set `DB_CONNECTION=sqlite` as the default, add a commented block showing the MySQL vars for deployment:
  ```
  # DB_CONNECTION=mysql
  # DB_HOST=127.0.0.1
  # DB_PORT=3306
  # DB_DATABASE=carlton
  # DB_USERNAME=root
  # DB_PASSWORD=
  ```
- Confirm `config/app.php` reads `'locale' => env('APP_LOCALE','en')`, `'fallback_locale' => env('APP_FALLBACK_LOCALE','ar')` (Laravel 12 default already does).
- **Timezone:** `APP_TIMEZONE=UTC` (default). All timestamps UTC per §9.
- **No MySQL setup needed locally** — `php artisan migrate` will create the SQLite file automatically.

---

## Ticket 4 — Two Sanctum guards (`users` + `guests`)
**Edit `config/auth.php`:**
- `guards.users` → driver `sanctum`, provider `users`.
- `guards.guests` → driver `sanctum`, provider `guests`.
- `providers.users` → eloquent, model `App\Models\User::class`.
- `providers.guests` → eloquent, model `App\Models\Guest::class`.
- Set `defaults.guard` to `users`.

**Note for Coder:** `App\Models\Guest` does **not** exist in P0 — it is built in P1. For P0, create a **minimal stub** so the guard resolves and the guest probe route (Ticket 21) works:
- **File:** `database/migrations/xxxx_create_guests_table.php` — columns: `id`, `uuid` (unique), `name` nullable, timestamps. (Full field set per §3.4 is P1; P0 only needs a bindable table + token subject.)
- **File:** `app/Models/Guest.php` — extends `Authenticatable`, uses `HasApiTokens`, `HasUuid` (Ticket 12). This is a **P0 stub**; P1 expands it. Mark with a `// P0 stub — expanded in P1` comment.

`App\Models\User` already exists from the installer; extend it in Ticket 16.

---

# P0.2 — Base layer

> Namespace root for base classes: `App\Base\...` unless noted. Keep the base layer framework-only — **zero domain knowledge**.

## Ticket 5 — Domain exception hierarchy
**Files:**
- `app/Exceptions/DomainException.php` — abstract base `extends \Exception`. Contract:
  - `abstract public function errorCode(): string;`
  - `abstract public function statusCode(): int;`
  - `public function context(): array { return []; }` (overridable)
  - Constructor accepts `(string $message = '', array $context = [])`; stores context.
- `app/Exceptions/TooManyRequestsException.php` — `errorCode() => 'too_many_requests'`, `statusCode() => 429`.
- `app/Exceptions/ExternalServiceException.php` — `errorCode() => 'external_service_error'`, `statusCode() => 502`.
- `app/Exceptions/NotFoundException.php` — `errorCode() => 'not_found'`, `statusCode() => 404`. (Base handler also maps Laravel's `ModelNotFoundException`/`NotFoundHttpException` to this code.)
- `app/Exceptions/UnauthorizedException.php` — `errorCode() => 'unauthorized'`, `statusCode() => 401`.
- `app/Exceptions/ForbiddenException.php` — `errorCode() => 'forbidden'`, `statusCode() => 403`.

**Note:** these are the **shared/base** set only. Module-specific exceptions (OTP, reservation, etc.) come in later phases and will `extend DomainException`.

---

## Ticket 6 — Global exception handler (envelope mapping)
**File:** edit `bootstrap/app.php` — inside `->withExceptions(function (Exceptions $exceptions) { ... })`.
- Register a renderable/`->render()` closure that, **for `wantsJson()` / API requests**, maps exceptions to the error envelope:
  | Exception | error_code | HTTP |
  |---|---|---|
  | `DomainException` (any subclass) | `$e->errorCode()` | `$e->statusCode()` |
  | `Illuminate\Validation\ValidationException` | `validation_failed` | 422 (include `errors`) |
  | `Illuminate\Auth\AuthenticationException` | `unauthorized` | 401 |
  | `Illuminate\Auth\Access\AuthorizationException` / `AccessDeniedHttpException` | `forbidden` | 403 |
  | `ModelNotFoundException` / `NotFoundHttpException` | `not_found` | 404 |
  | `ThrottleRequestsException` | `too_many_requests` | 429 |
  | anything else | `server_error` | 500 |
- Every error response includes `request_id` (pull from the request attribute set in Ticket 7; fall back to a fresh uuid).
- In non-local env, generic `server_error` must **not** leak the exception message — use `__('custom.errors.server_error')`.
- Provide a small private helper (a trait or an inline closure) that builds the error array so shape stays identical everywhere.

**Convention check:** the handler is the **only** place that turns exceptions into HTTP. Services never do.

---

## Ticket 7 — `request_id` middleware
**File:** `app/Http/Middleware/AttachRequestId.php`
- On handle: generate `(string) Str::uuid()`, store on the request (`$request->attributes->set('request_id', $id)`), continue, then set response header `X-Request-Id: $id`.
- The value is read by the base controller (success envelope) and the exception handler (error envelope), so both echo the **same** id that's in the header.

**Register in `bootstrap/app.php`:** append to the `api` middleware group so every API response carries it.

---

## Ticket 8 — `BaseService`
**File:** `app/Base/BaseService.php` (abstract)
- Properties:
  - `protected string $model;` — FQCN of the Eloquent model (subclasses set it).
  - `protected array $with = [];` — default eager-loads.
  - `protected int $perPage = 15;`
- Methods (all return `['data' => ..., 'code' => int]`; **never** HTTP, **never** read `request()`):
  - `index(array $filters = [], ?BaseFilter $filter = null): array` — builds query, applies `$with`, applies filter, **paginates** (never `all()`), returns paginator in `data` with code 200.
  - `show(Model $model): array` — loads `$with`, returns model, code 200.
  - `store(array $data): array` — `DB::transaction`, create, return fresh model with `$with`, code 201.
  - `update(Model $model, array $data): array` — `DB::transaction`, update, return refreshed model, code 200.
  - `destroy(Model $model): array` — `DB::transaction`, delete, return `['data' => null, 'code' => 204]`.
  - `protected query(): Builder` — `($this->model)::query()->with($this->with)`.
- Uses `Illuminate\Support\Facades\DB` for transactions on every write.

**Note:** accepts already-bound `Model` instances (route-model binding happens in the controller), so the service stays request-agnostic.

---

## Ticket 9 — `BaseFilter`
**File:** `app/Base/BaseFilter.php`
- Purpose: translate `?field[op]=value` query params into query constraints.
- Constructor: `(array $filters, array $allowed = [])` — `$filters` is the raw associative array (e.g. `['status' => ['eq' => 'open'], 'price' => ['gte' => '100']]`); `$allowed` whitelists filterable fields.
- `apply(Builder $query): Builder` — iterate whitelisted fields; for each op apply:
  - `eq` → `where(field, '=', v)`
  - `like` → `where(field, 'like', "%$v%")`
  - `gte` → `where(field, '>=', v)`
  - `lte` → `where(field, '<=', v)`
  - `in` → `whereIn(field, (array) v)` (accept comma-string or array)
- Ignore any field not in `$allowed` (no error, silent skip). Ignore unknown ops.

**Test target (Ticket 20):** each operator produces the right SQL/result on a scratch model.

---

## Ticket 10 — `BaseResource` + `BaseCollection`
**Files:**
- `app/Base/BaseResource.php` (extends `Illuminate\Http\Resources\Json\JsonResource`) — a thin base. Subclasses implement `toArray()`. **No queries inside resources** (§9). Provide a helper for exposing `uuid` not `id` on public models.
- `app/Base/BaseCollection.php` (extends `ResourceCollection`) — shapes paginated output into `{ items: [...], meta: {...} }` matching the envelope's paginated form. `meta` = `current_page, per_page, total, last_page`.

**Note:** the collection is what the base index controller wraps a paginator in so the `items/meta` shape is produced in exactly one place.

---

## Ticket 11 — Base controllers (`BaseCRUDController`, `BaseIndexController`)
**Files:**
- `app/Base/BaseController.php` (abstract) — holds the **envelope shapers**:
  - `protected success(mixed $data = null, string $messageKey = 'custom.messages.success', int $code = 200): JsonResponse` — builds `{ success:true, message: __($messageKey), data, request_id }` reading `request_id` from the request attribute (Ticket 7).
  - `protected respondFromService(array $result, string $messageKey = 'custom.messages.success'): JsonResponse` — takes a service's `['data','code']`, unwraps, and calls `success()`. If `data` is a `LengthAwarePaginator`, wrap it via `BaseCollection` so `items/meta` shape is produced.
- `app/Base/BaseCRUDController.php` (abstract, extends `BaseController`) — thin delegation to an injected service:
  - `index(Request $request)`, `show(Model $model)`, `store(BaseRequest $request)`, `update(BaseRequest $request, Model $model)`, `destroy(Model $model)`.
  - Each calls the matching service verb and returns `respondFromService(...)`. **No logic beyond delegation + envelope.**
  - Subclasses declare `protected function service(): BaseService` and the resource class to use.
- `app/Base/BaseIndexController.php` (abstract, extends `BaseController`) — read-only: `index` + `show` only (for public read endpoints).

**Convention check:** controllers are thin; they never contain business rules, never touch the DB directly.

---

## Ticket 12 — `BaseRequest`
**File:** `app/Base/BaseRequest.php` (abstract, extends `FormRequest`)
- `authorize(): bool` default `true` (module requests override).
- `messages(): array` — pull localized messages from `custom.php` validation keys where applicable.
- `attributes(): array` — localized attribute names.
- On failure, Laravel throws `ValidationException` → Ticket 6 handler shapes the `validation_failed` envelope. **Do not** override `failedValidation` to build responses.

---

## Ticket 13 — Traits
**Files (namespace `App\Traits`):**
- `app/Traits/HasUuid.php` — boot hook: on `creating`, set `uuid = (string) Str::uuid()` if empty. `getRouteKeyName(): string { return 'uuid'; }` so route-model binding uses uuid (§9). Assumes a `uuid` column on the model's table.
- `app/Traits/HasTranslations.php` — **wrap** `Spatie\Translatable\HasTranslations`. Re-export the Spatie trait under `App\Traits` so all models import the app trait (single seam to swap the package later). Add no behavior beyond `use Spatie\Translatable\HasTranslations as SpatieHasTranslations;` inside.
- `app/Traits/LogsActivity.php` — **wrap** `Spatie\Activitylog\Traits\LogsActivity`. Provide a sane default `getActivitylogOptions()` (`logAll()` or `logFillable()` + `logOnlyDirty()`), overridable per model. App-level seam.
- `app/Traits/FileTrait.php` — file upload helper:
  - `storeFile(UploadedFile $file, string $dir, string $disk = 'public'): string` — stores, returns the **path**.
  - `fileUrl(?string $path, string $disk = 'public'): ?string` — returns full URL via `Storage::disk($disk)->url($path)`; null-safe.
  - `deleteFile(?string $path, string $disk = 'public'): void`.
- **Run** `php artisan storage:link` (add to Ticket 22 command list) so the `public` disk serves URLs.

**Note on trait naming collision:** `App\Traits\LogsActivity` and `App\Traits\HasTranslations` intentionally shadow the Spatie names. Import the Spatie trait with an alias inside the wrapper to avoid recursion.

---

# P0.3 — RBAC install

## Ticket 14 — Configure spatie/laravel-permission for `users` guard
**Files:**
- `config/permission.php` — already published (Ticket 2). Confirm defaults are fine; no guard hard-coding needed here (guard is set per role/permission at seed time).
- Run migration publish already done in Ticket 2 via the provider publish; ensure the permission tables migration is present in `database/migrations/`.

**Edit `app/Models/User.php` (Ticket 16 covers the model body):** add `use Spatie\Permission\Traits\HasRoles;` and `use HasRoles;`. All roles/permissions in seeders are created with `'guard_name' => 'users'`.

---

## Ticket 15 — `Gate::before` super-admin bypass
**File:** `app/Providers/AppServiceProvider.php` (edit `boot()`)
- Add:
  ```php
  Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);
  ```
- Returning `null` (not `false`) lets normal permission checks proceed for non-super-admins. **This is exactly the spec — do not return `false`.**

---

## Ticket 16 — `User` model
**File:** `app/Models/User.php` (edit the installer's file)
- Traits: `HasApiTokens` (Sanctum), `HasRoles` (spatie, guard `users`), `LogsActivity` (app trait), `Notifiable`.
- Add fillable/casts for new columns (migration in Ticket 17): `type`, `is_active`.
- Method: `public function isSuperAdmin(): bool { return $this->type === 'super_admin'; }`.
- `type` values: `super_admin` | `staff`. `is_active` bool default true.

**Migration for the new columns:** Ticket 17.

---

## Ticket 17 — `users` table columns migration
**File:** `database/migrations/xxxx_add_type_and_is_active_to_users_table.php`
- Add `type` (string, default `'staff'`, indexed) and `is_active` (boolean, default `true`, indexed) to the `users` table.
- **Index** both (frequent where-clause columns per §9).

**Note:** keep it a separate migration from the default users migration so the installer's file stays untouched.

---

## Ticket 18 — Permission + role-preset seeder (idempotent)
**File:** `database/seeders/RolesAndPermissionsSeeder.php`
- **Reset spatie cache** first: `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();`
- **Permission catalog** (create each with `firstOrCreate(['name' => $p, 'guard_name' => 'users'])` — idempotent):
  ```
  reservations.view, reservations.create, reservations.cancel,
  folios.view, folios.settle,
  cms.view, cms.edit,
  service_requests.view, service_requests.assign, service_requests.update,
  tickets.view, tickets.assign, tickets.respond,
  pricing.edit, reports.view, staff.manage
  ```
- **Role presets** (`firstOrCreate(['name'=>..., 'guard_name'=>'users'])` then `syncPermissions([...])` so re-running converges — idempotent):
  - `reception` → `reservations.view, reservations.create, reservations.cancel, folios.view, folios.settle, service_requests.view`
  - `kitchen` → `service_requests.view, service_requests.update`
  - `housekeeping` → `service_requests.view, service_requests.update`
  - `concierge` → `service_requests.view, service_requests.assign, service_requests.update`
  - `events` → `service_requests.view, tickets.view, tickets.assign, tickets.respond`
- **Must be idempotent:** running `db:seed` twice produces the same rows, no duplicates, no errors.

**Register in `database/seeders/DatabaseSeeder.php`:** call `$this->call(RolesAndPermissionsSeeder::class);`.

---

# P0.4 — Conventions harness

## Ticket 19 — `custom.php` lang files (en + ar)
**Files:**
- `lang/en/custom.php` — returns an array. Keys needed by the base layer:
  ```php
  'messages' => ['success' => 'Success', 'created' => 'Created', 'deleted' => 'Deleted'],
  'errors' => [
    'server_error' => 'Something went wrong.',
    'not_found' => 'Resource not found.',
    'unauthorized' => 'Unauthenticated.',
    'forbidden' => 'You do not have permission to perform this action.',
    'validation_failed' => 'The given data was invalid.',
    'too_many_requests' => 'Too many requests. Please try again later.',
  ],
  'health' => ['ok' => 'Service healthy'],
  ```
- `lang/ar/custom.php` — **same keys**, Arabic values. Every key present in `en` must exist in `ar` (§9).
- **Run** `php artisan lang:publish` first if the `lang/` dir doesn't exist (Laravel 12 ships without it). Add to Ticket 22.

---

## Ticket 20 — Base test case + factories scaffold + base-layer tests
**Files:**
- `tests/TestCase.php` — already exists; ensure it uses `RefreshDatabase` where needed (per-test trait, not global).
- `tests/CreatesApplication` — L12 bootstraps via `bootstrap/app.php`; no separate trait needed. Skip if not present.
- `database/factories/UserFactory.php` — extend the installer's factory: add `type` (default `staff`), `is_active` (true), plus a `superAdmin()` state (`type => 'super_admin'`) and a `staff()` state.
- **Base-layer feature/unit tests** (the P0 done-condition suite):
  - `tests/Feature/HealthEndpointTest.php` — GET `/api/health` returns 200, `success:true`, has `data`, has `request_id`, and the `X-Request-Id` header equals the body `request_id`.
  - `tests/Feature/ExceptionEnvelopeTest.php` — hit the probe route that throws a `DomainException` subclass; assert 4xx, `success:false`, stable `error_code`, `request_id` present. Also assert validation failure returns `error_code: validation_failed` + `errors`.
  - `tests/Unit/BaseFilterTest.php` — each operator (`eq, like, gte, lte, in`) filters a scratch model/table correctly; non-whitelisted field is ignored.
  - `tests/Feature/SuperAdminBypassTest.php` — a `super_admin` user passes a `Gate`/`can()` check for a permission they were never granted; a plain `staff` without the permission is denied.
  - `tests/Feature/GuardsResolveTest.php` — a staff token hits the staff probe route (Ticket 21) → 200; a guest token hits the guest probe route → 200; wrong-guard token → 401 `unauthorized`.
  - `tests/Feature/SeederTest.php` — after seeding, assert all 16 permissions and all 5 role presets exist with `guard_name = users`, and that seeding twice does not duplicate.

---

## Ticket 21 — `/health` + probe routes (envelope proof + guard proof)
**File:** `routes/api.php`
- `GET /health` → returns `success(['status' => 'ok', 'time' => now()->toIso8601String()], 'custom.health.ok')`. Proves envelope + `request_id` end to end. **Permanent** (a real liveness probe).
- `GET /probe/domain-exception` → a closure/controller that `throw new NotFoundException('probe')` (or a tiny dedicated `ProbeException extends DomainException`). Proves the error envelope + stable `error_code`. **Mark `// P0 test probe — remove before P1 ships`.**
- `GET /probe/staff` → `->middleware('auth:users')`, returns `success(['guard'=>'users'])`. **Test probe — remove later.**
- `GET /probe/guest` → `->middleware('auth:guests')`, returns `success(['guard'=>'guests'])`. **Test probe — remove later.**

**Note:** keep probes minimal; the three `/probe/*` routes exist only to satisfy the P0 done-condition tests and must be deleted before P1 business routes land. `/health` stays.

---

## Ticket 22 — Wire-up & migrate (final assembly)
This ticket runs the artisan commands and confirms the done-condition. No new files.

**Command order (run in `backend/`):**
```
php artisan lang:publish
php artisan storage:link
php artisan migrate:fresh --seed
php artisan test
```
- If `lang/` already exists from an earlier step, `lang:publish` is a no-op — safe.
- `migrate:fresh --seed` must be **green** and seed the 16 permissions + 5 presets.
- `php artisan test` must be **green** across all Ticket 20 tests.

---

# Done-condition checklist (verify before stop-and-report)

- [ ] `php artisan migrate:fresh --seed` green; 16 permissions + 5 role presets seeded (`guard_name = users`); seeder idempotent (run twice, no dupes).
- [ ] `GET /api/health` → success envelope with `request_id`, and `X-Request-Id` header matches body.
- [ ] `GET /api/probe/domain-exception` → error envelope with stable `error_code` + `request_id`.
- [ ] Staff token → `/probe/staff` 200; guest token → `/probe/guest` 200; wrong guard → 401 `unauthorized`.
- [ ] `super_admin` bypasses an ungranted permission; plain `staff` is denied.
- [ ] `php artisan test` green (envelope shape, filter operators, exception mapping, super-admin bypass, guards, seeder).
- [ ] Base layer contains **no domain logic**; all conventions in §9 honored.
- [ ] **Stop and report.** Append build notes to `LOG_REPORT.md`, test results to `TESTING_REPORT.md`. Wait for go-ahead.

---

# Full artisan command sequence (consolidated, in order)

```
# Ticket 1
laravel new backend          # run inside D:\TupCode\Carlton\

# Ticket 2 (inside backend/)
php artisan install:api
composer require spatie/laravel-permission spatie/laravel-activitylog giggsey/libphonenumber-for-php spatie/laravel-translatable kreait/laravel-firebase
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider" --tag="config"

# Ticket 19 / 13 / 22
php artisan lang:publish
php artisan storage:link

# Ticket 22 (final)
php artisan migrate:fresh --seed
php artisan test
```

---

# Implementation order (strict)

1 → 2 → 3 → 4 (init) → 5 → 6 → 7 (exceptions + request_id) → 8 → 9 → 10 → 11 → 12 → 13 (base layer) → 14 → 15 → 16 → 17 → 18 (RBAC) → 19 → 20 → 21 → 22 (harness + assembly).

**Rationale:** exceptions + request_id before controllers (controllers depend on the envelope + handler); traits before the User model (User uses `LogsActivity`); RBAC seeder after the User model + columns exist; probes + tests last so they exercise everything.
