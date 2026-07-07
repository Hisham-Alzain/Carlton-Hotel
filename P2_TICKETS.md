# P2 — Staff RBAC Management: Ticket List

**For:** Coder (Sonnet). **Spec only — no code below is to be copy-pasted; implement to convention.**
All paths are relative to `backend/`. Effective access = **role preset permissions + direct grants − direct revokes** (spatie: role perms via `assignRole`, direct grants via `givePermissionTo`, revokes via `revokePermissionTo`).

---

## ⚠️ CRITICAL PRE-WORK FINDING (must fix first)

The `users` table has **no `uuid` column** and `App\Models\User` does **not** use `HasUuid`. However `UserResource` already exposes `$this->uuid` and P1 tests assert `data.uuid` (they currently pass only because SQLite returns `null` and the assertion compares `null === null` when the factory user has no uuid — verify). P2 routes bind `/staff/{uuid}`, which **requires** route-model binding on `uuid`. This must be fixed in Ticket 0 before any route works.

---

## Ticket 0 — Add `uuid` to users + enable HasUuid

**0a. Migration** — `database/migrations/<timestamp>_add_uuid_to_users_table.php`
- `Schema::table('users')`: `$table->uuid('uuid')->nullable()->after('id')->unique()->index();`
- In same migration `up()`, backfill existing rows: iterate `User::whereNull('uuid')->get()` and set `Str::uuid()` (safe — dev only), OR document that `migrate:fresh --seed` is required.
- `down()`: `dropColumn('uuid')`.
- Artisan: `php artisan make:migration add_uuid_to_users_table` then edit.

**0b.** `app/Models/User.php`
- Add `use App\Traits\HasUuid;` to the `use` group (this trait sets `getRouteKeyName()` → `uuid` and auto-fills on `creating`).
- Add `'uuid'` is NOT needed in `$fillable` (trait sets it via model event, not mass-assign).

**0c.** `database/factories/UserFactory.php`
- No change needed if HasUuid boots on `creating`. Verify factory-created users get a uuid; if not, add `'uuid' => (string) Str::uuid()` to `definition()`.

**0d.** Run `php artisan migrate:fresh --seed` and confirm green + existing 36 tests pass before continuing.

---

## Ticket 1 — StaffPolicy

**File:** `app/Policies/StaffPolicy.php`

Gates all `/staff/*` actions. Registered via auto-discovery (Laravel 11 maps `User` → `UserPolicy` by default; since our model is `User` but policy is `StaffPolicy`, **explicitly register** in `AppServiceProvider::boot()` with `Gate::policy(User::class, StaffPolicy::class)` — confirm no existing `UserPolicy`).

**Super-admin bypass:** already handled globally by `Gate::before` in `AppServiceProvider` (returns `true` for super-admin). So policy methods only run for non-super-admins. Every method below is written for the **non-super-admin** case.

Methods (each returns `bool`; controller/action throws `ForbiddenException` on false, or use `$this->authorize()`):
- `viewAny(User $actor): bool` → `$actor->hasPermissionTo('staff.manage')`
- `view(User $actor, User $target): bool` → `$actor->hasPermissionTo('staff.manage')` (super_admin targets are viewable, just not editable — see below)
- `create(User $actor): bool` → `$actor->hasPermissionTo('staff.manage')`
- `update(User $actor, User $target): bool` → `$actor->hasPermissionTo('staff.manage') && ! $target->isSuperAdmin()`
- `assignPermissions(User $actor, User $target): bool` → `$actor->hasPermissionTo('staff.manage') && ! $target->isSuperAdmin()`
- `deactivate(User $actor, User $target): bool` → `$actor->hasPermissionTo('staff.manage') && ! $target->isSuperAdmin() && $actor->id !== $target->id` (cannot self-deactivate)

**Guard-rail rule 2 (cannot edit/demote super_admin)** lives here (the `! $target->isSuperAdmin()` clause). **Guard-rail rule 1 (no privilege escalation)** is NOT in the policy — it is per-permission and lives in `AssignPermissionsAction` (Ticket 5), because the policy cannot see which specific permissions are being granted.

---

## Ticket 2 — StaffService

**File:** `app/Services/StaffService.php` (extends `App\Base\BaseService`)

Config:
- `protected string $model = User::class;`
- `protected array $with = ['roles', 'permissions'];`
- Override `query()` to scope to staff/manageable users: `return User::query()->with($this->with);` (list ALL users incl. super_admins so they show read-only; do NOT hide them).

Methods (all return `['data' => ..., 'code' => int]`; never read `request()`; wrap writes in `DB::transaction`):

- `index(array $filters = [], ?BaseFilter $filter = null): array` — inherit from Base (paginated). If a `StaffFilter` is added (optional, see Ticket 2b), accept it. Must paginate — never `all()`.
- `createFromPreset(array $data): array`
  - `$data` = validated: `name`, `email`, `password`, `role` (preset name).
  - In `DB::transaction`: create `User` with `type = 'staff'`, `is_active = true`, hashed password (model casts `password` → `hashed`, so pass raw). Then `$user->assignRole($data['role'])`.
  - `loadMissing($this->with)`; return `['data' => $user, 'code' => 201]`.
- `update(User $user, array $data): array`
  - Only `name` + `email` allowed (NEVER `type`, `is_active`, `password` here). `DB::transaction(fn () => $user->update($only_name_email))`.
  - `refresh()->loadMissing($this->with)`; `['data' => $user, 'code' => 200]`.
- `deactivate(User $user): array`
  - `DB::transaction(fn () => $user->update(['is_active' => false]))`. Also **revoke all tokens** so an active session can't continue: `$user->tokens()->delete()` inside the transaction.
  - `['data' => $user->refresh(), 'code' => 200]`.

**2b (optional but recommended)** `app/Http/Filters/StaffFilter.php` extends `BaseFilter` — allow filtering by `type`, `is_active`, `role`, and `search` (name/email `LIKE`). Only build if `BaseFilter` pattern exists; otherwise skip and note.

---

## Ticket 3 — PermissionAssignmentService

**File:** `app/Services/PermissionAssignmentService.php`

Holds the pure grant/revoke mechanics (no HTTP, no request). The **escalation guard** is enforced in `AssignPermissionsAction` (Ticket 5) BEFORE calling this service, but this service also exposes a helper the action uses.

Methods:
- `apply(User $target, array $grant, array $revoke): array`
  - In `DB::transaction`:
    - For each name in `$grant`: `$target->givePermissionTo($name)` (direct grant).
    - For each name in `$revoke`: `$target->revokePermissionTo($name)` (removes direct grant AND blocks a role-inherited perm? No — spatie `revokePermissionTo` only removes direct. To model "direct revoke" of a role-inherited permission, see note below).
  - `$target->forgetCachedPermissions()` via registrar if needed; `$target->load('roles','permissions')`.
  - Return `['data' => $target, 'code' => 200]`.

**IMPORTANT modelling note for the Coder / to confirm with Planner:** spatie has no native "negative permission." The architecture says effective = role perms + grants − revokes. Two viable implementations:
  1. **Simple (recommended for P2):** "revoke" only removes a *direct* grant (`revokePermissionTo`). Revoking a role-inherited permission is not supported; document this. Effective = role perms ∪ direct grants, minus any direct grant that was revoked.
  2. **Full negative model:** add a pivot/flag table of "denied permissions" and override `getAllPermissions()` to subtract them. Heavier — **defer unless Planner insists.**
  Implement option 1 for P2. Flag in PR description that true role-perm revocation is deferred.

- `heldBy(User $actor): \Illuminate\Support\Collection` — returns `$actor->getAllPermissions()->pluck('name')` (used by the escalation guard).

---

## Ticket 4 — CreateStaffAction

**File:** `app/Actions/Staff/CreateStaffAction.php`

- `handle(array $data): array` — thin orchestrator. Validates the `role` is a real preset (exists in `roles` table with guard `users`); if not, throw `ForbiddenException` or a validation-level failure (prefer the Request to validate role via `exists`). Calls `StaffService::createFromPreset($data)` and returns its result unchanged.
- Keep it minimal; the Request (Ticket 6) does field validation, the Policy (Ticket 1) does authorization. Action exists for symmetry with P2 slice + future hooks (e.g., welcome email).

---

## Ticket 5 — AssignPermissionsAction (escalation guard lives here)

**File:** `app/Actions/Staff/AssignPermissionsAction.php`

- `handle(User $actor, User $target, array $grant, array $revoke): array`
- **Guard rail 1 (no privilege escalation):**
  - If `$actor->isSuperAdmin()` → skip guard (bypass). Otherwise:
  - `$held = PermissionAssignmentService::heldBy($actor)` (the actor's own effective permissions).
  - For every permission name in `$grant`: if `! $held->contains($name)` → `throw new ForbiddenException(__('custom.errors.escalation_blocked'))`.
  - This automatically blocks granting `staff.manage` to self/others unless the actor holds it (rule 3 = subset of rule 1).
- **Guard rail 2 (super_admin untouchable):** enforced by StaffPolicy `assignPermissions` before the action runs; additionally defensively re-check `if ($target->isSuperAdmin()) throw new ForbiddenException(__('custom.errors.superadmin_immutable'));`.
- Validate every name in `$grant`/`$revoke` is a real permission (exists in permissions table) — prefer Request-level `exists:permissions,name`; defensively the action may re-check and throw `ForbiddenException` / a domain exception on unknown permission.
- On success: call `PermissionAssignmentService::apply($target, $grant, $revoke)` and return its result.

---

## Ticket 6 — Requests

**6a.** `app/Http/Requests/Staff/CreateStaffRequest.php` (extends `BaseRequest`)
```
rules:
  name     => required|string|max:255
  email    => required|email|max:255|unique:users,email
  password => required|string|min:8
  role     => required|string|exists:roles,name   // preset name
```
`authorize()` stays `true` (policy handles auth in controller). Add any new attribute messages to lang if needed (see lang section — reuse existing `required/string/email/max`; add `min` + `unique` + `exists`).

**6b.** `app/Http/Requests/Staff/AssignPermissionsRequest.php` (extends `BaseRequest`)
```
rules:
  grant           => sometimes|array
  grant.*         => string|distinct|exists:permissions,name
  revoke          => sometimes|array
  revoke.*        => string|distinct|exists:permissions,name
```
Add a `withValidator` / rule ensuring `grant` ∩ `revoke` = ∅ (a name cannot be in both) → fail with `custom.validation.grant_revoke_conflict`. At least one of `grant`/`revoke` must be present (`required_without`).

**6c.** `app/Http/Requests/Staff/UpdateStaffRequest.php` (extends `BaseRequest`)
```
rules:
  name  => sometimes|string|max:255
  email => sometimes|email|max:255|unique:users,email,{userUuid via route}
```
Use `Rule::unique('users','email')->ignore($this->route('user')->id)` (route param is the `User` model via binding). **Must NOT accept `type`, `password`, `is_active`.**

---

## Ticket 7 — Resources

**7a.** `app/Http/Resources/StaffResource.php` (extends `BaseResource`)
Fields: `uuid`, `name`, `email`, `type`, `is_active`, `roles` (`$this->getRoleNames()`), `effective_permissions` (`$this->getAllPermissions()->pluck('name')->values()`), and optionally split visibility: `direct_permissions` (`$this->getDirectPermissions()->pluck('name')`) and `role_permissions` (`$this->getPermissionsViaRoles()->pluck('name')`) so the UI can show the override breakdown required by the spec ("per-account override changes effective permissions"). Never query inside the resource — rely on `$with = ['roles','permissions']` eager-loaded by StaffService.

**7b.** `app/Http/Resources/PermissionResource.php` (extends `BaseResource`)
Represents ONE permission group (a module), NOT one permission — because `/permissions` returns permissions **grouped by module**. Two clean options:
  - **Collection-side grouping (recommended):** Controller builds the grouped array and passes it; `PermissionResource` shapes one group: `{ module: string, permissions: [names...] }`.
  - Grouping logic: take all permission names, split each on the FIRST `.` → prefix = module. Group by prefix. Example output:
    ```
    [
      { "module": "reservations", "permissions": ["reservations.view","reservations.create","reservations.cancel"] },
      { "module": "folios",       "permissions": ["folios.view","folios.settle"] },
      ...  (cms, service_requests, tickets, pricing, reports, staff)
    ]
    ```
  - The 16 permissions yield 8 modules: reservations, folios, cms, service_requests, tickets, pricing, reports, staff. **Split on first `.` only** so `service_requests.view` → module `service_requests` (NOT `service`).

**7c.** `app/Http/Resources/RolePresetResource.php` (extends `BaseResource`)
Wraps a spatie `Role`: `{ name: role->name, permissions: role->permissions->pluck('name')->values() }`. Rely on eager-loaded `permissions` (controller loads `Role::with('permissions')`).

---

## Ticket 8 — Controllers

**8a.** `app/Http/Controllers/Staff/StaffController.php` (extends `BaseController`, NOT BaseCRUDController — routes are non-standard)
Constructor injects `StaffService`, `CreateStaffAction`, `AssignPermissionsAction`. Each method calls `$this->authorize(...)` (StaffPolicy) then service/action, then wraps result in the resource + `success()`.
- `index(Request $request)` → `authorize('viewAny', User::class)` → `StaffService::index()` → paginated `StaffResource::collection` via `respondFromService` (note: `respondFromService` wraps paginator in `BaseCollection`; to use `StaffResource` per-item, either set `BaseCollection` to map resource, or build collection manually — confirm BaseCollection maps items through a resource; if not, return `StaffResource::collection($paginator)` and shape meta manually). **Coder: check whether `BaseCollection` applies a resource; P1 used it raw. Simplest: `respondFromService` returns raw models — acceptable, or override to use StaffResource.**
- `show(User $user, Request $request)` → `authorize('view', $user)` → `StaffService::show($user)` → `StaffResource`.
- `store(CreateStaffRequest $request)` → `authorize('create', User::class)` → `CreateStaffAction::handle($request->validated())` → `StaffResource`, code 201.
- `update(UpdateStaffRequest $request, User $user)` → `authorize('update', $user)` → `StaffService::update($user, $request->validated())` → `StaffResource`.
- `assignPermissions(AssignPermissionsRequest $request, User $user)` → `authorize('assignPermissions', $user)` → `AssignPermissionsAction::handle($request->user('users'), $user, $request->validated('grant', []), $request->validated('revoke', []))` → `StaffResource`.
- `deactivate(User $user, Request $request)` → `authorize('deactivate', $user)` → `StaffService::deactivate($user)` → `StaffResource`, message `custom.messages.staff_deactivated`.

**8b.** `app/Http/Controllers/Staff/PermissionController.php` (extends `BaseController`)
- `index(Request $request)` → `authorize('viewAny', User::class)` (or a lighter gate — anyone with `staff.manage`) → fetch `Permission::all()->pluck('name')`, group by first-`.` prefix, wrap as `PermissionResource::collection($groups)` → `success`. (This is a fixed catalog of 16 — `all()` is acceptable here; note the "never all()" rule is about paginating large tables, and this is a bounded reference list. Document the exception.)

**8c.** `app/Http/Controllers/Staff/RoleController.php` (extends `BaseController`)
- `index(Request $request)` → `authorize('viewAny', User::class)` → `Role::with('permissions')->get()` → `RolePresetResource::collection(...)` → `success`. Bounded reference list (5 presets) — `get()` acceptable, document.

---

## Ticket 9 — Routes

**File:** `routes/api.php` — add inside `Route::middleware('auth:users')` group (create one if the current file only guards `/auth/*`; wrap the staff block in `Route::middleware('auth:users')->group(...)`).

```
Route::middleware('auth:users')->group(function () {
    Route::get   ('/staff',                    [StaffController::class, 'index']);
    Route::post  ('/staff',                    [StaffController::class, 'store']);
    Route::get   ('/staff/{user}',             [StaffController::class, 'show']);
    Route::put   ('/staff/{user}',             [StaffController::class, 'update']);
    Route::post  ('/staff/{user}/permissions', [StaffController::class, 'assignPermissions']);
    Route::patch ('/staff/{user}/deactivate',  [StaffController::class, 'deactivate']);

    Route::get   ('/permissions',              [PermissionController::class, 'index']);
    Route::get   ('/roles',                    [RoleController::class, 'index']);
});
```
Route param name `{user}` binds `User` by `uuid` (via HasUuid `getRouteKeyName`). Spec wrote `{uuid}` — the URL segment is the uuid value; the binding var must be `{user}` for implicit model binding. Confirm `use App\Http\Controllers\Staff\{StaffController, PermissionController, RoleController};` imports added.

---

## Ticket 10 — Lang keys (Coder adds to BOTH `lang/en/custom.php` AND `lang/ar/custom.php`)

Add under existing sections. **New keys:**

`errors.*`:
- `escalation_blocked` — EN: "You cannot grant a permission you do not hold." / AR: "لا يمكنك منح صلاحية لا تملكها."
- `superadmin_immutable` — EN: "Super admin accounts cannot be modified." / AR: "لا يمكن تعديل حسابات المشرف الأعلى."
- `cannot_self_deactivate` — EN: "You cannot deactivate your own account." / AR: "لا يمكنك إلغاء تفعيل حسابك الخاص."
- `unknown_permission` — EN: "One or more permissions are invalid." / AR: "واحدة أو أكثر من الصلاحيات غير صالحة."

`messages.*`:
- `staff_created` — EN: "Staff account created." / AR: "تم إنشاء حساب الموظف."
- `staff_updated` — EN: "Staff account updated." / AR: "تم تحديث حساب الموظف."
- `staff_deactivated` — EN: "Staff account deactivated." / AR: "تم إلغاء تفعيل حساب الموظف."
- `permissions_updated` — EN: "Permissions updated." / AR: "تم تحديث الصلاحيات."

`validation.*` (add missing rule messages used by new Requests):
- `min` — EN: "The :attribute must be at least :min characters." / AR: "يجب أن يكون :attribute على الأقل :min أحرف."
- `unique` — EN: "The :attribute has already been taken." / AR: "قيمة :attribute مستخدمة بالفعل."
- `exists` — EN: "The selected :attribute is invalid." / AR: "قيمة :attribute المحددة غير صالحة."
- `grant_revoke_conflict` — EN: "A permission cannot be both granted and revoked." / AR: "لا يمكن منح صلاحية وسحبها في آن واحد."
- `distinct` — EN: "The :attribute field has a duplicate value." / AR: "يحتوي حقل :attribute على قيمة مكررة."

Also add the new rule keys (`min`, `unique`, `exists`, `distinct`) to `BaseRequest::messages()` mapping so they resolve, OR override `messages()` in the new Requests. **Recommend extending `BaseRequest::messages()`** to include `min`, `unique`, `exists`, `distinct`.

---

## Ticket 11 — Exceptions

**No new exception classes needed.** Existing `App\Exceptions\ForbiddenException` (403) covers escalation, super-admin-immutable, self-deactivate, and unknown-permission cases. `NotFoundException` (404) is thrown automatically by implicit model binding on bad uuid. Confirm the global handler in `bootstrap/app.php` maps `ForbiddenException` → 403 with `error_code: forbidden` (P1 tests already rely on this).

---

## Ticket 12 — UserResource

**No change required.** `UserResource` (P1) already exposes `uuid, name, email, type, is_active, roles, permissions` and is used only by `/auth/*`. P2 introduces a distinct `StaffResource` (Ticket 7a) with richer fields (`effective_permissions`, `direct_permissions`, `role_permissions`). Do **not** modify `UserResource`. (One caveat: once Ticket 0 adds the real uuid column, `UserResource.uuid` will start returning a real value — this is the intended fix, no code change.)

---

## Ticket 13 — Tests

**File:** `tests/Feature/Staff/` (new dir). Use `RefreshDatabase` + `$this->seed(RolesAndPermissionsSeeder::class)` in `setUp` (mirror `StaffLoginTest`). Add `UserFactory` states already exist (`superAdmin()`, `staff()`).

Required cases (spec-mandated):
1. **`StaffCreateTest`** — actor with `staff.manage` POST `/staff` from preset `reception` → 201; created user has the preset's effective permissions (assert `data.effective_permissions` contains `reservations.view` etc.); `type=staff`, `is_active=true`.
2. **`PermissionOverrideTest`** — create staff from preset, then POST `/staff/{uuid}/permissions` granting an extra permission the actor holds → `data.effective_permissions` now includes it; revoke a direct grant → it disappears. Proves "override changes effective permissions."
3. **`EscalationBlockedTest`** — actor holds `staff.manage` but NOT `pricing.edit`; attempts to grant `pricing.edit` to a target → 403 `error_code: forbidden` (`escalation_blocked`). Also: actor cannot grant `staff.manage` they don't hold.
4. **`SuperAdminProtectedTest`** — non-super-admin with `staff.manage` attempts PUT `/staff/{superadmin-uuid}` and POST permissions and PATCH deactivate → all 403. Also super-admin actor CAN do everything (Gate::before bypass) → 200/201.
5. **`DeactivatedCannotAuthTest`** — create+activate staff, obtain token, PATCH `/staff/{uuid}/deactivate` → subsequent `/auth/me` (or login) with that user → 403/401. Confirm `deactivate` also purges tokens (call `forgetGuards()` like P1 logout test if reusing same token in one request cycle).
6. **`PermissionsGroupedTest`** — GET `/permissions` returns 8 groups; `service_requests` group has 3 permissions and module name is exactly `service_requests` (verifies first-`.` split).
7. **`RolePresetsTest`** — GET `/roles` returns 5 presets each with their permission bundles matching the seeder.
8. **`StaffAuthorizationTest`** — a staff user WITHOUT `staff.manage` gets 403 on every `/staff/*` and `/permissions`,`/roles` endpoint.

Run: `php artisan test` — all P2 + existing 36 must pass. Then `php artisan migrate:fresh --seed` green.

---

## Build order (dependencies)

0 (uuid fix) → 1 (policy) → 3 (PermissionAssignmentService) → 2 (StaffService) → 4,5 (actions) → 6 (requests) → 7 (resources) → 8 (controllers) → 9 (routes) → 10 (lang) → 13 (tests). Ticket 11 & 12 are confirmations (no/near-no code).

## Convention checklist (verify on EVERY file)
- Services/Actions return `['data'=>..., 'code'=>int]`; never HTTP; never `request()`.
- `DB::transaction` wraps every multi-write (createFromPreset, apply, deactivate).
- Throw `ForbiddenException` — never return error arrays.
- All strings via `__('custom.*')`, keys in BOTH en + ar.
- Resources expose `uuid`, never numeric `id`; never query inside resources (eager-load via service `$with`).
- Index new FK/where columns — the `users.uuid` unique index (Ticket 0) covers route binding lookups.
- Never `Model::all()` on unbounded tables — paginate `/staff`. `/permissions` (16) and `/roles` (5) are bounded reference lists; `all()`/`get()` allowed, documented.
