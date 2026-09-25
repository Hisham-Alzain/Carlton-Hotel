# Phase 1: Access & Settings - Pattern Map

**Mapped:** 2026-09-25
**Files analyzed:** 15
**Analogs found:** 13 / 15

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Services/Auth/AuthGuestService.php::logout()` | service | request-response | `app/Services/Auth/AuthStaffService.php::logout()` | exact |
| `app/Services/Auth/AuthStaffService.php::updateProfile()` | service | CRUD | `app/Http/Requests/Staff/UpdateStaffRequest.php` (validation) + `AuthStaffService::me()` (return shape) | role-match |
| `app/Services/Auth/AuthStaffService.php::changePassword()` | service | CRUD | `AuthStaffService::logout()` (token query pattern) | role-match |
| `app/Http/Requests/Auth/UpdateStaffProfileRequest.php` | model/validation | CRUD | `app/Http/Requests/Staff/UpdateStaffRequest.php` | exact |
| `app/Http/Requests/Auth/ChangePasswordRequest.php` | model/validation | CRUD | `app/Http/Requests/Staff/CreateStaffRequest.php` (password min:8) | role-match |
| `app/Http/Requests/Auth/GuestLogoutRequest.php` | model/validation | request-response | `app/Http/Requests/Auth/UpdateGuestProfileRequest.php` | role-match |
| `app/Http/Controllers/Auth/StaffAuthController.php::profile/updateProfile/changePassword` | controller | request-response | `StaffAuthController::logout/me` (same file, existing methods) | exact |
| `app/Http/Controllers/Auth/GuestAuthController.php::logout` | controller | request-response | `StaffAuthController::logout()` + `GuestAuthController::updateProfile()` | exact |
| `routes/api.php` (4 new lines) | route | request-response | existing `auth:users`/`auth:guests` blocks, same file lines ~86-113 | exact |
| `app/Base/BaseRequest.php::messages()` (add 3 entries) | utility | transform | same file, existing map entries | exact |
| `lang/en/custom.php`, `lang/ar/custom.php` | config | transform | same files, existing `messages`/`auth`/`validation` groups | exact |
| `tests/Feature/Auth/StaffProfileTest.php` | test | request-response | `tests/Feature/Auth/GuestProfileTest.php` | role-match |
| `tests/Feature/Auth/StaffPasswordChangeTest.php` | test | request-response | `tests/Feature/Auth/StaffLoginThrottleTest.php` + `StaffLoginTest::test_logout_invalidates_token` | exact |
| `tests/Feature/Auth/GuestLogoutTest.php` | test | request-response | `tests/Feature/Auth/StaffLoginTest.php::test_logout_invalidates_token` | exact |
| `docs/carlton-tree.html` (2 node flips), `backend/docs/API_GUIDE_*.md`, `backend/docs/postman/*` | config/docs | transform | existing Auth module sections/nodes | exact |

## Pattern Assignments

### `app/Services/Auth/AuthGuestService.php::logout()`

**Analog:** `app/Services/Auth/AuthStaffService.php::logout()` (lines 35-44, read directly)

```php
public function logout(User $user): array
{
    $token = $user->currentAccessToken();
    if ($token) {
        $token->delete();
    } else {
        $user->tokens()->delete(); // null-guard fallback
    }
    return ['data' => null, 'code' => 200];
}
```
Copy verbatim into `AuthGuestService`, swap `User $user` → `Guest $guest`. Add D-09 device-token cleanup as an independent second step (do not gate token delete on it). `AuthGuestService` constructor currently DI's Action classes (`RequestOtpAction`, etc.) — follow the same style if `logout` grows into an Action, but per CONTEXT.md discretion, inline the small body directly in `AuthGuestService::logout()` unless it exceeds the ~300-line service threshold.

Existing constructor pattern to match (imports/DI style), `app/Services/Auth/AuthGuestService.php` lines 1-18:
```php
namespace App\Services\Auth;

use App\Actions\Auth\LinkBookingCodeAction;
...
use App\Models\Guest;

class AuthGuestService
{
    public function __construct(
        private readonly RequestOtpAction     $requestOtp,
        ...
    ) {}
```

---

### `app/Services/Auth/AuthStaffService.php::changePassword()`

**Analog:** same file's `logout()` token-query idiom, plus RESEARCH.md verified Sanctum relation.

```php
public function changePassword(User $user, string $newPassword): array
{
    $current = $user->currentAccessToken();
    DB::transaction(function () use ($user, $newPassword, $current) {
        $user->update(['password' => $newPassword]); // 'hashed' cast handles hashing
        $user->tokens()
            ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))
            ->delete();
    });
    return ['data' => null, 'code' => 200];
}
```
Add `use Illuminate\Support\Facades\DB;` to the existing import block (currently `App\Exceptions\ForbiddenException`, `UnauthorizedException`, `App\Models\User`, `Illuminate\Support\Facades\Hash`).

### `app/Services/Auth/AuthStaffService.php::updateProfile()`

**Analog:** `AuthStaffService::me()` (return shape, lines 46-49) + `UpdateStaffRequest` (validated-fields idiom).

```php
public function updateProfile(User $user, array $data): array
{
    $user->update($data);
    $user->load('roles', 'permissions');
    return ['data' => $user, 'code' => 200];
}
```
Controller resource-wraps `$result['data']` in `UserResource` (see Open Question 1 in RESEARCH.md — use `UserResource`, matching `/auth/me`'s current shape, NOT `StaffResource`).

---

### `app/Http/Requests/Auth/UpdateStaffProfileRequest.php` (validation, CRUD)

**Analog:** `app/Http/Requests/Staff/UpdateStaffRequest.php` (full file, 16 lines, read directly)

```php
<?php
namespace App\Http\Requests\Staff;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends BaseRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        return [
            'name'  => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }
}
```
For `UpdateStaffProfileRequest`, swap `$this->route('user')?->id` → `$this->user('users')?->id` (self-service, no route-bound model), and add the conditional `current_password` rule (D-02) using the `$emailChanging` idiom shown in RESEARCH.md Pattern 3:
```php
$user = $this->user('users');
$emailChanging = $this->filled('email') && $this->input('email') !== $user->email;
'current_password' => [$emailChanging ? 'required' : 'sometimes', 'string', 'current_password:users'],
```

### `app/Http/Requests/Auth/ChangePasswordRequest.php` (validation, CRUD)

**Analog:** `app/Http/Requests/Staff/CreateStaffRequest.php` (`password => 'required|string|min:8'`) for the `min:8` idiom; no analog exists yet for `current_password`/`confirmed`/`different` — these are new rule names for this codebase (RESEARCH.md Pitfall 2).

```php
public function rules(): array
{
    return [
        'current_password' => ['required', 'string', 'current_password:users'],
        'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
    ];
}
```

### `app/Http/Requests/Auth/GuestLogoutRequest.php` (validation, request-response)

**Analog:** `app/Http/Requests/Auth/UpdateGuestProfileRequest.php` structure (class shell, `BaseRequest` extension) — this request has no uniqueness/conditional logic, just an optional field:
```php
public function rules(): array
{
    return [
        'device_token' => ['sometimes', 'nullable', 'string', 'max:500'],
    ];
}
```

---

### `app/Http/Controllers/Auth/StaffAuthController.php` (controller, request-response)

**Analog:** same file's existing `logout`/`me` methods (lines 1-32, read directly in full)

```php
namespace App\Http\Controllers\Auth;

use App\Base\BaseController;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAuthController extends BaseController
{
    public function __construct(private readonly AuthStaffService $service) {}

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('users'));
        return $this->success(null, 'custom.auth.logged_out', 200, $request);
    }

    public function me(Request $request): JsonResponse
    {
        $result = $this->service->me($request->user('users'));
        return $this->success(new UserResource($result['data']), 'custom.messages.success', 200, $request);
    }
}
```
New methods follow the identical shape:
```php
public function profile(Request $request): JsonResponse
{
    $result = $this->service->me($request->user('users')); // reuse existing me()
    return $this->success(new UserResource($result['data']), 'custom.messages.success', 200, $request);
}

public function updateProfile(UpdateStaffProfileRequest $request): JsonResponse
{
    $result = $this->service->updateProfile($request->user('users'), $request->validated());
    return $this->success(new UserResource($result['data']), 'custom.messages.profile_updated', 200, $request);
}

public function changePassword(ChangePasswordRequest $request): JsonResponse
{
    $this->service->changePassword($request->user('users'), $request->validated('password'));
    return $this->success(null, 'custom.messages.password_changed', 200, $request);
}
```
Add `use App\Http\Requests\Auth\UpdateStaffProfileRequest;` and `use App\Http\Requests\Auth\ChangePasswordRequest;` to the import block.

### `app/Http/Controllers/Auth/GuestAuthController.php::logout` (controller, request-response)

**Analog:** `GuestAuthController::updateProfile()` (lines 51-56, read directly) for the wiring shape; `StaffAuthController::logout()` for the logout body.
```php
public function logout(GuestLogoutRequest $request): JsonResponse
{
    $this->service->logout($request->user('guests'), $request->validated('device_token'));
    return $this->success(null, 'custom.auth.logged_out', 200, $request);
}
```
Add `use App\Http\Requests\Auth\GuestLogoutRequest;` to the existing import block.

---

### `routes/api.php` (route, request-response)

**Analog:** existing `auth:users`/`auth:guests` blocks (lines 86-113, read directly)
```php
Route::middleware('auth:users')->group(function () {
    Route::post('/logout', [StaffAuthController::class, 'logout']);
    Route::get('/me', [StaffAuthController::class, 'me']);
    // NEW:
    Route::get('/profile', [StaffAuthController::class, 'profile']);
    Route::put('/profile', [StaffAuthController::class, 'updateProfile']);
    Route::put('/password', [StaffAuthController::class, 'changePassword'])->middleware('throttle:5,1');
});
...
Route::middleware('auth:guests')->group(function () {
    Route::get('/me',      [GuestAuthController::class, 'me']);
    Route::put('/profile', [GuestAuthController::class, 'updateProfile']);
    // NEW:
    Route::post('/logout', [GuestAuthController::class, 'logout']);
});
```
Follow the existing throttle-with-comment convention (see login route comment block, same file) — add a short comment above `throttle:5,1` explaining the brute-force rationale (D-06), matching the login route's comment style.

---

### `app/Base/BaseRequest.php::messages()` (utility, transform)

**Analog:** same method, existing entries (full file read, 68 lines)
```php
'current_password' => __('custom.validation.current_password', ['attribute' => ':attribute']),
'confirmed'         => __('custom.validation.confirmed',        ['attribute' => ':attribute']),
'different'         => __('custom.validation.different',        ['attribute' => ':attribute', 'other' => ':other']),
```
Insert alongside existing entries (e.g. after `'distinct'`, before the `Enum::class` line) following the exact `__('custom.validation.X', [...])` idiom already used for every rule in that array.

### `lang/en/custom.php` / `lang/ar/custom.php` (config, transform)

**Analog:** existing groups, confirmed present: `messages.profile_updated` (en line 33: `'Profile updated.'`; ar line 33: `'تم تحديث الملف الشخصي.'`), `auth.logged_out` (en line 72: `'Logged out successfully.'`; ar line 72: `'تم تسجيل الخروج بنجاح.'`).
Add to `messages` group: `'password_changed' => 'Password changed.'` (ar equivalent). Add to `validation` group: `current_password`, `confirmed`, `different` keys, both locales, matching the flat `'key' => 'Sentence.'` style seen at lines 33/72.

---

### `tests/Feature/Auth/GuestLogoutTest.php` and `StaffPasswordChangeTest.php` (test, request-response)

**Analog:** `tests/Feature/Auth/StaffLoginTest.php::test_logout_invalidates_token` (lines 54-62, read directly)
```php
public function test_logout_invalidates_token(): void
{
    $user  = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;
    $this->withToken($token)->postJson('/api/auth/logout')->assertStatus(200);
    $this->app->get('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
}
```
**CRITICAL:** use `Guest::factory()->create() + ->createToken()->plainTextToken + withToken()`, never `actingAs()`, for any assertion about selective token revocation (RESEARCH.md Pitfall 1). `GuestProfileTest.php` uses `actingAs()` — do NOT copy that pattern for logout/password-change tests.

### `tests/Feature/Auth/StaffPasswordChangeTest.php` (throttle sub-test)

**Analog:** `tests/Feature/Auth/StaffLoginThrottleTest.php` (lines 1-56, read directly) — `private const LIMIT = 5;` loop asserting 422 up to the limit, then 429 with `error_code: too_many_requests` on the next call:
```php
for ($attempt = 1; $attempt <= self::LIMIT; $attempt++) {
    $this->withToken($token)->putJson('/api/auth/password', $wrongPayload)->assertStatus(422);
}
$this->withToken($token)->putJson('/api/auth/password', $wrongPayload)
     ->assertStatus(429)->assertJsonPath('error_code', 'too_many_requests');
```

### `tests/Feature/Auth/StaffProfileTest.php`

**Analog:** `tests/Feature/Auth/GuestProfileTest.php` — role-match only (guest self-service profile update shape); adapt asserted resource fields to `UserResource`'s shape (uuid, name, email, type, is_active, is_super_admin, roles, permissions), not `StaffResource`'s.

---

## Shared Patterns

### Token revocation (Sanctum)
**Source:** `app/Services/Auth/AuthStaffService.php::logout()`
**Apply to:** `AuthGuestService::logout()`, `AuthStaffService::changePassword()`
Both use `$user->currentAccessToken()` / `$user->tokens()` (MorphMany, from `HasApiTokens`); never hand-roll a loop.

### Controller wiring (request → service → success())
**Source:** `app/Http/Controllers/Auth/StaffAuthController.php`, `GuestAuthController.php`
**Apply to:** all 4 new controller methods
Controllers never touch `request()` inside services; `$this->success($data, $langKey, $code, $request)` is the uniform envelope call.

### FormRequest → `BaseRequest::messages()` localization contract
**Source:** `app/Base/BaseRequest.php`
**Apply to:** `ChangePasswordRequest`, `UpdateStaffProfileRequest` — any new validation rule name used for the first time (`current_password`, `confirmed`, `different`) MUST get an entry here plus `custom.validation.*` keys in both `lang/en/custom.php` and `lang/ar/custom.php`, or 422s in non-English locales leak raw rule names (RESEARCH.md Pitfall 2, hard gate).

### Email uniqueness on self-record
**Source:** `app/Http/Requests/Staff/UpdateStaffRequest.php`
**Apply to:** `UpdateStaffProfileRequest` — `Rule::unique('users','email')->ignore($userId)`, with `$userId` sourced from `$this->user('users')?->id` instead of a route param.

### Test token acquisition (never `actingAs()` for revocation assertions)
**Source:** `tests/Feature/Auth/StaffLoginTest.php::test_logout_invalidates_token`
**Apply to:** `GuestLogoutTest`, `StaffPasswordChangeTest` — real `createToken()->plainTextToken` + `withToken()` + `$this->app->get('auth')->forgetGuards()` after the revoking call, before asserting 401 on reuse.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Http/Requests/Auth/ChangePasswordRequest.php` (rule combo) | model/validation | CRUD | No existing FormRequest in the codebase combines `current_password` + `confirmed` + `different`; assembled from `CreateStaffRequest`'s `min:8` plus RESEARCH.md's verified stock Laravel rule semantics |
| `docs/carlton-tree.html` node edits | config/docs | transform | Static HTML `TREE` var, not a code pattern — apply D-15's exact `ep`/`meta` values directly, no code excerpt needed |

## Metadata

**Analog search scope:** `backend/app/Services/Auth/`, `backend/app/Http/Controllers/Auth/`, `backend/app/Http/Requests/{Auth,Staff}/`, `backend/app/Base/`, `backend/routes/api.php`, `backend/lang/{en,ar}/custom.php`, `backend/tests/Feature/Auth/`
**Files scanned:** 11 read directly (full or targeted sections), 0 skipped for size (all under 200 lines)
**Pattern extraction date:** 2026-09-25
