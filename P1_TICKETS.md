# P1 — Auth & Identity — Ticket List

All paths relative to `backend/`. Spec only — Coder (Sonnet) writes the code.
Conventions are enforced on every file (see bottom: "Global Conventions Checklist").

Ordered so each ticket compiles/tests green before the next. Groups:
A. Migrations · B. Models · C. Exceptions · D. Lang keys · E. Requests · F. Actions · G. Services · H. Resources · I. Controllers · J. Routes · K. Cleanup · L. Tests

---

## GROUP A — Migrations

### A1 — Expand `guests` table (§3.4 full field set)
**File:** `database/migrations/2026_07_08_100000_expand_guests_table.php`
(new migration — do NOT edit the P0 `create_guests_table` migration; add columns via a new one so history stays clean)

Add columns to existing `guests` table (all after `name`, all nullable unless noted):
- `phone` string, nullable, **unique** (unique-when-present; SQLite treats multiple NULLs as distinct so a plain `unique()` is safe). Primary identity.
- `phone_country` string(2), nullable — ISO 3166-1 alpha-2 (e.g. `SY`)
- `phone_verified_at` timestamp, nullable
- `email` string, nullable, **unique** (unique-when-present)
- `email_verified_at` timestamp, nullable
- `first_name` string, nullable
- `last_name` string, nullable
- `preferred_locale` string(2), nullable, default `en` — values `ar`|`en`

Indexes: `phone` and `email` already covered by unique. Add plain index on `last_name` (used by booking-code link lookup fallback).

**Application-level constraint** (NOT a DB check — SQLite can't easily): "at least one of phone/email present" is enforced in the Guest model/actions, not the migration. Add a code comment noting this.

Down: drop the added columns.

**Artisan:** `php artisan migrate` (or `migrate:fresh --seed` — see A3 note).

### A2 — Create `otp_codes` table
**File:** `database/migrations/2026_07_08_100001_create_otp_codes_table.php`

Columns:
- `id` bigint PK
- `identifier` string — E.164 phone OR normalized (lowercased, trimmed) email
- `channel` string — enum-in-code: `sms` | `whatsapp` | `email`
- `code_hash` string — bcrypt hash, never plaintext
- `purpose` string — `login` | `register` | `booking_link`
- `attempts` unsignedTinyInteger, default 0
- `expires_at` timestamp
- `consumed_at` timestamp, nullable (single-use marker)
- timestamps

Index: composite `(identifier, purpose, expires_at)` named e.g. `otp_lookup_idx`.

Down: `dropIfExists('otp_codes')`.

### A3 — Minimal `reservations` STUB migration (P1 only — P4 expands)
**File:** `database/migrations/2026_07_08_100002_create_reservations_stub_table.php`

> **STUB — mark clearly in a file-top comment:**
> `// P1 STUB — minimal reservations table so LinkBookingCodeAction can look up booking_code. P4 replaces/expands this. Do NOT build reservation logic here.`

Columns (just enough for booking-code link):
- `id` bigint PK
- `booking_code` string, **unique** — format `CARL-XXXXXX` (Crockford Base32, no I/L/O/U)
- `guest_id` unsignedBigInteger, nullable, FK → `guests.id` (nullOnDelete). Index it.
- `last_name` string, nullable
- `phone` string, nullable
- timestamps

Index: `booking_code` (unique already), `guest_id`.

Down: `dropIfExists('reservations')`.

**Note for Coder:** because this table is created in P1, `LinkBookingCodeAction` queries it directly — no table-existence guard needed. P4 will supersede this migration; leave a `// TODO(P4)` marker.

**Artisan after A1–A3:** `php artisan migrate:fresh --seed` (must stay green).

---

## GROUP B — Models

### B1 — Expand `Guest` model
**File:** `app/Models/Guest.php` (edit P0 stub)

- Keep traits: `HasApiTokens, HasFactory, HasUuid`. **Add `LogsActivity`** (guest is state-changing: verified_at, linked device).
- `$fillable`: `uuid, name, phone, phone_country, phone_verified_at, email, email_verified_at, first_name, last_name, preferred_locale`
- `$hidden`: none needed (no password).
- `casts()`: `phone_verified_at => datetime`, `email_verified_at => datetime`.
- Helper methods (return types explicit):
  - `markPhoneVerified(): void` — sets `phone_verified_at = now()`, saves.
  - `markEmailVerified(): void` — sets `email_verified_at = now()`, saves.
  - `scopeByPhone($q, string $e164)` and `scopeByEmail($q, string $email)` — for identifier lookups.
- Do NOT add `HasTranslations` (auth model, not content).
- Route-model-bind stays on `uuid` (via HasUuid).

### B2 — Create `OtpCode` model
**File:** `app/Models/OtpCode.php`

- Traits: `HasFactory` only (internal record; no UUID exposure, no activity log needed — but see note).
- `$fillable`: `identifier, channel, code_hash, purpose, attempts, expires_at, consumed_at`
- `$hidden`: `code_hash`
- `casts()`: `expires_at => datetime`, `consumed_at => datetime`, `attempts => integer`
- Constants for channel/purpose values (e.g. `const CHANNEL_SMS = 'sms'` etc., `const PURPOSE_LOGIN`, `PURPOSE_REGISTER`, `PURPOSE_BOOKING_LINK`).
- Query helpers:
  - `scopeActive($q)` — `consumed_at IS NULL AND expires_at > now()`.
  - `scopeForIdentifier($q, string $identifier, string $purpose)`.
- Instance helpers:
  - `isExpired(): bool`
  - `isConsumed(): bool`
  - `isLocked(int $maxAttempts = 5): bool` — `attempts >= $maxAttempts`.
- Note: OTP is short-lived infra data; `LogsActivity` not required.

### B3 — Update `GuestFactory`
**File:** `database/factories/GuestFactory.php` (edit)

- `definition()` returns: `first_name`, `last_name`, `name` (full), `phone` (valid E.164 e.g. `+9639` + 8 digits, unique via faker), `phone_country => 'SY'`, `email` (unique, nullable-ok but provide), `preferred_locale => 'en'`.
- States:
  - `phoneVerified()` — sets `phone_verified_at => now()`.
  - `emailVerified()` — sets `email_verified_at => now()`.
  - `emailOnly()` — `phone => null`, ensures email present.
  - `phoneOnly()` — `email => null`.

### B4 — Create `OtpCodeFactory`
**File:** `database/factories/OtpCodeFactory.php`

- `definition()`: `identifier` (E.164 phone), `channel => 'sms'`, `code_hash => Hash::make('123456')`, `purpose => 'login'`, `attempts => 0`, `expires_at => now()->addMinutes(5)`, `consumed_at => null`.
- States: `expired()` (`expires_at => now()->subMinute()`), `consumed()` (`consumed_at => now()`), `locked()` (`attempts => 5`), `emailChannel()` (`channel => 'email'`, identifier => email).

---

## GROUP C — Exceptions

All extend `App\Exceptions\DomainException`. Mirror `NotFoundException` structure exactly: implement `errorCode(): string` and `statusCode(): int`. No constructor override needed (base handles message/ctx).

### C1 — `OtpExpiredException`
**File:** `app/Exceptions/OtpExpiredException.php`
- `errorCode()` → `'otp_expired'`; `statusCode()` → `422`.

### C2 — `OtpInvalidException`
**File:** `app/Exceptions/OtpInvalidException.php`
- `errorCode()` → `'otp_invalid'`; `statusCode()` → `422`.

### C3 — `OtpLockedException`
**File:** `app/Exceptions/OtpLockedException.php`
- `errorCode()` → `'otp_locked'`; `statusCode()` → `429`.

> Issuance rate-limit reuses existing `TooManyRequestsException` (`too_many_requests`, 429). Do NOT create a new one for that.

The global handler in `bootstrap/app.php` already renders any `DomainException` via `__('custom.errors.' . errorCode())`, so no handler edits are needed — just add lang keys (Group D).

---

## GROUP D — Lang keys (add to BOTH `lang/en/custom.php` AND `lang/ar/custom.php`)

Keys go under existing sections. Coder MUST add identical key set to both files (English + Arabic translations).

**Under `errors` (new keys — must match exception errorCodes):**
- `otp_expired`
- `otp_invalid`
- `otp_locked`
- (`too_many_requests` already exists — reused for issuance limit)
- `credentials_invalid` — staff login failure (see G2)
- `account_inactive` — staff `is_active = false` (see G2)
- `identity_required` — neither phone nor email supplied to request-otp
- `booking_link_failed` — booking code + second factor did not match a reservation

**Under a NEW `auth` section:**
- `otp_sent` — "A verification code has been sent."
- `logged_in` — "Logged in successfully."
- `logged_out` — "Logged out successfully."
- `otp_verified` — "Verification successful."
- `booking_linked` — "Booking linked successfully."

**Under `validation` (new attribute-driven keys as needed):**
- `phone_invalid` — "The phone number is not valid."
- `in` — generic "The selected :attribute is invalid." (for channel/purpose enums, if not already covered)

English sample values are illustrative; Coder writes proper en + ar strings. Every user-facing string in P1 code MUST resolve to one of these keys via `__('custom.…')`.

---

## GROUP E — Requests

All extend `App\Base\BaseRequest` (`authorize()` returns true; inherit `messages()`, extend it where new rules added). All phone normalization happens in `prepareForValidation()` using `giggsey/libphonenumber-for-php`.

**Shared helper — phone normalization.** To avoid duplicating libphonenumber calls, create a small trait:
**File:** `app/Support/NormalizesPhone.php` (namespace `App\Support`)
- `protected function normalizePhone(?string $raw, ?string $countryHint = 'SY'): ?array` → returns `['e164' => '+963…', 'country' => 'SY']` or `null` if unparseable. Uses `libphonenumber\PhoneNumberUtil`. Catches `NumberParseException` and returns null (let validation rule reject).
- Used inside `prepareForValidation()` of the OTP/link requests to `merge()` the normalized phone + phone_country before rules run.

### E1 — `StaffLoginRequest`
**File:** `app/Http/Requests/Auth/StaffLoginRequest.php`
- Rules: `email` → `required|email`; `password` → `required|string`.
- No phone logic.

### E2 — `RequestOtpRequest`
**File:** `app/Http/Requests/Auth/RequestOtpRequest.php`
- Input: one of `phone` or `email`, plus `channel` (`sms|whatsapp|email`), plus `purpose` (`login|register`).
- `prepareForValidation()`: if `phone` present, normalize via `NormalizesPhone` → merge `phone` (E.164) + `phone_country`. Lowercase/trim `email` if present.
- Rules:
  - `phone` → `nullable|string` (validity enforced by normalization producing non-null; add a closure/rule rejecting when raw phone given but normalization failed → message `custom.validation.phone_invalid`).
  - `email` → `nullable|email`.
  - `channel` → `required|in:sms,whatsapp,email`.
  - `purpose` → `required|in:login,register`.
  - `after` validation hook OR a rule enforcing **at least one of phone|email** present → else fail with `custom.errors.identity_required` (use `$validator->after` adding error). Also enforce channel↔identifier coherence (email channel requires email; sms/whatsapp require phone) — reject mismatch.

### E3 — `VerifyOtpRequest`
**File:** `app/Http/Requests/Auth/VerifyOtpRequest.php`
- Input: `phone` or `email` (same normalization as E2), `code` (6 digits), `purpose`.
- `prepareForValidation()`: normalize phone / lowercase email.
- Rules: `code` → `required|string|size:6`; `purpose` → `required|in:login,register,booking_link`; identifier presence rule as in E2.

### E4 — `LinkBookingCodeRequest`
**File:** `app/Http/Requests/Auth/LinkBookingCodeRequest.php`
- Input: `booking_code` (required), plus **at least one** of `last_name` | `phone` (second factor — code alone rejected, anti-enumeration).
- `prepareForValidation()`: uppercase `booking_code` (codes are uppercase Crockford Base32); normalize `phone` if present.
- Rules:
  - `booking_code` → `required|string` + regex `/^CARL-[0-9A-HJ-NP-TV-Z]{6}$/` (Crockford: excludes I,L,O,U). Message via `custom.validation` key.
  - `last_name` → `nullable|string`.
  - `phone` → `nullable|string`.
  - `$validator->after`: require `last_name` OR `phone` present → else fail `custom.errors.booking_link_failed` (do NOT leak which factor missing).

---

## GROUP F — Actions

Actions are single-responsibility, injectable classes. **Contract: return `['data' => …, 'code' => int]`; throw domain exceptions; never read `request()`; wrap multi-writes in `DB::transaction`.** Namespace `App\Actions\Auth`.

### F1 — `RequestOtpAction`
**File:** `app/Actions/Auth/RequestOtpAction.php`
- `handle(string $identifier, string $channel, string $purpose): array`
- Steps:
  1. **Rate-limit issuance** (before creating): 1/min and 5/hour per `(identifier, purpose)`. Use Laravel `RateLimiter` facade with two limiter keys (e.g. `otp:min:{identifier}` limit 1/60s, `otp:hour:{identifier}` limit 5/3600s). On breach → throw `TooManyRequestsException`.
  2. Generate 6-digit numeric code (`str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT)`).
  3. `DB::transaction`: create `OtpCode` with `code_hash = Hash::make($code)`, `expires_at = now()->addMinutes(5)`, `attempts = 0`. (Optionally invalidate prior active codes for same identifier+purpose by setting `consumed_at = now()` — recommended to prevent multiple live codes.)
  4. **Dispatch** the code via channel: for P1, delegate to a `OtpDispatcher` (see F1a) — but do NOT block on real SMS/WhatsApp/email provider integration (that's later). Dispatcher logs / stores for test visibility.
  5. Return `['data' => ['identifier' => $identifier, 'channel' => $channel, 'expires_at' => …], 'code' => 200]`. **Never return the code.**
- Note: the plaintext code must reach the dispatcher in-memory only; never persisted plaintext, never returned in the response.

### F1a — `OtpDispatcher` (channel sender seam)
**File:** `app/Actions/Auth/OtpDispatcher.php` (or `app/Services/Otp/OtpDispatcher.php`)
- `send(string $identifier, string $channel, string $code): void`
- P1 implementation: switch on channel; for now log via `Log::info` (redact code in non-local) or write to a fake for tests. Real SMS/WhatsApp (kreait firebase / provider) wired in a later phase — leave a `// TODO(P2/P3): real provider` marker. Keep the method injectable/mockable so tests assert it was called with the right identifier+channel.

### F2 — `VerifyOtpAction`
**File:** `app/Actions/Auth/VerifyOtpAction.php`
- `handle(string $identifier, string $code, string $purpose): array`
- Steps (all lookups on active/latest code for identifier+purpose, newest first):
  1. Fetch latest `OtpCode` for `(identifier, purpose)` ordered by `id desc`. If none → throw `OtpInvalidException`.
  2. If `isConsumed()` → `OtpInvalidException`. If `isExpired()` → `OtpExpiredException`. If `isLocked(5)` → `OtpLockedException`.
  3. `Hash::check($code, $otp->code_hash)`:
     - **Fail:** `DB::transaction` increment `attempts`. If now `>= 5` → throw `OtpLockedException`; else throw `OtpInvalidException`.
     - **Success:** `DB::transaction`: set `consumed_at = now()` (single-use). Then resolve/create guest:
       - `register` purpose OR no existing guest match → create `Guest` (path 1); set the verified flag matching the identifier type (phone→`markPhoneVerified`, email→`markEmailVerified`).
       - `login` purpose with existing guest → match (path 2), mark verified.
  4. Issue Sanctum token on `guests` guard: `$guest->createToken('guest')`.
  5. Return `['data' => ['guest' => $guest, 'token' => $plainTextToken], 'code' => 200]`.
- Guest matching: by phone if identifier is E.164, by email otherwise (use `scopeByPhone`/`scopeByEmail`).

### F3 — `LinkBookingCodeAction`
**File:** `app/Actions/Auth/LinkBookingCodeAction.php`
- `handle(string $bookingCode, ?string $lastName, ?string $phone): array`
- Steps:
  1. Look up `reservations` row where `booking_code = $bookingCode` **AND** (matches `last_name` if provided OR matches `phone` if provided). **Code alone is never sufficient** — require the second factor in the WHERE clause. If no match → throw `NotFoundException` (or a domain failure mapped to `booking_link_failed`) — do NOT reveal whether the code exists (anti-enumeration; generic message).
  2. Determine the contact on the reservation (its `phone`, or the linked guest's contact) → this is where the OTP will be sent (path 3: "OTP to contact on reservation").
  3. Issue an OTP for `purpose = booking_link` to that reservation contact by delegating to `RequestOtpAction` (identifier = reservation phone, channel = sms/whatsapp). Device linking completes when the guest later calls verify-otp with `purpose=booking_link` (F2 handles booking_link by linking `reservation.guest_id` → guest and returning a guest token).
  4. Return `['data' => ['identifier_masked' => …, 'channel' => …], 'code' => 200]` — masked contact only, never full number.
- Because the `reservations` stub exists (A3), the query runs; no table guard needed. `// TODO(P4)`: richer reservation matching.
- **Note to Coder:** the booking_link branch inside `VerifyOtpAction` (F2) must, on success, set `reservations.guest_id` to the resolved/created guest so the device is linked. Keep the two actions consistent on `purpose='booking_link'`.

---

## GROUP G — Services

Services orchestrate actions + shape return payloads. Same contract (`['data'=>…, 'code'=>int]`, throw domain exceptions, no `request()`). May extend `App\Base\BaseService` only if useful; auth services are custom so a plain class is fine. Namespace `App\Services\Auth`.

### G1 — `AuthGuestService`
**File:** `app/Services/Auth/AuthGuestService.php`
- Constructor injects `RequestOtpAction`, `VerifyOtpAction`, `LinkBookingCodeAction`.
- Methods:
  - `requestOtp(string $identifier, string $channel, string $purpose): array` → delegates to F1.
  - `verifyOtp(string $identifier, string $code, string $purpose): array` → delegates to F2.
  - `linkBookingCode(string $code, ?string $lastName, ?string $phone): array` → delegates to F3.
- Thin — no business logic beyond delegation + payload assembly.

### G2 — `AuthStaffService`
**File:** `app/Services/Auth/AuthStaffService.php`
- `login(string $email, string $password): array`
  1. Find `User` by email. If none, or `Hash::check` fails → throw `UnauthorizedException` with `custom.errors.credentials_invalid` (do NOT reveal which). (Reuse existing `UnauthorizedException`; 401.)
  2. If `is_active === false` → throw `ForbiddenException` with `custom.errors.account_inactive` (403).
  3. `DB::transaction` not needed (single token create is fine, but wrap if you also touch last_login). Issue token on `users` guard: `$user->createToken('staff')`.
  4. Return `['data' => ['user' => $user, 'token' => $plainTextToken, 'permissions' => $user->getAllPermissions()->pluck('name')], 'code' => 200]`.
- `logout($user): array` — `$user->currentAccessToken()->delete()`; return `['data'=>null,'code'=>200]`.
- `me($user): array` — return `['data'=>$user, 'code'=>200]` (controller wraps in UserResource; permissions computed in resource).

---

## GROUP H — Resources

Extend `App\Base\BaseResource` (subclasses implement `toArray()`; never query inside — pass data in). Namespace `App\Http\Resources`.

### H1 — `UserResource`
**File:** `app/Http/Resources/UserResource.php`
- `toArray($request)`:
  - `id` → use `uuid` (public-facing) — expose `uuid`, NOT numeric id.
  - `name`, `email`, `type`, `is_active`.
  - `permissions` → `$this->getAllPermissions()->pluck('name')` (array of permission names). **Must be present** (done-condition: staff login returns permissions array + type).
  - `roles` → optional: `$this->getRoleNames()`.
- Note: safe to call spatie permission accessors here (they're loaded relations/cached), but prefer passing permissions from the service to avoid N+1. If computed in resource, ensure roles/permissions eager-loaded in service (`$user->load('roles','permissions')`).

### H2 — `GuestResource`
**File:** `app/Http/Resources/GuestResource.php`
- `toArray($request)`: `uuid`, `first_name`, `last_name`, `name`, `phone`, `phone_country`, `phone_verified => (bool) phone_verified_at`, `email`, `email_verified => (bool) email_verified_at`, `preferred_locale`. Do NOT expose numeric `id` or `code_hash`-type internals.

---

## GROUP I — Controllers

Extend `App\Base\BaseController`; use `respondFromService()` / `success()` for envelopes (they already inject `request_id` + `__()` message). Controllers: validate via typed Request, call service, wrap in Resource, respond. Namespace `App\Http\Controllers\Auth`.

### I1 — `StaffAuthController`
**File:** `app/Http/Controllers/Auth/StaffAuthController.php`
- Constructor injects `AuthStaffService`.
- `login(StaffLoginRequest $request)`: call `login()`, wrap user in `UserResource`, return envelope with `token` + `permissions` alongside. Message key `custom.auth.logged_in`. Shape:
  `data => ['user' => UserResource, 'token' => …, 'permissions' => [...]]`.
- `logout(Request $request)`: `->middleware('auth:users')`; call `service->logout($request->user())`; message `custom.auth.logged_out`.
- `me(Request $request)`: `->middleware('auth:users')`; return `UserResource` of `$request->user()`; message `custom.messages.success`.

### I2 — `GuestAuthController`
**File:** `app/Http/Controllers/Auth/GuestAuthController.php`
- Constructor injects `AuthGuestService`.
- `requestOtp(RequestOtpRequest $r)`: call `requestOtp(...)`; message `custom.auth.otp_sent`. Return non-sensitive payload (identifier masked, channel, expires_at). **Never** return the code.
- `verifyOtp(VerifyOtpRequest $r)`: call `verifyOtp(...)`; wrap guest in `GuestResource`; return `data => ['guest'=>…, 'token'=>…]`; message `custom.auth.otp_verified`.
- `linkBookingCode(LinkBookingCodeRequest $r)`: call `linkBookingCode(...)`; message `custom.auth.booking_linked` (or otp_sent — since it triggers an OTP). Return masked contact only.

---

## GROUP J — Routes

**File:** `routes/api.php` (edit — keep `/health`, remove probes per Group K)

Add (all under `api` group; middleware as noted). Prefix `auth`:

Staff:
- `POST /auth/login` → `StaffAuthController@login` (public)
- `POST /auth/logout` → `StaffAuthController@logout` — `middleware('auth:users')`
- `GET  /auth/me` → `StaffAuthController@me` — `middleware('auth:users')`

Guest:
- `POST /auth/guest/request-otp` → `GuestAuthController@requestOtp` (public)
- `POST /auth/guest/verify-otp` → `GuestAuthController@verifyOtp` (public)
- `POST /auth/guest/link-booking-code` → `GuestAuthController@linkBookingCode` (public)

Optional but recommended: apply Laravel `throttle` middleware to the OTP request route as a defense-in-depth layer on top of the action-level RateLimiter (e.g. `throttle:10,1`). Action-level limit remains the authoritative 1/min·5/hour gate.

Group the auth routes with `Route::prefix('auth')->group(...)` for clarity. Use controller imports (no closures).

---

## GROUP K — Cleanup (P0 probe removal)

**File:** `routes/api.php` — **DELETE all three P0 probe routes:**
- `GET /probe/domain-exception`
- `GET /probe/staff`
- `GET /probe/guest`

Also update the now-broken test **`tests/Feature/GuardsResolveTest.php`**: it currently hits `/api/probe/staff` and `/api/probe/guest`. Rewrite those assertions to use the new real endpoints:
- staff token → `GET /api/auth/me` (200)
- guest token → a guest-guarded endpoint. Since P1 has no GET guest route, either (a) keep a minimal guest-guarded route, or (b) assert guest token is rejected on `/api/auth/me` (401) and staff token accepted. **Recommended:** repurpose the test to assert guard isolation via `/api/auth/me` (staff 200, guest 401) + unauthenticated 401 with `error_code=unauthorized`. Keep the `NotFoundException` envelope coverage by moving it into `ExceptionEnvelopeTest` if it relied on `/probe/domain-exception` (verify that file; adjust if needed).

Remove the `use App\Exceptions\NotFoundException;` import from `routes/api.php` once the probe is gone (avoid unused-import).

---

## GROUP L — Tests

**Dir:** `tests/Feature/Auth/` (new). Use `RefreshDatabase`. Use factories from B3/B4. Mock/fake `OtpDispatcher` to capture the issued code (since the code is never returned) — bind a fake in the container that records `(identifier, channel, code)` so tests can read the plaintext code to verify. Alternatively, tests may create `OtpCode` rows directly with a known code + `Hash::make`.

Required cases (map 1:1 to Done-condition):

### L1 — `OtpHappyPathTest`
- `test_otp_issue_and_verify_phone_channel` — request-otp (sms) → capture code → verify-otp → 200, guest created, `phone_verified_at` set, token returned & works on guest guard.
- `test_otp_issue_and_verify_email_channel` — same via email channel; `email_verified_at` set.
- `test_returning_guest_matched_not_duplicated` — pre-existing guest by phone → verify → same guest id, no duplicate row (path 2).

### L2 — `OtpFailureTest`
- `test_expired_otp_throws_otp_expired` — OtpCode with `expires_at` past → verify → 422 `error_code=otp_expired`.
- `test_invalid_code_throws_otp_invalid` — wrong code → 422 `error_code=otp_invalid`, `attempts` incremented.
- `test_locked_after_5_attempts` — 5 wrong tries → `error_code=otp_locked` (429). Assert 6th blocked even with correct code.

### L3 — `OtpRateLimitTest`
- `test_issuance_rate_limited_per_minute` — two request-otp within 60s → second returns 429 `error_code=too_many_requests`.
- (optional) hourly cap: 6th within the hour → 429.

### L4 — `BookingCodeLinkTest`
- Seed a `reservations` stub row (`booking_code`, `last_name`, `phone`).
- `test_code_alone_rejected` — POST link with only `booking_code`, no second factor → 422/failure (validation `booking_link_failed`), no OTP issued (anti-enumeration).
- `test_code_plus_last_name_issues_otp_to_reservation_contact` — code + matching last_name → 200, OTP dispatched to reservation phone; verify-otp `purpose=booking_link` links `reservations.guest_id` and returns guest token.
- `test_wrong_second_factor_generic_failure` — code + wrong last_name → generic failure, does not reveal code validity.

### L5 — `StaffLoginTest`
- `test_login_returns_permissions_array_and_type` — seed roles/perms (existing seeder), user with role → login → `data.user.type` present, `data.permissions` is a non-empty array of names. (Done-condition.)
- `test_invalid_credentials_401` — `error_code=credentials_invalid` (or `unauthorized`), no user leak.
- `test_inactive_user_forbidden` — `is_active=false` → 403 `account_inactive`.
- `test_me_and_logout` — `me` returns UserResource with permissions; `logout` deletes token → subsequent `me` 401.

### L6 — `PhoneNormalizationTest`
- `test_phone_normalized_to_e164_on_storage` — request-otp with a locally-formatted Syrian number (e.g. `0912345678`, or with country hint) → verify → resulting `Guest.phone` stored as `+9639XXXXXXXX` (E.164) and `phone_country = 'SY'`. (Done-condition.)

**Run:** `php artisan test --filter=Auth` (and full `php artisan test` must stay green).

---

## Global Conventions Checklist (enforce on EVERY file)
- [ ] Services/Actions return `['data'=>…, 'code'=>int]` — never HTTP responses, never read `request()`.
- [ ] Throw domain exceptions; never return error arrays.
- [ ] `DB::transaction` wraps every multi-write (OTP create+invalidate, verify+consume+guest create, booking link).
- [ ] Every user-facing string via `__('custom.key')`; key added to BOTH `lang/en/custom.php` and `lang/ar/custom.php`.
- [ ] Public-facing models use `HasUuid`; route-model-bind on `uuid`; resources expose `uuid`, never numeric `id`.
- [ ] State-changing models use `LogsActivity` (Guest gains it; OtpCode exempt as infra).
- [ ] Every FK + frequent where-column indexed (`otp_codes(identifier,purpose,expires_at)`, `reservations.guest_id`, `guests.last_name`, unique `guests.phone/email`).
- [ ] OTP: hashed (bcrypt via `Hash::make`), single-use (`consumed_at`), 5-min TTL, 5-attempt lock, plaintext never persisted/returned.
- [ ] Phone E.164 normalization only in Request `prepareForValidation()` (via `NormalizesPhone` + giggsey/libphonenumber); services/actions receive already-normalized identifiers.
- [ ] Booking-code: second factor (last_name|phone) mandatory in the WHERE clause; code alone always rejected; failures generic (anti-enumeration).
- [ ] `HasTranslations` NOT applied to auth models.

## Artisan command summary (run in order)
1. After Group A migrations written: `php artisan migrate:fresh --seed` (green).
2. After each group compiles: `php artisan test` (green).
3. Final: `php artisan migrate:fresh --seed && php artisan test` — all green = P1 done.

## Suggested execution order for the Coder
A1 → A2 → A3 → B1 → B2 → B3 → B4 → C1–C3 → D → E(support trait first, then E1–E4) → F1a → F1 → F2 → F3 → G1 → G2 → H1 → H2 → I1 → I2 → J → K → L1–L6.
