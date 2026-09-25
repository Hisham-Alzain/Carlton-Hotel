# Codebase Concerns

**Analysis Date:** 2026-09-25

## Tech Debt

### P7 Guest Document Storage on Public Disk

**Issue:** Guest identity documents (`guest_documents` via `FileTrait`) are stored on the `public` disk (same location as CMS media). Files are stored at a UUID-scoped path (`guest/{guest_uuid}/reservation/{reservation_uuid}/filename`) which is not enumerable, but the disk itself is unauthenticated/web-servable.

**Files:** 
- `app/Models/GuestDocument.php` (uses `FileTrait` with default `public` disk)
- `app/Actions/SubmitDocumentsAction.php` (lines ~25–45, calls `storeFile()`)
- `app/Http/Requests/SubmitDocumentsRequest.php` (validates file upload)

**Impact:** If a document's URL is leaked (via logs, referrer header, shared cache headers, or misconfigured CORS), anyone with the URL can fetch an unverified guest's passport/ID scan with no authentication check. Storing sensitive identity documents on a publicly-readable disk violates security best practices for PII.

**Fix approach:** Private disk + authenticated download route (P12 hardening phase or explicit follow-up). Requires:
1. Create private disk configuration in `config/filesystems.php` 
2. Migrate `FileTrait` usage for `GuestDocument` to private disk
3. Add authenticated `GET /api/documents/{document_uuid}/download` route gated by ownership check
4. Verify HTTP headers (no `Cache-Control: public`, signed URLs if any)

**Current mitigation:** Path-scoping provides enumeration protection if leak is minimal, but this is defense-in-depth, not primary control.

---

### Untested Integration Code

#### Firebase Integration (P9)

**Issue:** `FirebaseService` (real `kreait/laravel-firebase` SDK implementation) is not exercised by the test suite. Tests rebind `FirebaseServiceInterface` to a fake, making the integration logic uncovered.

**Files:**
- `app/Services/FirebaseService.php` (lines ~30–80, `sendPush()` and `mirrorToFirestore()`)
- `app/Listeners/MirrorServiceRequestToFirestore.php`
- `app/Listeners/SendWelcomeNotification.php`
- `app/Listeners/SendRoomReadyNotification.php`

**Impact:** Silent failures if Firebase credentials are misconfigured (P0 config published, but never used until runtime in production). Push send failures, Firestore writes not reaching the mirror. No error message reaches the admin until the feature is live.

**Fix approach:** 
1. Integration test against Firebase emulator (gcloud suite) in CI
2. Production monitoring/alerting on Firebase call failures
3. Graceful degradation: catch Firebase exceptions, log, return success to caller (chat/queue still work in MySQL, just not live-mirrored)

**Current mitigation:** Dual-write pattern (MySQL is source of truth); Firestore out → app still functions but subscribers don't see live updates. Firestore in ← no feature depends on real-time writes from Firestore.

---

## Known Bugs

### (None currently documented in codebase)

All critical issues identified during review (Naive Reviewer FAIL findings) were fixed before commit:
- P4: `reservation_rooms.price_usd` storing post-promo total (fixed to subtotal_usd)
- P4: `assign-room` under read permission (moved to `reservations.create`)
- P4: `VerifyOtpAction` booking_link lacked second-factor (fixed)
- P8: TOCTOU double-settle race (fixed with transaction + lockForUpdate)
- P8: hardcoded locale in `GenerateFolioAction` (fixed to `app()->getLocale()`)

---

## Security Considerations

### Session Swap Attack on Guest Booking Verification

**Risk:** `verifyGuestBooking` endpoint (`POST /api/reservations/guest/verify`) could be exploited if a guest on one device submits an OTP meant for a different guest's booking.

**Files:**
- `app/Http/Controllers/Api/ReservationController.php::verifyGuestBooking()` (lines ~60–90)
- `app/Actions/VerifyOtpAction.php` (lines ~100–130, booking_link branch)

**Current mitigation:** Identity guard added in P4.R. The OTP identifier (phone/email) is re-checked against the reservation's linked guest contact before linking. Prevents a guest from using their own valid OTP to activate someone else's reservation.

**Verify:** Test `tests/Feature/Reservations/GuestBookingTest.php::test_verifyGuestBooking_identity_guard_prevents_session_swap` confirms the check works.

---

### Permissions Model Cannot Deny Role-Inherited Permissions

**Risk:** `spatie/laravel-permission` has no native deny (negative permissions). If a role grants permission `X` and you later need to revoke `X` for one user (exception), only direct grants can be revoked — role-inherited `X` cannot be removed without breaking the role for everyone else.

**Files:**
- `app/Services/PermissionAssignmentService.php` (lines ~40–70, `apply()` method)
- `tests/Feature/Staff/PermissionOverrideTest.php` (tests grant override, not deny)

**Impact:** Staff RBAC is less flexible for exception cases. Workaround: create a narrower role for the exception user. No tickets request deny semantics, so this is deferred.

**Fix approach (P12+):** 
1. Add `staff_permission_denials` table (user_id, permission_id)
2. Override spatie's `hasPermissionTo()` middleware check to exclude denied permissions
3. Extend test suite with deny scenarios

---

## Performance Bottlenecks

### Hand-Paginated Merged Queue (P10)

**Problem:** `OperationsQueueService::index()` pulls both `service_requests` and `tickets` tables, merges them in-memory, sorts by `created_at`, and hand-paginates the merged collection.

**Files:**
- `app/Services/OperationsQueueService.php` (lines ~50–120, `index()` method)
- `app/Http/Resources/OperationsQueueItemResource.php`

**Current capacity:** Works well with <1000 total items in both tables combined (typical for a hotel). Scales poorly if the tables exceed several thousand rows — memory usage becomes O(n), sort is full collection not database-level.

**Scaling path (P12+):**
1. Replace hand-pagination with database-level union query (`UNION` of both tables, order by DB)
2. Keep the normalizing `OperationsQueueItemResource` for shape, but apply it per-item at query time
3. Benchmark with 10K+ test rows to confirm acceptable response times

**Current mitigation:** Staff dashboard refresh is manual (`GET /operations/queue`), not poll-based, so high refresh latency is visible and fixable before it becomes a habit.

---

### Availability Checks with `whereDate()` Workaround

**Problem:** SQLite stores dates as `'2027-01-03 00:00:00'` (with time component). String comparison `'2027-01-03 00:00:00' > '2027-01-03'` evaluates TRUE, causing adjacent bookings to falsely collide.

**Files:**
- `app/Services/AvailabilityService.php` (lines ~60–90, `checkAvailability()`)
- `database/migrations/...P4...create_reservations_table.php` (uses `'date'` cast)

**Current mitigation:** `whereDate()` cast strips the time component before comparison. Works on SQLite, MySQL, PostgreSQL.

**Note:** This is a solved issue, not a bottleneck. Documented here because the root cause (date format mismatch in SQL) is subtle and easy to reintroduce if the cast is ever removed or `whereTime()` is added without care.

---

## Fragile Areas

### OTP Locking & Rate Limiting (P1)

**Files:**
- `app/Models/OtpCode.php` (constants and helper methods)
- `app/Actions/RequestOtpAction.php` (rate limiter, `lockAfter5()`)
- `app/Actions/VerifyOtpAction.php` (consume + lock check)
- `tests/Feature/Auth/OtpRateLimitTest.php`
- `tests/Feature/Auth/OtpHappyPathTest.php`

**Why fragile:** Multi-step state (expiry, consumption, lock count). If `VerifyOtpAction` is ever refactored to skip the `consumed_at` update or the lock check, the 5-attempt limit silently stops working. Same for `RequestOtpAction` rate-limiter — if Redis cache is swapped for in-memory cache without care, the limiter resets per-request.

**Safe modification:** 
1. Always wrap OTP state updates in `DB::transaction`
2. Test rate limit + lock exhaustion in every phase that touches OTP
3. Never skip the `consumed_at` write — it's the single-use enforcement
4. Document the 1/min + 5/hr rate limits in a code comment at the action's top

**Test coverage:** ✓ Green (OtpRateLimitTest, OtpFailureTest, OtpHappyPathTest all passing)

---

### Folio Idempotence (P8)

**Files:**
- `app/Actions/GenerateFolioAction.php` (lines ~20–60, idempotency logic)
- `tests/Feature/Folio/FolioTest.php::test_folio_generation_is_idempotent`
- `tests/Feature/Folio/FolioTest.php::test_settled_folio_does_not_drift_on_regeneration`

**Why fragile:** `GenerateFolioAction` is called by both guest (`POST /folio/approve`) and admin (`POST /cms/folios/{reservation}/generate`). It must be idempotent (multiple calls → same folio, not duplicates). Naive implementation would either create duplicates or fail on the second call.

**Current implementation:** `firstOrCreate` on the unique `reservation_id` FK, then delete-and-recreate `folio_items` each call. Correct but requires all items to be re-aggregated on every call — safe because the aggregation query is deterministic (no random sampling).

**Previous issues (all fixed pre-P8 commit):**
1. TOCTOU double-settle: moved settle status check inside transaction after `lockForUpdate()`
2. Hardcoded locale: changed to `app()->getLocale()` (set by P3's `SetLocale` middleware)
3. Settled-folio drift: short-circuit regeneration once status === settled

**Safe modification:**
1. Never cache folio items outside the transaction
2. Never skip the settlement status check
3. Test both happy path (new folio) and idempotent path (regenerate existing)

**Test coverage:** ✓ Green (11 tests, including idempotent + drift checks)

---

### Entitlement Gate Server-Side Resolution (P7)

**Files:**
- `app/Support/GuestEntitlement.php` (gate logic, two flags)
- `app/Http/Middleware/EnsureHasBooking.php`
- `app/Http/Middleware/EnsureIsCheckedIn.php`
- `app/Http/Resources/GuestResource.php` (same flags computed for payload)

**Why fragile:** Guest-facing routes must never trust a client-supplied `reservation_id` or `guest_id`. The `GuestEntitlement` gate resolves the guest's active reservation from the authenticated token + fresh DB query. If any route ever accepts `reservation_id` in the request body (backward compat, feature request), it bypasses the gate.

**Current invariant:** Every guest-facing POST/PATCH route receives no ID parameters from the request; all identities are resolved server-side via `GuestEntitlement::currentReservation()`.

**Examples of correct usage:**
- `PlaceServiceRequestAction` — no reservation_id parameter; resolves via `GuestEntitlement::currentReservation($guest)`
- `SubmitDocumentsAction` — no reservation_id parameter; same pattern
- `ApproveFolioAction` — no folio_id parameter (only guest has one active folio); derives from reservation

**Unsafe pattern (never use):**
```php
// ❌ WRONG — trusts client input
$reservationId = $request->input('reservation_id');
$reservation = Reservation::find($reservationId);

// ✓ CORRECT — server-side resolution
$reservation = app(GuestEntitlement::class)->currentReservation($guest);
```

**Safe modification:**
1. Add new guest-facing endpoint? Resolve identities via `GuestEntitlement`, never accept IDs from request body
2. Refactor an existing action? Verify it still calls `currentReservation()`, never adds a request parameter

**Test coverage:** ✓ Tests verify no guest can access another guest's data (implicit in permission tests, explicit in `PreArrivalTest`, etc.)

---

## Scaling Limits

### SQLite Local Development Database

**Current setup:** SQLite for local dev (`.env DB_CONNECTION=sqlite`). No schema validation, no concurrency constraints, and string-based date comparison (documented under Performance Bottlenecks above).

**Production target:** MySQL 8 (`.env.example` documents this; used in staging/prod).

**Scaling limit:** SQLite is single-user (one writer at a time). Reservation concurrency tests (`ConcurrencyTest.php`) pass only because they're single-request tests within one transaction. If two HTTP requests tried to create overlapping reservations in real-time against SQLite, the second would timeout waiting for the lock.

**Impact:** Development testing does not catch race conditions that would surface in production (MySQL) under actual concurrent load.

**Fix approach:** 
1. **For dev environment:** Switch to in-container MySQL (docker-compose, provided `.env.mysql` example)
2. **For CI:** Run tests against both SQLite (fast) and MySQL (thorough) — SQLite for unit tests, MySQL for integration tests with concurrency scenarios
3. **For load testing:** Staging uses MySQL; load tests must be run there, not against local SQLite

**Current mitigation:** `ConcurrencyTest.php` explicitly tests `lockForUpdate()` logic with the `DB::transaction` wrapper, which works correctly on both SQLite and MySQL. Tests are not marked as requiring MySQL, so they pass locally. Actual concurrent requests are unlikely to occur during development (one developer, no load).

---

## Dependencies at Risk

### Firebase SDK (`kreait/laravel-firebase`) Integration Complexity

**Risk:** Firebase configuration is published in P0 but not wired into the app until P9. If credentials are misconfigured or expired, the first sign of failure is a production outage (push notifications fail silently, Firestore mirrors don't reach live clients).

**Files:**
- `config/firebase.php` (published in P0, not touched until P9)
- `app/Services/FirebaseService.php` (P9, real implementation)
- `.env` (must include `FIREBASE_CREDENTIALS_PATH` or similar)

**Risk mitigation:**
1. **Health check route:** Add `GET /api/health/firebase` that attempts a low-stakes Firestore write + FCM send, returns `{ firebase: { connected: true/false } }`. Call this from monitoring dashboards.
2. **Graceful degradation:** All Firebase calls wrapped in try-catch. Failures log to Sentry/monitoring, but don't fail the HTTP response.
3. **Test-in-staging:** Integration test should run against actual Firebase emulator (or staging credentials) in CI before merging.

**Current status:** Interface-driven (`FirebaseServiceInterface`), allowing fake bindings in tests. Production is untested.

---

### Spatie Permission Library Version & Behavior

**Risk:** `spatie/laravel-permission` v5 was chosen in P0; if a major version is ever needed (breaking API change), staff RBAC middleware + all permission checks must be updated.

**Files:** 
- `config/permission.php` (published in P0)
- `app/Http/Middleware/Authenticate.php` (not custom, uses laravel default)
- All permission-gated routes via `Middleware\Authenticate` + route `permission:` checks

**Current protection:** Pinned to v5 in `composer.lock`. Manual updates required.

**Upgrade path (when needed):**
1. Read `spatie/laravel-permission` v6+ release notes for breaking changes
2. Update `config/permission.php` if needed
3. Test all permission-gated routes (P2 staff RBAC test suite should catch 90%+ of issues)
4. Run full `php artisan test` suite before merge

---

## Missing Critical Features

### No Negative Permissions (Deny Semantics)

**Gap:** P2's RBAC allows grant overrides per user (escalation guards on role elevation), but no deny semantics. A user in a role cannot be selectively denied a permission that role grants.

**Files:** `app/Services/PermissionAssignmentService.php` (no deny logic)

**Impact:** RBAC is less flexible for exception cases. Workaround is to create a narrower role.

**Depends on:** `spatie/laravel-permission`'s underlying model (spatie has no deny, by design)

**Not blocking:** No current use case. P2 done-condition satisfied without deny. Deferred to P12 hardening or future ticket.

---

### Service Requests Carry No Price Data

**Gap:** P7's `service_requests` have `type` and `notes` only. No price, no line-item support. P8's folio aggregation correctly excludes service_requests (they have no `price_usd`).

**Files:**
- `database/migrations/...P7...create_service_requests_table.php`
- `app/Models/ServiceRequest.php` (no price field)

**Impact:** Room-service orders, food ordering, and other priced services cannot be tracked via service_requests. If the app later needs "guest orders room-service at $XX", a new model is needed.

**Design alternative explored:** Add `amount_usd` to `service_requests`, make it nullable (for non-priced requests like housekeeping). Decision deferred to when room-service is actually built (not specified in current PLAN.md).

**Current mitigation:** Folio correctly excludes unpriced requests (no silent bugs). If priced requests are ever added, `GenerateFolioAction` will need a one-line update to include them.

---

## Test Coverage Gaps

### Integration Tests (Firebase, Payment Gateway)

**Untested area:** Real Firebase integration (sendPush, mirrorToFirestore), real payment gateway integration (assumed to always succeed in ManualDriver).

**Files:**
- `app/Services/FirebaseService.php` — integration code only
- `app/Services/PaymentGateway/ManualDriver.php` — failure path untested (P6.5 added test via interface rebinding, but live ManualDriver never fails)

**Risk:** Silent failures in production if configuration is wrong or Firebase credentials expire.

**Priority:** High (affects user-facing features — guests won't receive push notifications if Firebase is broken)

**Fix approach:**
1. `FirebaseService`: Add integration test against Firebase emulator in CI
2. `ManualDriver`: Failure path covered by P6.5's interface-rebinding test; acceptable as-is (ManualDriver is development-only, real drivers must test their own failure paths)

---

### Guest Device Behavior Under Token Expiry

**Untested area:** What happens if a guest's device token expires (Firebase revokes old tokens periodically). Current implementation re-registers a device token only if `token` value changes — if Firebase silently revokes and the client doesn't re-register, guest never receives push again without a logout/login cycle.

**Files:**
- `app/Actions/RegisterDeviceTokenAction.php` (upserts on `token` value, ignores silent revocation)
- `tests/Feature/Notifications/DeviceTokenTest.php` (no silent-revocation scenario)

**Risk:** Medium (guests see no notifications after Firebase token expiry, they need to re-login to fix it — not great UX, but visible failure, not silent data loss)

**Fix approach:** 
1. Add health-check endpoint that guest app calls on startup (compare local cached `token` against server's latest registered token; if mismatch, re-register)
2. Firebase sends `canonicalRegistrationToken` in some responses; honor that if present
3. Add test scenario: register token, simulate Firebase revocation, verify re-registration on next app startup

**Priority:** Medium (not blocking, visible failure, easy fix if app team reports it)

---

## Architectural Constraints

**Threading:** Single-threaded event loop (Laravel HTTP request-per-thread). Concurrency is request-level via database locks (`lockForUpdate`), not in-process threading.

**Global state:** Module-level singletons via `AppServiceProvider` service container (FirebaseServiceInterface, PaymentGatewayInterface, GuestEntitlementService). No mutable shared state outside of request scope.

**Circular imports:** None known. Interfaces (`FirebaseServiceInterface`, `PaymentGatewayInterface`, `ChannelAdapterInterface`) break potential cycles.

**Date/time:** All stored as UTC. Client-supplied dates normalized via `Carbon` casting in models. `SetLocale` middleware sets app locale per request; no global timezone assumption.

---

*Concerns audit: 2026-09-25*
