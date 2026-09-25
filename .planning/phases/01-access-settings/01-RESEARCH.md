# Phase 1: Access & Settings - Research

**Researched:** 2026-09-25
**Domain:** Laravel 13 / Sanctum auth extension — staff self-service profile/password, guest logout
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Profile fields & email change:**
- D-01: Staff self-service profile edits `name` and `email` only. No migration: `users` table stays `name, email, password, type, is_active`. `type`, `is_active`, roles and permissions remain admin-only via `PUT /staff/{staff}`. Dashboard language switch stays client-side; no `locale` column.
- D-02: Changing `email` requires `current_password` in the same `PUT /auth/profile` request, validated with Laravel's `current_password:users` rule. When submitted email equals stored one (or omitted), `current_password` is not required. Email uniqueness uses `Rule::unique('users','email')->ignore($currentUserId)` exactly like `UpdateStaffRequest`.
- D-03: `GET /auth/profile` returns the same `StaffResource` that `GET /auth/me` returns (uuid, name, email, type, is_active, roles, effective/direct/role permissions). `PUT /auth/profile` returns the updated `StaffResource` with message key `custom.messages.profile_updated` (already exists).

**Password change policy:**
- D-04: `PUT /auth/password` body: `current_password`, `password`, `password_confirmation`. Rules: `current_password` → `required|string|current_password:users`; `password` → `required|string|min:8|confirmed|different:current_password`. `min:8` matches `CreateStaffRequest`; no stricter complexity rule.
- D-05: On success, delete every Sanctum token of the user except the current access token (revoke other devices, keep the current session). Response `data: null`, message key `custom.messages.password_changed` (new, en + ar).
- D-06: `PUT /auth/password` is throttled `throttle:5,1` (keyed per authenticated user). Rationale: this endpoint verifies a secret and reveals whether it was right, so it is a brute-force vector against the highest-value accounts. `PUT /auth/profile` inherits the same throttle only for the email-change path is NOT required; keep profile un-throttled.
- D-07: Both writes run through the service (`AuthStaffService`), not the controller; password hashing relies on the `User` model's `hashed` cast. Activity logging stays on the existing `LogsActivity` trait; `password` must never appear in activity properties.

**Guest logout scope:**
- D-08: `POST /auth/guest/logout` lives inside the existing `auth:guests` block under `prefix('guest')` (tier-2: any guest token, no `has_booking`/`is_checked_in` gate). It revokes the current access token only, mirroring `AuthStaffService::logout` including its null-guard fallback (delete all tokens when `currentAccessToken()` is null). Response `data: null`, message key `custom.auth.logged_out` (already exists).
- D-09: Optional `device_token` (string, max 500) in the body. When present and a `device_tokens` row with that token belongs to the authenticated guest, delete the row so FCM pushes stop; unknown tokens or tokens owned by another guest are ignored silently (still 200). Without `device_token`, device tokens are untouched. This is derived, so the planner may keep it but must not widen it (no "delete all device tokens" variant).
- D-10: A second logout with an already-revoked token returns the guard's normal 401 `unauthenticated`; no idempotency special-casing.

**Error codes & responses:**
- D-11: Wrong `current_password` on either endpoint → 422 `validation_failed` with `errors.current_password` carrying a localized message (new key `custom.validation.current_password`, en + ar). No new domain exception and no new `error_code`.
- D-12: Email already taken → 422 `validation_failed` with `errors.email`. Missing/expired token → 401 `unauthenticated`. Deactivated staff cannot reach these routes because `StaffService::deactivate` revokes tokens in the same transaction, so no extra `is_active` check is added here.
- D-13: No new `error_code` values and no new permissions in this phase (routes are gated by the guard only). XCUT-01 is satisfied by stating "no new permissions" in the phase summary; DOCS-01 still applies.
- D-14: Route placement: `GET/PUT /auth/profile` and `PUT /auth/password` go inside the existing `Route::middleware('auth:users')` group under `Route::prefix('auth')` (routes/api.php ~lines 102-105); `POST /auth/guest/logout` inside the existing `auth:guests` group under `prefix('guest')` (~lines 112-115). Controllers: `StaffAuthController` gains `profile`, `updateProfile`, `changePassword`; `GuestAuthController` gains `logout`. Requests under `app/Http/Requests/Auth/` (`UpdateStaffProfileRequest`, `ChangePasswordRequest`, `GuestLogoutRequest`).

**Docs & tree (DOCS-01):**
- D-15: Update `backend/docs/API_GUIDE_DASHBOARD.md` (Auth module: add `GET/PUT /auth/profile`, `PUT /auth/password`), `backend/docs/API_GUIDE_MOBILE.md` (Auth module: add `POST /auth/guest/logout` incl. optional `device_token`), the Postman collection, and flip two nodes in `docs/carlton-tree.html`: "guest sign out" → `api:true`, `ep:["POST /auth/guest/logout"]`, meta "token revoked on sign out"; "dashboard settings" → `api:true`, `ep:["GET/PUT /auth/profile","PUT /auth/password"]`, meta "profile + password endpoints · language switch stays client-side".

### Claude's Discretion
- Whether `PUT /auth/password` returns `data: null` (default) or `{ "revoked_sessions": n }`.
- Exact new lang key names, as long as they sit in the existing `messages` / `validation` / `auth` groups of `lang/{en,ar}/custom.php` and both locales are filled.
- Keep the logic in `AuthStaffService` / `AuthGuestService` (both are small) rather than new Actions, unless the service would exceed the guide's ~300-line threshold.
- Test file naming, e.g. `tests/Feature/Auth/StaffProfileTest.php`, `StaffPasswordChangeTest.php`, `GuestLogoutTest.php`, following the existing `test_snake_case` style and `withToken($this->staffToken(...))` helpers.
- Whether the throttle on `PUT /auth/password` keys by user id via `throttle:5,1` default resolution or an explicit named limiter; either is acceptable if the test proves the 6th call in a minute returns 429 `too_many_requests`.

### Deferred Ideas (OUT OF SCOPE)
- Staff `phone`, `locale` (server-side persistence of the dashboard language switch) and `avatar` on the profile — would need a migration and FileTrait wiring.
- Returning a `revoked_sessions` count from the password change (default is `data: null`).
- "Sign out everywhere" for guests (delete all tokens and all device tokens) — not requested today.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ACCESS-01 | Guest can sign out; current Sanctum token revoked (`POST /auth/guest/logout`) | `AuthStaffService::logout()` pattern to mirror in `AuthGuestService`; `GuestAuthController` wiring; D-08/D-09/D-10; Sanctum token-deletion mechanics verified from vendor source (see Sanctum Specifics) |
| ACCESS-02 | Staff view/update own profile (`GET/PUT /auth/profile`) | `UpdateStaffRequest`'s `Rule::unique(...)->ignore()` pattern; resource-shape discrepancy flagged in Open Questions (UserResource vs StaffResource); `current_password:users` rule verified from framework source |
| ACCESS-03 | Staff change password with current-password check, other tokens revoked | D-04/D-05/D-06; `hashed` cast on `User::password`; token-revoke-except-current pattern; throttle precedent (`StaffLoginThrottleTest`) |
| DOCS-01 | API guides, Postman, `docs/carlton-tree.html` updated per phase | Exact doc section formats captured below; exact TREE node lines identified |
| XCUT-01 | New permissions follow `{domain}.{action}`, seeded, listed in summary | N/A this phase — explicitly no new permissions (D-13); still must state this in the phase summary |
</phase_requirements>

## Summary

This phase is a narrow, no-migration extension of an already-working staff/guest Sanctum auth system. All four new endpoints are additive routes inside existing `auth:users` / `auth:guests` route groups, reusing the existing `AuthStaffService` / `AuthGuestService` → Controller → FormRequest → Resource layering. No new tables, no new permissions, no new packages. The two genuinely non-trivial pieces are (1) getting Sanctum token semantics exactly right for "revoke this token but not others" and for guest logout, and (2) filling three previously-unused Laravel validation rules (`current_password`, `different`, `confirmed`) into the project's localization pipeline, which today does not map any of them.

Two concrete risks were found by reading source, not by assumption. First, `Laravel\Sanctum\HasApiTokens::currentAccessToken()` returns whatever was set via `withAccessToken()`, and plain PHPUnit `actingAs($model, $guard)` never calls it — so tests written with `actingAs()` (the pattern used throughout `GuestProfileTest`/`GuestMeTest`) will see `currentAccessToken()` as `null`, silently falling into the *null-guard fallback* (delete-all-tokens) branch instead of exercising "revoke just this token." Guest logout and password-change tests MUST issue and send a real bearer token via `withToken()`, exactly like `StaffLoginTest::test_logout_invalidates_token` and `StaffLoginThrottleTest`, not `actingAs()`. Second, `BaseRequest::messages()` — the map that routes every FormRequest rule to a `custom.validation.*` key for AR/EN/FR/TR/ES — has no entry for `current_password`, `different`, or `confirmed` today (grep-verified empty), and neither locale file has these keys. Left unmapped, a wrong-current-password 422 in a non-English locale would return the raw translation key instead of a sentence, which is exactly the bug class `ValidationMessageLocalizationTest` exists to catch. The plan must add these three mappings plus `custom.validation.current_password` (new; `different`/`confirmed` can reuse Laravel's stock wording pattern, still needs a `custom.validation.*` key per the BaseRequest contract) to both locale files.

**Primary recommendation:** Extend `AuthStaffService`/`AuthGuestService` and their controllers in place (no new services/actions unless line-count forces it), add 3 new `Http/Requests/Auth/*` classes mirroring `UpdateStaffRequest`'s uniqueness pattern, write tests with **real bearer tokens** (never `actingAs()`) for every token-revocation assertion, and treat the `BaseRequest::messages()` + locale-file gap for `current_password`/`different`/`confirmed` as a required task, not an afterthought — the phase's own contract gate (`AR/EN keys exist`) hard-fails without it.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Guest logout (token revocation) | API / Backend | Database (token row delete) | Sanctum token lives in `personal_access_tokens`; controller/service only orchestrate |
| Staff profile read/update | API / Backend | Database (users table) | Standard CRUD-on-self; no client-side state beyond the returned resource |
| Password change | API / Backend | Database (token rows + users.password) | Verification, hashing, and mass token revocation must happen server-side; client only sends plaintext once over TLS |
| Device-token deregistration on logout | API / Backend | Database (device_tokens table) | Purely a server-side cleanup of push-notification routing state |
| Docs/tree/Postman sync | Documentation (out of runtime tiers) | — | Static artifacts updated alongside code, not a runtime concern |

## Standard Stack

### Core
No new packages. This phase uses only what's already installed:

| Library | Version (verified) | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^13.8 (composer.json) [VERIFIED: backend/composer.json] | Validation rules (`current_password`, `different`, `confirmed`), routing, testing | Already the project's framework |
| laravel/sanctum | ^4.0 (composer.json) [VERIFIED: backend/composer.json] | Token issuance/revocation for both guards | Already wired for staff + guest auth |
| spatie/laravel-permission | (existing, unchanged) | `HasRoles` trait already on `User` | No permission changes this phase (D-13) |
| spatie/laravel-activitylog | (existing, unchanged) | `LogsActivity` trait already on `User`; must exclude `password` | Already the project's audit mechanism |

### Supporting
None new.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel's built-in `current_password:{guard}` rule | A custom `Rule` object doing `Hash::check()` manually | Rejected — CONTEXT.md D-02/D-04 lock the stock rule; framework source confirms it resolves the named guard and checks `guard->user()->getAuthPassword()`, which is exactly what's needed |
| `$user->tokens()->where('id','!=',$currentId)->delete()` for "revoke others" | `spatie/laravel-personal-access-tokens` or similar wrapper package | No such package needed — Sanctum's own `tokens()` relation (from `HasApiTokens`) is a plain `MorphMany`, trivially filterable |

**Installation:** None — no new packages.

**Version verification:** Confirmed directly from `backend/composer.json`: `"php": "^8.3"`, `"laravel/framework": "^13.8"`, `"laravel/sanctum": "^4.0"`. [VERIFIED: backend/composer.json]

## Package Legitimacy Audit

Not applicable — this phase installs zero new packages. No legitimacy check required.

## Architecture Patterns

### System Architecture Diagram

```
Guest app / Dashboard
        │
        │ Bearer <token>
        ▼
routes/api.php  ── Route::prefix('auth') ──┬── auth:users group ──► StaffAuthController
        │                                   │        ├─ login (existing)
        │                                   │        ├─ logout (existing)
        │                                   │        ├─ me (existing)
        │                                   │        ├─ profile()        [NEW: GET]
        │                                   │        ├─ updateProfile()  [NEW: PUT, throttle none]
        │                                   │        └─ changePassword() [NEW: PUT, throttle:5,1]
        │                                   │
        │                                   └── prefix('guest') → auth:guests group ──► GuestAuthController
        │                                            ├─ me / updateProfile (existing)
        │                                            └─ logout()         [NEW: POST]
        ▼
FormRequest (validates + normalizes)
        │  UpdateStaffProfileRequest / ChangePasswordRequest / GuestLogoutRequest
        ▼
Service (business logic, no request() access)
        │  AuthStaffService::updateProfile() / changePassword()
        │  AuthGuestService::logout()
        ▼
Model + Sanctum token table
        │  User (hashed cast) ─┬─ tokens() [MorphMany PersonalAccessToken]
        │  Guest ──────────────┘
        │  DeviceToken (deleted on matching guest logout w/ device_token)
        ▼
Resource (response shaping)
        │  StaffResource / UserResource (see Open Questions — shape ambiguity)
        ▼
BaseController::success() → envelope { success, message, data, request_id }
```

### Recommended Project Structure
No new folders. Files touched/added:
```
app/Http/Controllers/Auth/
├── StaffAuthController.php   # + profile, updateProfile, changePassword methods
└── GuestAuthController.php   # + logout method

app/Http/Requests/Auth/
├── UpdateStaffProfileRequest.php   # NEW
├── ChangePasswordRequest.php       # NEW
└── GuestLogoutRequest.php          # NEW

app/Services/Auth/
├── AuthStaffService.php   # + updateProfile(), changePassword()
└── AuthGuestService.php   # + logout()

app/Base/BaseRequest.php   # + messages() entries: current_password, different, confirmed

lang/en/custom.php, lang/ar/custom.php   # + messages.password_changed, validation.current_password (+ different/confirmed if not silently OK)

routes/api.php   # 4 new route lines inside existing groups

tests/Feature/Auth/
├── StaffProfileTest.php          # NEW
├── StaffPasswordChangeTest.php   # NEW
└── GuestLogoutTest.php           # NEW

backend/docs/API_GUIDE_DASHBOARD.md, API_GUIDE_MOBILE.md, backend/docs/postman/*, docs/carlton-tree.html
```

### Pattern 1: Reusing the existing `logout()` null-guard fallback for guest logout
**What:** `AuthStaffService::logout()` already implements the exact pattern D-08 asks for:
```php
// Source: backend/app/Services/Auth/AuthStaffService.php (existing code, read directly)
public function logout(User $user): array
{
    $token = $user->currentAccessToken();
    if ($token) {
        $token->delete();
    } else {
        // Fallback: delete all tokens for this user (safe — one active session)
        $user->tokens()->delete();
    }
    return ['data' => null, 'code' => 200];
}
```
**When to use:** Mirror this verbatim in `AuthGuestService::logout(Guest $guest)`, swapping the type hint. `Guest` already has `HasApiTokens` (confirmed in `app/Models/Guest.php`), so `currentAccessToken()`/`tokens()` are available identically.
**Then:** wire D-09's optional `device_token` cleanup as a second, independent step after the token delete — do not conditionally skip the token delete based on device_token presence/absence.

### Pattern 2: "Revoke all tokens except the current one" (password change, D-05)
**What:** Sanctum's `HasApiTokens` trait exposes `tokens()` as a plain `MorphMany` (confirmed by reading `vendor/laravel/sanctum/src/HasApiTokens.php`) — there is no built-in "revoke others" helper, so this is a one-line query:
```php
// Pattern derived from AuthStaffService::logout() + verified Sanctum MorphMany relation
public function changePassword(User $user, string $newPassword): array
{
    $current = $user->currentAccessToken();

    DB::transaction(function () use ($user, $newPassword, $current) {
        $user->update(['password' => $newPassword]); // 'hashed' cast on User handles hashing
        $user->tokens()
            ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))
            ->delete();
    });

    return ['data' => null, 'code' => 200];
}
```
**When to use:** Inside `AuthStaffService`, called from the controller after `ChangePasswordRequest` validates `current_password` + `password` (`confirmed`, `different:current_password`). Wrap in `DB::transaction` per this project's transactionality convention (multi-step write: password update + token deletes).
**Caveat:** `whereKeyNot()` requires the token model's key column — Sanctum's `PersonalAccessToken` primary key is `id`; `whereKeyNot($current->getKey())` is safe and framework-idiomatic. If `$current` is null (see Pitfall 1 below), the `when()` guard means *all* tokens are deleted (equivalent to "sign out everywhere"), which only happens for whatever edge case leaves `currentAccessToken()` null — verify this can't happen on the two production-realistic requests (real bearer token via `auth:users` middleware always sets it).

### Pattern 3: Conditional `current_password` requirement (D-02)
**What:** `current_password` is only `required` when `email` is being changed to a *different* value than the one on file.
```php
// Illustrative — verify against actual UpdateStaffProfileRequest field access patterns in UpdateStaffRequest
public function rules(): array
{
    $user = $this->user('users');
    $emailChanging = $this->filled('email') && $this->input('email') !== $user->email;

    return [
        'name'  => ['sometimes', 'string', 'max:255'],
        'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        'current_password' => [$emailChanging ? 'required' : 'sometimes', 'string', 'current_password:users'],
    ];
}
```
**When to use:** `UpdateStaffProfileRequest`. Note `rules()` cannot easily depend on itself being called twice cheaply — computing `$emailChanging` once at the top of `rules()` is the standard FormRequest idiom already used in this codebase's own `UpdateStaffRequest`/`UpdateGuestProfileRequest`.

### Anti-Patterns to Avoid
- **Testing token revocation with `actingAs()`:** `actingAs($user, 'guests')` (the pattern already used in `GuestProfileTest`/`GuestMeTest`) never calls `withAccessToken()`, so `currentAccessToken()` is `null` in the controller under test. A "guest logout revokes only this token" test written this way silently degrades into testing the null-guard (delete-all) branch and would pass even if the "revoke just current" logic were broken. **Always** use `Guest::factory()->create()` + `$guest->createToken(...)->plainTextToken` + `$this->withToken($token)` for any test asserting selective token revocation (this is exactly `StaffLoginTest::test_logout_invalidates_token`'s existing pattern).
- **Skipping `BaseRequest::messages()` for new rules:** Adding `current_password`/`different`/`confirmed` to a FormRequest's `rules()` without adding matching entries to `BaseRequest::messages()` produces raw translation keys (`"validation.current_password"`) in non-English locales — the exact regression `ValidationMessageLocalizationTest` was written to catch. The PR checklist (`developer-guide.md` §17) already requires "If you added a new exception type, the translation key exists" — the same discipline applies to new validation rule names.
- **Deriving the Resource class from CONTEXT.md's prose without checking current code:** see Open Questions — CONTEXT.md's field list for `/auth/profile` matches `StaffResource`'s actual fields, but the class it's compared to (`GET /auth/me`) currently returns `UserResource`, whose fields differ. Do not assume the two are already the same shape.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Verifying the caller's current password | A custom `Hash::check($request->current_password, $user->password)` call in a controller/service | Laravel's `current_password:users` validation rule | Already the locked decision (D-02/D-04); framework source confirms it resolves the `users` guard and checks `getAuthPassword()` — identical semantics, zero custom code, and it fires during validation (422) rather than requiring a manual check + custom exception |
| Confirming the new password was typed twice | Manual `$request->password === $request->password_confirmation` check | Laravel's `confirmed` rule (expects `password_confirmation` field) | Standard Laravel convention; needs only a `BaseRequest::messages()` + locale-key addition, no new logic |
| Ensuring the new password differs from the old one | Manual string comparison in the service | Laravel's `different:current_password` rule | Runs at validation time against the sibling `current_password` field before either reaches the service — same reasoning as above |
| Revoking "all tokens but this one" | A loop over `$user->tokens` calling `->delete()` individually with an `if ($token->id !== $current->id)` skip | `$user->tokens()->whereKeyNot($current->getKey())->delete()` (single query) | One SQL statement instead of N; matches this codebase's set-based-query convention already documented in PITFALLS.md (grid/board N+1 discipline extends naturally here) |

**Key insight:** Every mechanism this phase needs (secret verification, confirmation matching, selective token revocation) is a native Laravel/Sanctum primitive already available in the installed versions (`^13.8` / `^4.0`). The only genuine "build" work is wiring FormRequest → Service → localization, not inventing auth mechanics.

## Runtime State Inventory

Not applicable — this is not a rename/refactor/migration phase. No existing runtime state (stored data, live service config, OS-registered state, secrets, build artifacts) is being renamed or moved. New state introduced (Sanctum token rows, `device_tokens` rows) is created/deleted through normal application code paths, not migrated.

## Common Pitfalls

### Pitfall 1: `actingAs()` masks token-revocation bugs (VERIFIED from Sanctum + Laravel source)
**What goes wrong:** A feature test uses `$this->actingAs($guest, 'guests')` to set up an authenticated request, then asserts the guest's token is revoked after calling logout. Because `actingAs()` never calls `$guest->withAccessToken(...)`, `$guest->currentAccessToken()` is `null` inside the controller, so `AuthGuestService::logout()`'s null-guard fallback (`$user->tokens()->delete()`) runs instead of "delete just the current token." The test can pass even when the "revoke just this token" code path is completely broken, because there's no real token row to check against in the first place.
**Why it happens:** `HasApiTokens::currentAccessToken()` (read directly from `vendor/laravel/sanctum/src/HasApiTokens.php`) simply returns the `$accessToken` property, which is `null` unless `withAccessToken()` was called. Laravel's own `actingAs()` test helper only calls `Auth::guard($guard)->setUser($user)` — it has no knowledge of Sanctum tokens. (`Sanctum::actingAs()` is a *different* helper that does set a token — but it sets a Mockery mock, not a real DB row, so it's unsuitable for "does deleting revoke DB access" assertions either.)
**How to avoid:** For every test asserting selective token revocation (guest logout ACCESS-01 criterion 1, staff password-change ACCESS-03 criterion 3), create a real token via `$model->createToken('t')->plainTextToken` and send it via `$this->withToken($token)`, never `actingAs()`. This is already the codebase's own pattern in `StaffLoginTest::test_logout_invalidates_token` and `GuardsResolveTest`.
**Warning signs:** A test file for this phase using `actingAs($guest, 'guests')` anywhere near an assertion about token counts or "other tokens now 401."

### Pitfall 2: New validation rules ship without AR/EN localization (project-specific, grep-verified)
**What goes wrong:** `current_password`, `different`, and `confirmed` are used in new FormRequests (D-04) but `App\Base\BaseRequest::messages()` has no entries for them (confirmed empty by grep across `backend/app/Base/BaseRequest.php`), and neither `lang/en/custom.php` nor `lang/ar/custom.php` has `validation.current_password` (also grep-confirmed absent). A non-English (or even English, if Laravel's own `validation.php` translation is missing for these keys in some locale) 422 response would surface `"validation.current_password"` as the literal error string instead of a sentence.
**Why it happens:** This project intentionally does NOT use Laravel's own `lang/{locale}/validation.php` for user-facing strings — `BaseRequest::messages()` is the single source of truth, audited by `ValidationMessageLocalizationTest`. Every rule used in a FormRequest for the first time is a manual addition to that map.
**How to avoid:** Add `current_password`, `different`, and `confirmed` entries to `BaseRequest::messages()` (following the exact `__('custom.validation.X', ['attribute' => ':attribute'])` pattern already used for every other rule), and add the corresponding `custom.validation.*` keys to both `lang/en/custom.php` and `lang/ar/custom.php`. D-11 explicitly requires the `current_password` key; `different`/`confirmed` need the same treatment even though CONTEXT.md doesn't name them explicitly, because they are new rule names introduced by this phase.
**Warning signs:** A manual `POST`/`PUT` test with `Accept-Language: ar` and a deliberately wrong `current_password` returning an `errors.current_password` value that reads `"validation.current_password"` rather than an Arabic sentence.

### Pitfall 3: Resource-shape ambiguity between `/auth/me` and the new `/auth/profile` (see Open Questions)
**What goes wrong:** CONTEXT.md's D-03 says `/auth/profile` returns "the same StaffResource that GET /auth/me returns," and lists fields (`effective/direct/role permissions`, plural/split) — but the actual, currently-shipped `StaffAuthController::me()` returns `UserResource` (single `permissions` array + `is_super_admin`), not the `StaffResource` class (which has `effective_permissions`/`direct_permissions`/`role_permissions` and no `is_super_admin`). Building `/auth/profile` against whichever resource is picked without reconciling this could either (a) diverge from `/auth/me`'s actual existing shape, breaking the "same as /auth/me" promise, or (b) change `/auth/me`'s shape too, which is a contract break for existing dashboard clients (Pitfall 10 in project PITFALLS.md — "existing endpoints subtly break the API contract").
**Why it happens:** CONTEXT.md was written from a general description of "the staff resource shape" without diffing the two actual resource classes.
**How to avoid:** Before implementing, the planner/executor must explicitly pick one: (1) reuse `UserResource` for `/auth/profile` (byte-for-byte matches today's `/auth/me`, safest for contract stability), or (2) migrate both `/auth/me` and `/auth/profile` to `StaffResource` deliberately (a small scope increase beyond what CONTEXT.md locked, and a breaking change to `/auth/me`'s existing shape that needs sign-off per the PR checklist's "new error code" analog for shape changes). Recommendation: **use `UserResource`** for `/auth/profile` since that's what "the same as GET /auth/me returns" concretely means today, and treat the field-list parenthetical in D-03 as imprecise. Flag this to the user/planner rather than silently choosing.
**Warning signs:** A `PUT /auth/profile` test asserting `data.effective_permissions` (StaffResource-only field) exists while `GET /auth/me` in the same suite still only returns `data.permissions` (UserResource-only field) — that mismatch is the tell.

## Code Examples

Verified patterns from this codebase's own source (all read directly, not inferred):

### Existing staff logout (template for guest logout, `AuthStaffService::logout`)
```php
// Source: backend/app/Services/Auth/AuthStaffService.php (read directly)
public function logout(User $user): array
{
    $token = $user->currentAccessToken();
    if ($token) {
        $token->delete();
    } else {
        $user->tokens()->delete();
    }
    return ['data' => null, 'code' => 200];
}
```

### Existing email-uniqueness pattern to mirror (`UpdateStaffRequest`)
```php
// Source: backend/app/Http/Requests/Staff/UpdateStaffRequest.php (read directly)
public function rules(): array
{
    $userId = $this->route('user')?->id;
    return [
        'name'  => 'sometimes|string|max:255',
        'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
    ];
}
```
For `/auth/profile`, `$userId` becomes `$this->user('users')?->id` (the authenticated user, not a route-bound model).

### `current_password` rule resolution (verified from framework source)
```php
// Source: vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php (read directly)
protected function validateCurrentPassword($attribute, $value, $parameters)
{
    $auth = $this->container->make('auth');
    $hasher = $this->container->make('hash');
    $guard = $auth->guard(Arr::first($parameters)); // 'users' for this phase
    if ($guard->guest()) {
        return false;
    }
    return $hasher->check($value, $guard->user()->getAuthPassword());
}
```
Confirms `current_password:users` targets the `users` guard defined in `config/auth.php` — `'users' => ['driver' => 'sanctum', 'provider' => 'users']` (read directly), which resolves correctly since these routes already run under `auth:users` middleware.

### Sanctum's `HasApiTokens` — confirms `tokens()` is a plain relation
```php
// Source: vendor/laravel/sanctum/src/HasApiTokens.php (read directly)
public function tokens()
{
    return $this->morphMany(Sanctum::$personalAccessTokenModel, 'tokenable');
}
public function currentAccessToken()
{
    return $this->accessToken; // null unless withAccessToken() was called
}
```

### Existing throttle-with-comment convention (mirror for `PUT /auth/password`)
```php
// Source: backend/routes/api.php (existing login route, read directly)
Route::post('/login', [StaffAuthController::class, 'login'])->middleware('throttle:10,1');
```
D-06 asks for `throttle:5,1` for password change (tighter than login's `10,1`, since this endpoint is authenticated-user-keyed rather than IP-keyed by default — confirm keying via a per-user named limiter or accept default IP-keying if the test only needs to prove a 6th call 429s, per Claude's Discretion).

### Test pattern for throttle 429 (mirror `StaffLoginThrottleTest`)
```php
// Source: backend/tests/Feature/Auth/StaffLoginThrottleTest.php (read directly, adapt LIMIT=5)
for ($attempt = 1; $attempt <= self::LIMIT; $attempt++) {
    $this->withToken($token)->putJson('/api/auth/password', $wrongCurrentPasswordPayload)
         ->assertStatus(422); // wrong current_password, not yet throttled
}
$this->withToken($token)->putJson('/api/auth/password', $wrongCurrentPasswordPayload)
     ->assertStatus(429)
     ->assertJsonPath('error_code', 'too_many_requests');
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| N/A | N/A | — | This phase does not touch a deprecated pattern; it extends a system already built with current Laravel 13/Sanctum 4 conventions. |

**Deprecated/outdated:** None identified — the existing auth code (`AuthStaffService`, `AuthGuestService`) already follows current Laravel 13 idioms (typed `casts()` method, constructor property promotion, `hashed` cast instead of manual `Hash::make()`).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `PUT /auth/profile` should use `UserResource` (matching today's actual `/auth/me` shape), not the class literally named `StaffResource`, despite CONTEXT.md's D-03 wording | Common Pitfalls #3, Architecture Patterns | If wrong, either `/auth/profile` ships a different shape than `/auth/me` (contradicts D-03's stated intent) or `/auth/me` gets silently reshaped, which is an undocumented breaking change to an existing, already-consumed endpoint |
| A2 | `different`/`confirmed` validation rule names need new `custom.validation.*` keys even though CONTEXT.md's D-11 only explicitly names `current_password` | Common Pitfalls #2 | If the planner skips `different`/`confirmed` localization, `ValidationMessageLocalizationTest`-style manual QA (or a future audit) would find raw translation keys in the `errors` map for non-English locales, failing the phase's own "AR/EN keys exist" contract gate |
| A3 | An explicit named rate limiter is not required — default `throttle:5,1` keying (by IP, or by authenticated user id if Laravel resolves it that way under `auth:users`) is sufficient to satisfy D-06's test requirement | Code Examples (throttle) | Low risk — CONTEXT.md's Claude's Discretion section explicitly allows either approach; only matters if the test's 6th-call-429 assertion needs a specific keying strategy to pass deterministically in a shared-IP CI environment |

## Open Questions

1. **Which Resource class does `GET/PUT /auth/profile` actually return — `UserResource` or `StaffResource`?**
   - What we know: `StaffAuthController::me()` currently returns `UserResource` (uuid, name, email, type, is_active, is_super_admin, roles, single `permissions` array). A separate class literally named `StaffResource` exists under `app/Http/Resources/StaffResource.php` with a different field set (effective_permissions, direct_permissions, role_permissions — no is_super_admin), used elsewhere (likely `GET/PUT /staff/{staff}` in Staff Management).
   - What's unclear: CONTEXT.md D-03 names "StaffResource" but describes fields that don't match that class — they more closely match... actually neither matches exactly (StaffResource lacks `is_super_admin`, has 3-way permission split; UserResource has `is_super_admin`, single permission list). Neither class exactly matches D-03's parenthetical field list.
   - Recommendation: Before planning task breakdown, confirm with the user/PM whether `/auth/profile` should byte-match `/auth/me`'s current `UserResource` output (safest, zero risk to existing contract) or intentionally introduce a new shape. Default to `UserResource` reuse absent clarification, and note the deviation from D-03's literal wording in the phase summary.

2. **Does `PUT /auth/profile`'s email-change path need the same `throttle:5,1` as password change?**
   - What we know: D-06 explicitly says "PUT /auth/profile inherits the same throttle only for the email-change path is NOT required; keep profile un-throttled" (locked decision — profile stays un-throttled).
   - What's unclear: Nothing — this is resolved, listed here only to confirm the plan should NOT add throttle middleware to `/auth/profile`.
   - Recommendation: No action needed; documented for planner visibility only.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Backend runtime | ✓ | ^8.3 (composer.json) [VERIFIED] | — |
| Laravel Framework | Routing, validation, testing | ✓ | ^13.8 (composer.json) [VERIFIED] | — |
| Laravel Sanctum | Token issuance/revocation | ✓ | ^4.0 (composer.json) [VERIFIED] | — |
| SQLite (`:memory:`) | Test database | ✓ (phpunit.xml, per TESTING.md) | — | — |
| Composer CLI | Dependency/version checks | ✓ | — | — |

No missing dependencies — this phase needs nothing beyond what's already installed and configured.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.12 [per .planning/codebase/TESTING.md] |
| Config file | `backend/phpunit.xml` |
| Quick run command | `php artisan test --filter=StaffProfileTest` (per new test class) |
| Full suite command | `php artisan test` (must stay green — 250+ existing tests are the regression net per PITFALLS.md Pitfall 10) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ACCESS-01 (happy) | Guest logout revokes current token; second `/auth/guest/me` call with same token returns 401 | feature | `php artisan test --filter=test_guest_logout_invalidates_token` | ❌ Wave 0 — new `tests/Feature/Auth/GuestLogoutTest.php` |
| ACCESS-01 (401) | Unauthenticated call to `POST /auth/guest/logout` returns 401 | feature | `php artisan test --filter=test_unauthenticated_guest_cannot_logout` | ❌ Wave 0 |
| ACCESS-01 (device_token) | Logout with a `device_token` owned by the guest deletes that row; unknown/foreign token is silently ignored (still 200) | feature | `php artisan test --filter=test_logout_deregisters_owned_device_token` / `test_logout_ignores_foreign_device_token` | ❌ Wave 0 |
| ACCESS-01 (idempotency, D-10) | Second logout with an already-revoked token returns plain 401 `unauthenticated` (no special-casing) | feature | `php artisan test --filter=test_second_logout_with_revoked_token_returns_401` | ❌ Wave 0 |
| ACCESS-02 (happy read) | `GET /auth/profile` returns the staff's own resource | feature | `php artisan test --filter=test_staff_can_view_own_profile` | ❌ Wave 0 — new `tests/Feature/Auth/StaffProfileTest.php` |
| ACCESS-02 (happy update) | `PUT /auth/profile` updates name/email, returns updated resource + `custom.messages.profile_updated` | feature | `php artisan test --filter=test_staff_can_update_name_and_email` | ❌ Wave 0 |
| ACCESS-02 (401) | Unauthenticated `GET/PUT /auth/profile` returns 401 | feature | `php artisan test --filter=test_unauthenticated_cannot_access_profile` | ❌ Wave 0 |
| ACCESS-02 (422 duplicate email) | Changing email to one already in use returns 422 with `errors.email` | feature | `php artisan test --filter=test_duplicate_email_returns_422` | ❌ Wave 0 |
| ACCESS-02 (422 wrong current_password on email change) | Email change without/with wrong `current_password` returns 422 with `errors.current_password` | feature | `php artisan test --filter=test_email_change_requires_correct_current_password` | ❌ Wave 0 |
| ACCESS-03 (happy) | Correct `current_password` + valid new password changes password; old sessions except current return 401, current session stays 200 | feature | `php artisan test --filter=test_password_change_revokes_other_sessions` | ❌ Wave 0 — new `tests/Feature/Auth/StaffPasswordChangeTest.php` |
| ACCESS-03 (422 wrong current) | Wrong `current_password` returns 422 with `errors.current_password` | feature | `php artisan test --filter=test_wrong_current_password_returns_422` | ❌ Wave 0 |
| ACCESS-03 (422 confirmed/different) | Mismatched confirmation or new password equal to current returns 422 | feature | `php artisan test --filter=test_password_confirmation_mismatch_returns_422` / `test_new_password_same_as_current_returns_422` | ❌ Wave 0 |
| ACCESS-03 (401) | Unauthenticated `PUT /auth/password` returns 401 | feature | `php artisan test --filter=test_unauthenticated_cannot_change_password` | ❌ Wave 0 |
| ACCESS-03 (429) | 6th call within a minute returns 429 `too_many_requests` | feature | `php artisan test --filter=test_password_change_is_throttled` | ❌ Wave 0 — mirror `StaffLoginThrottleTest.php` structure |
| DOCS-01 | AR/EN keys exist for all new messages/validation strings | unit/manual | `php artisan test --filter=ValidationMessageLocalizationTest` (extend or add sibling assertions for the 3 new endpoints) plus manual review of `lang/{en,ar}/custom.php` diff | Existing file — extend if the pattern is reused for this phase's endpoints |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=<NewTestClass>`
- **Per wave merge:** `php artisan test` (full suite — enforced by project convention, not optional)
- **Phase gate:** Full suite green before `/gsd-verify-work`; also re-run `GuestProfileTest`, `GuestMeTest`, `StaffLoginTest`, `GuardsResolveTest` explicitly since this phase touches shared controllers those tests exercise.

### Wave 0 Gaps
- [ ] `tests/Feature/Auth/StaffProfileTest.php` — covers ACCESS-02
- [ ] `tests/Feature/Auth/StaffPasswordChangeTest.php` — covers ACCESS-03 (including a throttle test class or section, mirroring `StaffLoginThrottleTest.php`)
- [ ] `tests/Feature/Auth/GuestLogoutTest.php` — covers ACCESS-01
- [ ] `BaseRequest::messages()` additions (`current_password`, `different`, `confirmed`) — no test framework gap, but a required source change before any 422 test in a non-default locale can pass meaningfully
- [ ] No new factories needed — `User::factory()` and `Guest::factory()` (existing) cover every test scenario; `DeviceToken::factory()` likely already exists for D-09's tests (verify at implementation time)

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Sanctum bearer tokens (existing); `current_password:users` re-verifies identity before a sensitive change (password/email) |
| V3 Session Management | yes | Token revocation on logout and on password change (D-05/D-08); Sanctum `personal_access_tokens` table is the session store |
| V4 Access Control | yes (minimal) | Route-guard-only gating (`auth:users`/`auth:guests`) — no permission middleware needed since these are self-service, "my own record" operations (D-13) |
| V5 Input Validation | yes | FormRequest classes (`UpdateStaffProfileRequest`, `ChangePasswordRequest`, `GuestLogoutRequest`) — never hand-rolled; `Rule::unique()->ignore()` prevents email collisions |
| V6 Cryptography | yes | Password hashing via `User`'s `'password' => 'hashed'` Eloquent cast (Laravel's `Hash` facade under the hood) — never hand-rolled hashing |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Credential stuffing against `PUT /auth/password` (an authenticated endpoint that reveals whether a guessed current password was right) | Spoofing / Information Disclosure | `throttle:5,1` (D-06) — tighter than login's `10,1` since the attacker already holds a valid session and is only guessing the password, a narrower and higher-value target |
| Session fixation via a stale token after password change | Elevation of Privilege | D-05's "revoke all tokens except current" closes any session opened before the legitimate owner noticed a compromise and changed their password |
| Guest token replay after logout (the exact bug this phase fixes — "guest sign out ... token never revoked" per current `docs/carlton-tree.html`) | Spoofing | `POST /auth/guest/logout` actually deletes the Sanctum token row (ACCESS-01) — closing a documented, currently-real gap |
| Email enumeration via the 422 `errors.email` "already taken" message | Information Disclosure (minor, already accepted by the codebase) | Not newly introduced — `UpdateStaffRequest` already reveals this for staff-to-staff email changes; this phase's self-service email change carries identical, already-accepted risk profile, no new mitigation needed per existing precedent |

## Sources

### Primary (HIGH confidence — read directly this session)
- `backend/app/Services/Auth/AuthStaffService.php`, `AuthGuestService.php` — existing logout/me/updateProfile logic
- `backend/app/Http/Controllers/Auth/StaffAuthController.php`, `GuestAuthController.php` — existing controller wiring
- `backend/app/Http/Requests/Auth/UpdateGuestProfileRequest.php`, `backend/app/Http/Requests/Staff/UpdateStaffRequest.php`, `CreateStaffRequest.php` — validation patterns to mirror
- `backend/app/Http/Resources/StaffResource.php`, `backend/app/Http/Resources/UserResource.php` — resource shape discrepancy (Open Question 1)
- `backend/app/Models/User.php`, `Guest.php`, `DeviceToken.php` — traits, casts, fillable
- `backend/routes/api.php` (lines 1-150) — exact route group structure
- `backend/lang/en/custom.php`, `lang/ar/custom.php` (full files) — existing message/validation/auth key groups
- `backend/tests/TestCase.php`, `StaffLoginTest.php`, `StaffLoginThrottleTest.php`, `GuestProfileTest.php`, `GuardsResolveTest.php`, `ValidationMessageLocalizationTest.php` — test conventions and the `actingAs()` vs `withToken()` distinction
- `backend/app/Base/BaseController.php`, `BaseRequest.php` — envelope and message-mapping mechanics
- `backend/database/seeders/RolesAndPermissionsSeeder.php` — confirms no new permission scaffolding needed
- `vendor/laravel/sanctum/src/HasApiTokens.php`, `TransientToken.php`, `Sanctum.php` — VERIFIED `currentAccessToken()`/`actingAs()` semantics
- `vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php` — VERIFIED `current_password` rule implementation
- `backend/config/auth.php` — VERIFIED guard names (`users`, `guests`)
- `backend/composer.json` — VERIFIED `php ^8.3`, `laravel/framework ^13.8`, `laravel/sanctum ^4.0`
- `backend/docs/API_GUIDE_DASHBOARD.md`, `API_GUIDE_MOBILE.md` — exact doc section formats (headers, request/response tables, failure tables)
- `docs/carlton-tree.html` — exact current node text for "guest sign out" and "dashboard settings"
- `backend/.claude/skills/tupcode-laravel-backend/references/developer-guide.md` §17 (PR checklist), throttle guidance

### Secondary (MEDIUM confidence)
- `.planning/research/SUMMARY.md`, `.planning/research/PITFALLS.md` — milestone-level research (Pitfall 10: API contract breakage, directly relevant to Open Question 1)
- `.planning/codebase/CONVENTIONS.md`, `TESTING.md` — mapped conventions

### Tertiary (LOW confidence)
None used — every claim in this document traces to a file read this session or a locked CONTEXT.md decision.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — verified directly against `composer.json`; no new packages
- Architecture: HIGH — based on direct inspection of existing controllers/services/routes, an internal-extension question, not an ecosystem survey
- Pitfalls: HIGH — both critical pitfalls (actingAs/currentAccessToken, missing validation localization) verified by reading vendor source and grepping the actual codebase, not assumed from training knowledge

**Research date:** 2026-09-25
**Valid until:** 30 days (stable internal codebase extension; no external API drift risk)
