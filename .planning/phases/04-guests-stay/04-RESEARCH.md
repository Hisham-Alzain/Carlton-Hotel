# Phase 4: Guests & Stay - Research

**Researched:** 2026-09-26
**Domain:** Laravel 13 / PHP 8.3 hotel PMS — guest directory, staff notes, guest preferences, online check-in, and a display-only "digital key" credential
**Confidence:** HIGH (all claims below verified by reading the actual working tree at commit `74c625e`; no web research was needed or performed — this is an internal-codebase-convention phase, not a new-library phase)

## Summary

Phase 4 is almost entirely new code sitting on top of existing, well-understood scaffolding (`GuestEntitlement`, `StayService`, `CheckInApproval`, `SubmitDocumentsAction`, `BaseFilter`/`BaseService`/`BaseController`). Nothing in this phase requires a new Composer package — every mechanism the locked decisions call for (`encrypted` cast, `random_int`, `hash_hmac`, `Schedule::command`, `Rule::enum()->except()`) is native to Laravel 13 / PHP 8.3, but **none of them has been used anywhere in this codebase before**, so there is no in-repo precedent to copy for the digital-key and preferences work — this is the one part of the phase that is genuinely novel, not a "reuse the pattern from Phase N" job.

The two riskiest structural facts this research surfaced, both load-bearing for planning:

1. **`App\Traits\LogsActivity` fixes `getActivitylogOptions()` with a comment stating "the trait's options are fixed, so no model adds its own logExcept()."** D-08 and D-11 both require a per-model `logExcept()` override on `Guest` and `Reservation`. This is possible — a class method on `Guest`/`Reservation` takes precedence over a trait method of the same name — but the override must **reconstruct the whole `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()` chain and add `->logExcept([...])` to it**, not call into the trait's version (PHP has no `parent::` for trait-to-class override). The plan must call this out explicitly as a documented, deliberate deviation from the stated project convention, per D-08/D-11.
2. **`Reservation` has no `checkInApproval()` relation yet** (`CheckInApproval` only exposes `reservation(): BelongsTo`), and `GuestEntitlement::currentReservation()` still resolves via `sortByDesc('check_in')->first()` — the exact `FA-06-1` carry-forward from Phase 3, explicitly NOT fixed in this phase (D-03 keeps the guest-app uploader on `currentReservation()` and defers alignment). The new `targetReservation()` method (D-03) must be added alongside, not replace, the existing method.

**Primary recommendation:** Build guest directory/profile/notes/preferences as declarative CRUD-adjacent code on the existing `Base*` layer (new `GuestService` extends `BaseService`, `GuestFilter` extends `BaseFilter` with an `apply()` override for the derived `stay_status`); build online check-in and the digital key as a small, self-contained `Support\StayPayload` + three actions (`SubmitOnlineCheckInAction`, `IssueDigitalKeyAction`, `RevokeDigitalKeyAction`) wired into the three existing hook points (`ApproveCheckInAction`, `CancelReservationAction`, a new listener on `ReservationCheckedOut`) plus one new scheduled command modeled on `ReleaseExpiredHolds`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Guest directory search/list (`GET /guests`) | API / Backend | Database | `GuestFilter` + `GuestService` on `BaseService`; derived `stay_status` computed in PHP from an eager-loaded reservation set, not stored |
| Guest profile composition (`GET /guests/{guest}`) | API / Backend | Database | One service method assembling `StayPayload`/`PreArrivalChecklist`/notes/preferences from eager-loaded relations; no new read-model table |
| Guest notes (create/list) | API / Backend | Database | New `GuestNote` model + `AddGuestNoteAction`; append-only, no edit/delete surface |
| Guest preferences (guest + staff write) | API / Backend | Database | One `UpdateGuestPreferencesAction` shared by both routes; guest route sits in the existing `auth:guests` tier-2 group |
| Online check-in submission | API / Backend | Database | `SubmitOnlineCheckInAction` on the existing `auth:guests` `stays` group; hotel-local business date via `HotelClock` (already built, Phase 3) |
| Digital key issuance/revocation | API / Backend | Database (encrypted column) | Lifecycle-bound to `ApproveCheckInAction`/`CancelReservationAction`/`ReservationCheckedOut` listener/scheduled sweep — never a client-controlled write |
| Digital key exposure to guest app | API / Backend | Browser/Mobile (display only) | Guest stay resources (`UpcomingStayResource`, `ActiveStayResource`, `CheckInStatusResource`) gain a `digital_key` key with `Cache-Control: no-store`; the mobile client only displays it, never verifies it (no lock hardware in scope) |
| Pre-arrival checklist | API / Backend | — | Pure derivation (`PreArrivalChecklist::for(Reservation)`), never persisted, computed from already-loaded relations |

## Package Legitimacy Audit

**Not applicable this phase.** No new Composer or npm package is introduced. Every mechanism the locked decisions require — `Illuminate\Database\Eloquent\Casts\AsEncryptedCollection`/the `encrypted` cast type (native since Laravel 7), `random_int` (PHP core), `hash_hmac` (PHP core `ext-hash`, always available), `Illuminate\Support\Facades\Schedule::command()` (native since Laravel 5.6), `Illuminate\Validation\Rules\Enum::except()` (native since Laravel 10) — ships with the already-installed `laravel/framework` 13.8 and PHP 8.3 runtime. `composer.lock` requires no change. `Rule::enum(BedType::class)->except([BedType::EXTRA])` was verified against Laravel 13's `Illuminate\Validation\Rules\Enum` API, which has had `->except()`/`->only()` since Laravel 10 — confirm the installed version supports it with `composer show laravel/framework` before writing the rule (expected: yes, project already pins 13.8) `[CITED: composer.json/composer.lock, laravel/framework ^13.8]`.

## Standard Stack

### Core (all already installed; no version changes)

| Library | Version (installed) | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.8 | `encrypted` cast, `Schedule::command`, `Rule::enum()->except()`, `firstOrCreate` | Already the project runtime; every new mechanism here is native, verified against the installed version `[VERIFIED: composer.json — backend/composer.json pins laravel/framework ^13.8]` |
| spatie/laravel-activitylog | 5.0 | `LogsActivity` trait, `logExcept()` | Already installed; `logExcept()` is a documented `LogOptions` builder method available on 5.0 `[CITED: spatie/laravel-activitylog README, v5 LogOptions API]` |
| spatie/laravel-permission | 8.3 | New `guests.view`/`guests.edit` permissions, `reception`/`concierge` presets | Already installed and used by every prior phase's permission work |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| (none new) | — | — | This phase adds zero new dependencies |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHP `encrypted` cast + `hash_hmac` lookup hash for the digital key | A dedicated encryption/KMS library (e.g. `paragonie/halite`) | Unnecessary — Laravel's `encrypted` cast already uses `APP_KEY`-derived AES-256-CBC via `Illuminate\Encryption\Encrypter`; the project has no KMS integration and this is a display-only credential (D-11 explicitly documents it as NOT lock-grade) |
| `random_int` + custom alphabet for the key code | `Str::random()` / `Str::uuid()` | Rejected by the locked decision (D-11) precisely because `Str::random()` draws from a wider alphabet that includes visually ambiguous characters (`0`/`O`, `1`/`l`); D-11's `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` alphabet is deliberately ambiguity-free for a code a human reads off a screen |

**Installation:** None required — `composer install` already provides everything.

**Version verification:** `laravel/framework` pinned at `^13.8` in `backend/composer.json`, confirmed installed via `composer.lock` presence in the repo; no `composer update` needed for this phase `[VERIFIED: backend/composer.json]`.

## Architecture Patterns

### System Architecture Diagram

```
Staff dashboard                          Guest app (mobile)
     │                                          │
     │ GET /guests?search=&stay_status=         │ PATCH /auth/guest/preferences
     │ GET /guests/{guest}                      │ POST /stays/{r}/online-check-in
     │ POST /guests/{guest}/notes               │ GET /stays/upcoming|active|status
     │ PATCH /guests/{guest}/preferences         │
     ▼                                          ▼
┌─────────────────────┐              ┌──────────────────────┐
│ GuestController      │              │ GuestAuthController   │
│ (auth:users +        │              │ StayController         │
│  permission:guests.*)│              │ (auth:guests, tier-2) │
└──────────┬───────────┘              └──────────┬────────────┘
           │                                     │
           ▼                                     ▼
   ┌───────────────┐                     ┌───────────────────────┐
   │ GuestService    │◄───reads/writes───►│ SubmitOnlineCheckInAction│
   │ GuestFilter     │                     │ UpdateGuestPreferencesAction│
   │ AddGuestNoteAction│                   └───────────┬───────────┘
   └───────┬─────────┘                                 │
           │                              ┌────────────▼─────────────┐
           │ reads via                    │ CheckInApproval::firstOrCreate│
           │ Support\StayPayload           │ (pending, never downgrades)  │
           │ Support\PreArrivalChecklist   └────────────┬─────────────┘
           ▼                                             │
   ┌─────────────────────────────────────────────────────▼─────────┐
   │ Reservation (+ rooms, folio, documents, checkInApproval [new]) │
   │ Guest (+ notes [new], preferences [new columns])               │
   └──────────────────────────────┬──────────────────────────────────┘
                                   │
              ┌────────────────────┼────────────────────────┐
              ▼                    ▼                         ▼
     ApproveCheckInAction   CancelReservationAction   ReservationCheckedOut event
     (approved → issue key) (any cancel → revoke)     (listener → revoke, reason=checked_out)
              │                    │                         │
              └────────────┬───────┴─────────────┬───────────┘
                            ▼                     ▼
                   IssueDigitalKeyAction   RevokeDigitalKeyAction
                            │
                            ▼
              reservations.digital_key_code (encrypted, hidden)
              reservations.digital_key_hash (sha256/HMAC, indexed)
                            │
                            ▼
         Scheduled command (routes/console.php, modeled on
         ReleaseExpiredHolds) sweeps expired keys nightly/hourly
```

### Recommended Project Structure

```
app/Http/Controllers/Admin/GuestController.php          # NEW — BaseController, custom verbs (index/show/notes/preferences)
app/Services/Guest/GuestService.php                      # NEW — extends BaseService
app/Filters/GuestFilter.php                               # NEW — extends BaseFilter, apply() override for stay_status
app/Http/Requests/Guest/{AddGuestNoteRequest,UpdateGuestPreferencesRequest}.php  # NEW
app/Http/Resources/Guest/{GuestProfileResource,GuestNoteResource,GuestPreferencesResource}.php  # NEW
app/Models/GuestNote.php                                   # NEW — HasUuid, HasFactory, no LogsActivity
app/Actions/Guest/{AddGuestNoteAction,UpdateGuestPreferencesAction}.php  # NEW
app/Actions/Booking/SubmitOnlineCheckInAction.php          # NEW
app/Actions/Booking/{IssueDigitalKeyAction,RevokeDigitalKeyAction}.php  # NEW
app/Support/{StayPayload,PreArrivalChecklist}.php          # NEW — shared builders feeding every resource
app/Events/CheckInApproved.php                             # NEW — ShouldDispatchAfterCommit
app/Listeners/{SendCheckInApprovedNotification,RevokeDigitalKeyOnCheckOut}.php  # NEW
app/Exceptions/OnlineCheckInClosedException.php             # NEW
app/Enums/{PillowType,FloorPreference}.php                  # NEW
app/Console/Commands/ExpireDigitalKeys.php                  # NEW — modeled on ReleaseExpiredHolds
database/migrations/{..._create_guest_notes_table,..._add_preferences_to_guests_table,..._add_online_check_in_to_reservations_table}.php  # NEW
database/factories/GuestNoteFactory.php                    # NEW
tests/Feature/Guests/{GuestDirectoryTest,GuestProfileTest,GuestNotesTest,GuestPreferencesTest}.php  # NEW
tests/Feature/Stays/{OnlineCheckInTest,DigitalKeyLifecycleTest}.php  # NEW
tests/Unit/Guest/*.php                                      # NEW
```

### Pattern 1: Filter with a derived, non-column condition in `apply()`

**What:** `RoomFilter` (existing, `app/Filters/RoomFilter.php`) shows the minimal shape of a `BaseFilter` subclass: declare `$safeParms`, `$searchable`, `$sortable`, done — no override needed when every condition maps directly to a column. `GuestFilter` needs more: `stay_status` is not a column, it is computed by joining/filtering against `reservations`. Precedent for a filter doing non-trivial work in `apply()` does not exist verbatim in this codebase (every current filter is column-only) — this is new ground, built on the same base class.
**When to use:** Any list endpoint whose most useful filter is a derived business state, not a stored column.
**Example:**
```php
// Source: app/Base/BaseFilter.php (apply() is the override point — read before writing GuestFilter)
class GuestFilter extends BaseFilter
{
    protected array $searchable = ['name', 'first_name', 'last_name', 'phone', 'email'];
    protected array $safeParms = [
        'phone' => ['eq', 'like'],
        'email' => ['eq', 'like'],
        'preferred_locale' => ['eq', 'in'],
    ];
    protected array $sortable = ['name', 'last_name', 'created_at'];

    public function apply(Builder $query): Builder
    {
        parent::apply($query); // conditions + search + sort, unchanged
        $this->applyStayStatus($query); // new: 422 on an unknown value, EXISTS-subquery scoping
        return $query;
    }
}
```
D-02 requires an unknown `stay_status` value to 422 as `validation_failed` on the `stay_status` param — reuse `BaseFilter::reject()` (`protected`, callable from a subclass) rather than inventing a new exception path.

### Pattern 2: Model-level `getActivitylogOptions()` override alongside a trait that "fixes" it

**What:** `App\Traits\LogsActivity` declares a concrete `getActivitylogOptions()` and the project's own `config/activitylog.php` comment says no model adds its own `logExcept()`. D-08 (`Guest`) and D-11 (`Reservation`) both require exactly that. A class method with the same name as a trait method silently wins in PHP — no error, no warning — but the model's override must **restate the whole option chain**, because there is no way to call "the trait's version" from an overriding class method (traits are compile-time mixins, not a base class `parent::` target).
**When to use:** Only for `Guest` and `Reservation` in this phase — do not generalize this into a project-wide pattern change; document it as a one-off, phase-scoped deviation in the phase summary so the next reader is not confused by two different activity-log configurations across models.
**Example:**
```php
// Source: app/Traits/LogsActivity.php (existing, unmodified) + this phase's Guest override
class Guest extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid, LogsActivity;

    // Overrides the trait method of the same name — PHP resolves class methods
    // before trait methods, so this wins with no `parent::` available.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept(['preferences_other', 'pillow_type']); // D-08
    }
}
```

### Pattern 3: Encrypted, hidden, non-fillable column written via `forceFill`

**What:** No model in this codebase uses the `encrypted` cast today (`grep -rn "encrypted" app/Models` returns nothing) `[VERIFIED: backend/app/Models — grep, no matches]`. D-11 requires `digital_key_code` to be `encrypted`-cast, listed in `$hidden`, and explicitly **excluded** from `$fillable` (written only via `forceFill()` inside `IssueDigitalKeyAction`, never through mass-assignment from a request).
**When to use:** Any column holding a display credential or secret that must survive at rest encrypted and never appear in a `toArray()`/`json_encode()` unless deliberately re-included.
**Example:**
```php
// Source: Laravel 13 Eloquent casts docs (encrypted cast) — no in-repo precedent to copy
protected function casts(): array
{
    return [
        // ...existing casts...
        'digital_key_code'        => 'encrypted',
        'digital_key_issued_at'   => 'datetime',
        'digital_key_expires_at'  => 'datetime',
        'digital_key_revoked_at'  => 'datetime',
    ];
}

// In IssueDigitalKeyAction — never via $fillable/mass assignment:
$reservation->forceFill([
    'digital_key_code' => $code,               // encrypted at rest by the cast
    'digital_key_hash' => hash_hmac('sha256', $code, config('app.key')),
    'digital_key_issued_at' => now(),
    'digital_key_expires_at' => $expiresAtUtc,
    'digital_key_revoked_at' => null,
    'digital_key_revoked_reason' => null,
])->save();
```

### Pattern 4: High-entropy, ambiguity-free code generation

**What:** No `random_int`-based code generator exists in this codebase yet. D-11's alphabet (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`, 32 chars, no `0/O/1/I/L`) at 12 characters is `log2(32) * 12 ≈ 60` bits of entropy.
**Example:**
```php
// Source: PHP core (random_int is CSPRNG-backed since PHP 7); no in-repo precedent
private function generateCode(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $raw = '';
    for ($i = 0; $i < 12; $i++) {
        $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return implode('-', str_split($raw, 4)); // XXXX-XXXX-XXXX
}
```

### Pattern 5: Scheduled sweep command, modeled on `ReleaseExpiredHolds`

**What:** `routes/console.php` registers scheduled commands with `Schedule::command('signature')->everyFiveMinutes()` / `->dailyAt(...)`. `app/Console/Commands/ReleaseExpiredHolds.php` is the exact shape to copy: thin `Command` class, injects an Action, calls `->handle()`, reports a count.
**Example:**
```php
// Source: backend/app/Console/Commands/ReleaseExpiredHolds.php (existing, verbatim shape)
class ExpireDigitalKeys extends Command
{
    protected $signature = 'stays:expire-digital-keys';
    protected $description = 'Revoke digital keys whose expiry has passed';

    public function handle(RevokeExpiredDigitalKeysAction $action): int
    {
        $revoked = $action->handle();
        $this->info("Revoked {$revoked} expired digital key(s).");
        return Command::SUCCESS;
    }
}
// routes/console.php: Schedule::command('stays:expire-digital-keys')->everyFifteenMinutes();
```
Frequency is Claude's discretion per CONTEXT.md (not locked) — `everyFiveMinutes()` matches the existing hold-release cadence and keeps the "expires at hotel checkout time" promise reasonably tight without over-polling; document the chosen cadence in the phase summary.

### Anti-Patterns to Avoid

- **Calling `GuestEntitlement::currentReservation()` for the profile's "current/next reservation":** D-03 explicitly requires a *new* method (`targetReservation()`) with different semantics (in-house first, else next arrival ascending, never `sortByDesc`). Do not repurpose or mutate the existing method — 8 other call sites (`FA-06-1`) depend on its current behavior and are explicitly out of scope this phase.
- **Reusing `GuestResource` for the staff profile response:** D-04 mandates a distinct `GuestProfileResource`, never reused on guest routes — a test must assert this. `GuestResource` (guest `me`) and `GuestProfileResource` (staff `GET /guests/{guest}`) diverge in what they expose (staff sees notes-adjacent stats, checklist, key metadata; guest never sees another guest's data by construction of the route).
- **Adding `guest_notes` to any guest-facing response:** D-07 requires a test proving `GET /auth/guest/me` and `GET /stays/*` carry no `notes` key. Do not let `Guest::notes()` get eager-loaded anywhere on the guest-auth path.
- **Letting the digital key code leak through any secondary channel:** D-11 lists FCM payload, Firestore mirror, Postman env refresh output, `activity_log` (issue/revoke/forceFill), and `toArray()`/`json_encode()` as leakage surfaces requiring one consolidated test. `CheckInApprovalResource` (staff) and `CheckInApproved` notification body must never carry the code — the notification text is "key is ready in the app," never the value itself.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Guest search across name/phone/email | Custom `LIKE` chains in the controller | `BaseFilter::$searchable` + `applySearch()` (already handles case-insensitivity + LIKE-escaping across SQLite/MySQL) | Already solved, already tested (`RoomFilter`, `ReservationFilter` precedent) |
| Pagination defaults/ceiling | A new `per_page` clamp | `BaseService::resolvePerPage()` (default 15, max 100) | Already the project-wide contract every index route follows |
| Encryption of the digital key | A custom AES wrapper or a third-party crypto library | Laravel's `encrypted` Eloquent cast (`Illuminate\Encryption\Encrypter`, backed by `APP_KEY`) | Already configured, already the project's only encryption mechanism (`APP_KEY` env var), avoids introducing a second crypto dependency for one column |
| Verifying an old encrypted value after a key rotation | A manual `try/catch` around raw `Crypt::decrypt` scattered at call sites | One documented `DecryptException` catch inside the accessor/action layer, treating a decrypt failure as "no active key" (per D-11) | Keeps the failure mode centralized and matches `APP_PREVIOUS_KEYS` rotation support Laravel already ships |
| Hash lookup for the key (fast equality check without decrypting) | Storing the raw code in a second plaintext column "for lookups" | `hash_hmac('sha256', $code, config('app.key'))` stored in `digital_key_hash`, indexed | Avoids a second plaintext copy of the credential while still allowing an indexed lookup path if a future verify endpoint needs one |
| Checklist as stored state | A `pre_arrival_checklist` table with boolean columns kept in sync on every write | `App\Support\PreArrivalChecklist::for(Reservation)` computed at read time from already-loaded relations | D-05 explicitly locks this as derived, never stored — matches the project's own "derived state over stored flags" convention already used for room occupancy (Phase 2) |

**Key insight:** Every "don't hand-roll" item in this phase is really the same instruction restated: this codebase has a strong existing convention (`BaseFilter`/`BaseService` for queries, derived-state-over-flags for computed status, Laravel's own crypto primitives for secrets) — the risk here is not missing a library, it's an agent inventing a bespoke mechanism where a native Laravel feature already does the job, because no in-repo example exists to copy from directly.

## Common Pitfalls

### Pitfall 1: `LogsActivity` trait override silently does nothing if class method placement is wrong

**What goes wrong:** A developer adds a `logExcept()` call somewhere expecting it to merge with the trait's defaults, or defines `getActivitylogOptions()` in a way Spatie's trait resolution doesn't pick up.
**Why it happens:** The trait method and the class override have the same name; PHP resolves the class's own method first (this works), but a common mistake is calling `parent::getActivitylogOptions()` from inside the override, which does not work here because `LogsActivity` is a trait, not a parent class — this throws a fatal "Call to undefined method" at runtime, only caught if a test actually triggers a logged write.
**How to avoid:** The override must fully reconstruct `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->logExcept([...])` — copy the trait's exact chain, do not call `parent::`.
**Warning signs:** A test that updates `preferences_other` and asserts the activity log excludes it — if this test is missing, the override could be silently wrong (over- or under-excluding) with no signal until a PII audit.

### Pitfall 2: `GuestEntitlement::currentReservation()` vs. the new `targetReservation()` get conflated

**What goes wrong:** A developer "helpfully" changes `currentReservation()` (used by `PreArrivalService`, `StayController`, and 6 other call sites per `FA-06-1`) to match the new in-house-then-next-arrival semantics D-03 wants for the profile, thinking it's the same concept.
**Why it happens:** Both methods answer "what reservation matters right now for this guest" — but `currentReservation()`'s existing `sortByDesc('check_in')->first()` semantics are relied on by 8 call sites this phase does not touch, and changing it is explicitly deferred (`FA-06-1`, carried to a future phase).
**How to avoid:** Add `targetReservation()` as a genuinely new method (on `GuestEntitlement` or `GuestService`, per D-03's discretion) that does not call or wrap `currentReservation()`. Do not delete, rename, or alter `currentReservation()`'s existing behavior in this phase.
**Warning signs:** Any diff touching `GuestEntitlement::currentReservation()`'s method body is a scope violation for this phase.

### Pitfall 3: `stay_status` filter computed per-row with N+1 queries

**What goes wrong:** `GuestFilter`/`GuestService` naively loops over the paginated guest collection and issues one `reservations` query per guest to determine `stay_status`, defeating the query-count target (D-02 targets 5 queries on the service path).
**Why it happens:** `stay_status` is derived from a guest's reservation set with precedence rules (`departing > in_house > arriving > upcoming > past > none`) that feel row-by-row to reason about, tempting a `foreach` with a lazy relation access.
**How to avoid:** Load one constrained eager-load (`with('reservations', fn ($q) => $q->whereIn('status', [...])->select([...]))`) for the whole paginated page, then compute precedence in PHP over the already-loaded collection — same discipline `PITFALLS.md` #8 already documents for the room board and grids.
**Warning signs:** `expectsDatabaseQueryCount()` assertion fails or is missing on the directory endpoint's feature test.

### Pitfall 4: Digital key entropy/lifecycle regresses to a "just another field" implementation

**What goes wrong:** The key gets written via `$reservation->update(['digital_key_code' => ...])` (mass assignment) instead of `forceFill()`, silently working today because `digital_key_code` isn't yet in `$fillable` (Eloquent throws `MassAssignmentException` in that case, so this fails loudly) — but a later refactor that adds it to `$fillable` "to make updates easier" reopens the exact hole D-11 locks shut.
**Why it happens:** `forceFill()` is an unusual pattern in this codebase (most writes go through `$fillable` + `update()`), so a future maintainer unfamiliar with D-11's intent might "fix" what looks like an inconsistency.
**How to avoid:** A code comment on the `$fillable` array (or a unit test asserting `digital_key_code` is never in `$fillable`) documents why it's deliberately excluded.
**Warning signs:** `digital_key_code` appears in `Reservation::$fillable`.

### Pitfall 5: `CheckInApprovalStatus` transition `approved → rejected` doesn't currently guard against re-issuing/losing a key correctly

**What goes wrong:** `ApproveCheckInAction` (existing) does a blind `$approval->update(['status' => $status, ...])` with no transition guard at all — any status can go to any status today. D-11 requires specific side effects per transition (`approved → rejected` revokes with reason `rejected`; re-approving an *already-approved-and-still-active* key must NOT mint a new one; `approve → reject → approve` must mint a fresh one). Naively calling `IssueDigitalKeyAction` unconditionally on every `approved` write breaks the "keeps the active key" case.
**Why it happens:** The existing action has no state-machine discipline to extend from — it's a plain `update()`, not a guarded transition.
**How to avoid:** `IssueDigitalKeyAction` itself must check "does an unrevoked, unexpired key already exist?" before minting — the guard belongs in the issuance action, not in `ApproveCheckInAction`'s call site logic, so the action is idempotent under repeated `approved` writes.
**Warning signs:** A test approving an already-approved-and-active reservation asserts the key code is unchanged; missing this test is missing the guard.

### Pitfall 6: `CancelReservationAction` has no transaction wrapping the revoke call correctly

**What goes wrong:** Current `CancelReservationAction::handle()` wraps only the `$reservation->update(...)` in `DB::transaction()`, as a `fn() => ...` closure whose return value is discarded — the method's own `return` statement executes *outside* that transaction. Naively adding `RevokeDigitalKeyAction::handle($reservation, 'cancelled')` after the existing `DB::transaction(...)` line (rather than inside it) would revoke the key in a separate, un-atomic step from the cancellation.
**Why it happens:** The existing code already has this shape (transaction wraps only the status update, not the whole method), so "add one more line after the transaction" is the path of least resistance and looks consistent with what's already there — but D-14 explicitly requires the revoke to happen "inside its transaction."
**How to avoid:** Restructure `CancelReservationAction::handle()` so the whole `DB::transaction(function () use (...) { $reservation->update(...); RevokeDigitalKeyAction::handle($reservation, 'cancelled'); })` closure contains both writes, and the method's `return` happens after the transaction closure resolves.
**Warning signs:** A test that mocks/spies the revoke action and asserts it never fires when the cancellation-triggering update itself is rolled back (a forced-failure test) — if the revoke is outside the transaction, this test would catch it firing anyway.

### Pitfall 7: `GuestDocument.type` has no enum — "type=id_card?" is not a fixed value

**What goes wrong:** A developer assumes ID-scan uploads must send a specific literal string (`id_card`) and adds server-side validation restricting `documents.*.type` to an enum, breaking the existing free-string contract `SubmitDocumentsRequest` already ships (`'documents.*.type' => ['required', 'string', 'max:255']`, no enum, no whitelist).
**Why it happens:** GUEST-06's requirement text and the phase brief both mention "type=id_card" as if it were a fixed value, but the actual code has never constrained it.
**How to avoid:** GUEST-06 is documentation-only — no code or validation change to `SubmitDocumentsRequest`/`GuestDocument`. The mobile guide documents that the app currently sends `type: "id_card"` (or whatever string it already sends) as a convention, not a server-enforced enum.
**Warning signs:** Any diff touching `SubmitDocumentsRequest`'s `documents.*.type` rule is out of scope for GUEST-06 as locked.

### Pitfall 8: Preferences validation accepting a body with none of the four keys

**What goes wrong:** `UpdateGuestPreferencesRequest` with all-`sometimes` rules validates an empty `{}` body as fully valid (nothing to check), silently succeeding with a no-op PATCH — contradicting D-09's requirement that a body with none of the four keys returns 422 on `preferences`.
**Why it happens:** `sometimes` rules are correct for "each field is independently optional," but they don't express "at least one of these must be present," which needs an explicit `withValidator()`/`after()` closure (the same pattern `UpdateGuestProfileRequest` already uses for its phone-format cross-check).
**How to avoid:** Add a `withValidator()` closure checking `$this->hasAny(['bed_type','pillow_type','floor_preference','other'])` and failing on the `preferences` key if none are present — mirrors the existing precedent in `UpdateGuestProfileRequest::withValidator()`.
**Warning signs:** A test PATCHing `{}` to either preferences route and expecting 422 is missing.

## Code Examples

Verified patterns from this codebase's own source (all read directly, paths given):

### Existing `GuestEntitlement` (do not modify `currentReservation`; add `targetReservation` alongside)
```php
// Source: backend/app/Support/GuestEntitlement.php (verbatim, current state)
class GuestEntitlement
{
    public static function bookedReservations(Guest $guest)
    {
        $today = now()->startOfDay();
        return $guest->activeReservations()
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN])
            ->whereDate('check_out', '>=', $today)
            ->get();
    }

    // D-03: do NOT change this. 8 call sites depend on sortByDesc semantics (FA-06-1).
    public static function currentReservation(Guest $guest): ?Reservation
    {
        return self::bookedReservations($guest)->sortByDesc('check_in')->first();
    }

    // NEW for Phase 4 (D-03): in-house first, else next arrival ascending — never sortByDesc.
    public static function targetReservation(Guest $guest): ?Reservation
    {
        $booked = self::bookedReservations($guest);
        return $booked->first(fn (Reservation $r) => $r->status === ReservationStatus::CHECKED_IN)
            ?? $booked->filter(fn (Reservation $r) => $r->check_in->gte(HotelClock::today()))
                       ->sortBy('check_in')->first();
    }
}
```

### Existing `ApproveCheckInAction` — the exact hook point for `IssueDigitalKeyAction`/`CheckInApproved`
```php
// Source: backend/app/Actions/Service/ApproveCheckInAction.php (verbatim, current state — no transaction bug here, unlike CancelReservationAction)
class ApproveCheckInAction
{
    public function handle(Reservation $reservation, string $status, User $approver, ?string $notes = null): array
    {
        return DB::transaction(function () use ($reservation, $status, $approver, $notes) {
            $approval = CheckInApproval::where('reservation_id', $reservation->id)->first();
            if (! $approval) {
                throw new NotFoundException(__('custom.errors.not_found'));
            }
            $approval->update(['status' => $status, 'approved_by' => $approver->id, 'notes' => $notes]);
            // Phase 4 insertion point: if ($status === 'approved' && in_array($reservation->status, [CONFIRMED, CHECKED_IN])) { $this->issueKey->handle($reservation); CheckInApproved::dispatch($reservation, $approval); }
            // if ($status === 'rejected') { $this->revokeKey->handle($reservation, 'rejected'); }
            return ['data' => $approval->fresh()->load('approver'), 'code' => 200];
        });
    }
}
```

### Existing `CancelReservationAction` — needs restructuring, not just appending (see Pitfall 6)
```php
// Source: backend/app/Actions/Booking/CancelReservationAction.php (verbatim, current state — the transaction bug this phase must fix while adding the revoke)
class CancelReservationAction
{
    public function handle(Reservation $reservation): array
    {
        if (! $reservation->status->isCancellable()) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }
        DB::transaction(fn () => $reservation->update(['status' => ReservationStatus::CANCELLED]));
        // ^ return value discarded; the closure's work is atomic but nothing after this line is.
        return ['data' => null, 'code' => 204];
    }
}
```

### Existing `SendRoomReadyNotification` — the union-type listener pattern to copy for `SendCheckInApprovedNotification`
```php
// Source: backend/app/Listeners/SendRoomReadyNotification.php (verbatim) — CheckInApproved is a single-type listener, simpler than this union
class SendRoomReadyNotification implements ShouldQueue
{
    public function handle(RoomAssigned|GuestCheckedIn $event): void
    {
        $guest = $event->reservation->guest;
        if (! $guest) return;
        $this->notifications->pushToGuest($guest, NotificationType::ROOM_READY, __(...), __(...), [...]);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| No digital-key/token issuance anywhere in this codebase | This phase introduces the first encrypted-at-rest, HMAC-hashed, lifecycle-bound credential column | This phase (Phase 4) | Sets the pattern any future real-lock integration (v2, deferred) will extend — document it clearly since it's a first for the project |
| `LogsActivity` trait documented as "fixed, no model override" | This phase adds the first two per-model `getActivitylogOptions()` overrides (`Guest`, `Reservation`) | This phase (Phase 4) | The `config/activitylog.php` comment becomes stale the moment this ships — update or annotate that comment in the same PR so the next reader isn't misled |

**Deprecated/outdated:** None — this phase adds capability, it does not replace or deprecate any existing mechanism.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `Rule::enum(BedType::class)->except([BedType::EXTRA])` API exists and behaves as expected on the installed Laravel 13.8 (`Illuminate\Validation\Rules\Enum::except()`) | Package Legitimacy Audit, Pattern 1 | If the method signature differs, the D-08 validation rule needs a manual `in_array` fallback instead; low risk, cheap to verify with `composer show laravel/framework` + a quick Tinker check before writing the request class |
| A2 | An `everyFiveMinutes()` (or similar) cadence for the digital-key expiry sweep is an acceptable default, matching `ReleaseExpiredHolds`' cadence | Pattern 5 | If the property wants tighter/looser timing, this is Claude's Discretion per CONTEXT.md — no locked decision to violate, just document the choice made in the phase summary |
| A3 | `hash_hmac('sha256', $code, config('app.key'))` is an acceptable HMAC key source (reusing `APP_KEY` rather than a dedicated secret) | Code Examples, Don't Hand-Roll | D-11 explicitly specifies this exact mechanism ("sha256/HMAC with APP_KEY"), so this is actually a locked decision, not an assumption — listed here only because no in-repo precedent exists to cross-check against |

**If this table is empty:** N/A — see A1-A3 above; all are low-risk, cheaply verifiable, and none touches a compliance/retention/security-standard judgment call (the one genuinely security-sensitive design, the digital key's non-lock-grade nature, is explicitly documented as a locked, intentional decision in D-11, not an assumption).

## Open Questions

1. **Does `Illuminate\Validation\Rules\Enum` on the installed Laravel 13.8 actually expose `->except()`?**
   - What we know: `Enum::except()`/`Enum::only()` were added to Laravel's validation rule in a 10.x minor release and have shipped in every version since, per Laravel's own changelog conventions; the project pins `^13.8`.
   - What's unclear: This session did not run `composer show laravel/framework` or open the vendor source to confirm the exact method signature (`except(array|Closure $values)` vs `except(mixed ...$values)`).
   - Recommendation: One `php artisan tinker` check or a one-line unit test before writing `UpdateGuestPreferencesRequest`/the guest-preferences migration's validation rule; trivial to verify, not worth blocking research on.

2. **Exact cron cadence for the digital-key expiry sweep.**
   - What we know: D-11 requires "a scheduled command revokes keys past expiry" but does not lock a frequency; this is explicitly Claude's Discretion territory (not called out in the Discretion list but also not in the Decisions list — an omission, not a lock).
   - What's unclear: Whether hourly is tight enough (a guest could theoretically retain a working key up to the sweep interval past expiry) or whether `everyFiveMinutes()` (matching `ReleaseExpiredHolds`) is overkill for a display-only credential.
   - Recommendation: Default to `everyFifteenMinutes()` — a middle ground between the existing 5-minute hold-release cadence and hourly, and cheap to change later since it is pure `routes/console.php` configuration, not an API contract.

## Environment Availability

Skipped — this phase has no external service/tool dependency beyond what every other phase in this milestone already assumes (PHP 8.3, Composer, SQLite `:memory:` for tests, MySQL for production — all already verified present by Phases 1-3's own closed test suites).

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.12 |
| Config file | `backend/phpunit.xml` |
| Quick run command | `php artisan test --filter=GuestDirectoryTest` (or the relevant class name) |
| Full suite command | `php artisan test` (serial — `--parallel` unavailable, `paratest` not installed, per Phase 3's own closing note) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| GUEST-01 | `GET /guests` search/filter/paginate, `stay_status` derivation + 422 on bad value | Feature | `php artisan test --filter=GuestDirectoryTest` | ❌ Wave 0 |
| GUEST-01 | `GET /guests/{guest}/notes` list, `guests.view` gate | Feature | `php artisan test --filter=GuestNotesTest` | ❌ Wave 0 |
| GUEST-02 | `GET /guests/{guest}` full profile shape, bounded query count, `GuestProfileResource` never on guest routes | Feature + Unit (query count) | `php artisan test --filter=GuestProfileTest` | ❌ Wave 0 |
| GUEST-03 | `POST /guests/{guest}/notes`, append-only, never in guest-facing responses/activity log | Feature | `php artisan test --filter=GuestNotesTest` | ❌ Wave 0 |
| GUEST-04 | `PATCH /auth/guest/preferences` + `PATCH /guests/{guest}/preferences`, merge semantics, empty-body 422 | Feature | `php artisan test --filter=GuestPreferencesTest` | ❌ Wave 0 |
| GUEST-05 | `POST /stays/{reservation}/online-check-in`, 403 non-owner, 422 closed window, digital key issue/expire/revoke lifecycle, leakage test | Feature + Unit | `php artisan test --filter=OnlineCheckInTest`, `php artisan test --filter=DigitalKeyLifecycleTest` | ❌ Wave 0 |
| GUEST-06 | Docs-only; existing `POST /pre-arrival/documents` route unchanged | Manual (docs review) | N/A | N/A — no new test, existing `SubmitDocumentsTest`-equivalent coverage (if any) stays green |
| XCUT-01 | `guests.view`/`guests.edit` seeded with `reception`/`concierge` presets | Feature | `php artisan test --filter=SeederTest`, `php artisan test --filter=PermissionsGroupedTest` (both need count updates, see Pitfalls) | ✅ exists, needs edits |
| DOCS-01 | `docs/carlton-tree.html` nodes flipped, guides/Postman updated | Manual (contract gate) | N/A | N/A |

### Sampling Rate

- **Per task commit:** the relevant `--filter=` class run
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** Full suite green before `/gsd-verify-work` — Phase 3 closed at 1229/1229; Phase 4 must not regress any of those

### Wave 0 Gaps

- [ ] `tests/Feature/Guests/GuestDirectoryTest.php` — covers GUEST-01
- [ ] `tests/Feature/Guests/GuestProfileTest.php` — covers GUEST-02
- [ ] `tests/Feature/Guests/GuestNotesTest.php` — covers GUEST-01 (list), GUEST-03 (create)
- [ ] `tests/Feature/Guests/GuestPreferencesTest.php` — covers GUEST-04
- [ ] `tests/Feature/Stays/OnlineCheckInTest.php` — covers GUEST-05 (submission path)
- [ ] `tests/Feature/Stays/DigitalKeyLifecycleTest.php` — covers GUEST-05 (issue/revoke/expire/leakage)
- [ ] `tests/Unit/Guest/*` — for `GuestEntitlement::targetReservation()`, `PreArrivalChecklist`, `IssueDigitalKeyAction`/`RevokeDigitalKeyAction` transition guards
- [ ] `database/factories/GuestNoteFactory.php` — new model, no factory exists yet
- [ ] Existing `tests/Feature/SeederTest.php` and `tests/Feature/Staff/PermissionsGroupedTest.php` need count edits (19→21 permissions, 9→10 groups, `reception`/`concierge` permission counts grow) — in place, not new files
- [ ] Framework install: none — PHPUnit 12.5.12 already configured

**Factories that already exist and are reusable as-is:** `GuestFactory`, `GuestDocumentFactory`, `CheckInApprovalFactory` (all verified present and read in full — no changes needed to any of them).

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (guard/token model unchanged this phase) | Existing Sanctum guards (`auth:users`, `auth:guests`) |
| V3 Session Management | No | N/A |
| V4 Access Control | Yes | New `guests.view`/`guests.edit` permission middleware; `FormRequest::authorize()` ownership check on `SubmitOnlineCheckInRequest` (403 `forbidden` for non-owner, matching the project's established guest-ownership pattern) |
| V5 Input Validation | Yes | `Rule::enum()` for `bed_type`/`pillow_type`/`floor_preference`; `date_format:H:i` for `arrival_time`; `BaseFilter`'s existing `reject()`/422 path for `stay_status` |
| V6 Cryptography | Yes | Laravel's `encrypted` Eloquent cast (AES-256-CBC via `APP_KEY`) for `digital_key_code`; `hash_hmac('sha256', ..., config('app.key'))` for the lookup hash — both native Laravel/PHP primitives, never a hand-rolled cipher |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Digital key value derivable from reservation UUID or guest-visible data | Information Disclosure / Spoofing | `random_int`-generated 12-char code from a 32-symbol alphabet (~60 bits), never derived from any client-visible ID (D-11, already locked) |
| Digital key leaking through a secondary channel (push payload, Firestore mirror, activity log, staff resource) | Information Disclosure | One consolidated leakage test enumerating every serialization surface (D-11); `$hidden` on the model plus deliberate resource-level omission on staff-facing resources |
| Non-owner submitting online check-in for another guest's reservation | Elevation of Privilege | `SubmitOnlineCheckInRequest::authorize()` checks `reservation->guest_id === $this->user('guests')->id`, else 403 `forbidden` — mirrors the existing guest-ownership pattern already used elsewhere in this codebase (e.g., `StayController::ownedOrFail`, which returns 404 instead — D-11's context explicitly calls out 403 here as a deliberate difference from the receipt routes' 404, so do not "fix" this into a 404 to match `ownedOrFail`) |
| Guest PII (notes) exposed beyond the intended staff audience | Information Disclosure | `guests.edit` gates writing notes, `guests.view` gates reading them (not a separate `guests.notes.view` — D-01 deliberately uses the coarser existing two-permission split, a documented deviation from `PITFALLS.md` Pitfall 7's suggestion of a dedicated `guests.notes.view` permission; flag this in the phase summary as an intentional, locked scope reduction, not an oversight) |
| Activity-log capturing sensitive preference data (allergy-adjacent `pillow_type`, free-text `preferences_other`) | Information Disclosure | Model-level `logExcept(['preferences_other', 'pillow_type'])` per D-08 (see Pattern 2 above for the implementation nuance) |

## Sources

### Primary (HIGH confidence — read directly from the working tree this session)
- `backend/app/Support/GuestEntitlement.php`, `backend/app/Services/Booking/StayService.php`, `backend/app/Http/Controllers/Api/StayController.php` — current stay/entitlement resolution
- `backend/app/Http/Resources/Booking/{UpcomingStayResource,ActiveStayResource,CheckInStatusResource}.php` — stay resource shapes to extend
- `backend/app/Actions/Service/{SubmitDocumentsAction,ApproveCheckInAction}.php`, `backend/app/Services/Service/PreArrivalService.php`, `backend/app/Http/Controllers/{Api/PreArrivalController,Admin/CheckInApprovalController}.php` — pre-arrival/approval flow
- `backend/app/Actions/Booking/CancelReservationAction.php` — transaction-scope bug this phase must fix while extending
- `backend/app/Services/Auth/AuthGuestService.php`, `backend/app/Actions/Auth/UpdateGuestProfileAction.php`, `backend/app/Http/Requests/Auth/UpdateGuestProfileRequest.php`, `backend/app/Http/Controllers/Auth/GuestAuthController.php` — guest profile update pattern to mirror for preferences
- `backend/app/Http/Resources/{GuestResource,Service/GuestDocumentResource,Service/CheckInApprovalResource}.php`
- `backend/app/Models/{Guest,Reservation,CheckInApproval,GuestDocument}.php` — current fillable/casts/relations
- `backend/app/Traits/LogsActivity.php`, `backend/config/activitylog.php` — the fixed-options trait and its "no model override" comment
- `backend/app/Base/{BaseFilter,BaseService,BaseController}.php`, `backend/app/Filters/RoomFilter.php` — base layer + filter precedent
- `backend/app/Listeners/SendRoomReadyNotification.php`, `backend/app/Services/Notification/NotificationService.php`, `backend/app/Enums/NotificationType.php` — notification pattern
- `backend/app/Enums/{BedType,CheckInApprovalStatus,ReservationStatus}.php`, `backend/app/Enums/Concerns/HasValues.php`
- `backend/database/migrations/{2026_07_07_180000_create_guests_table,2026_07_08_100000_expand_guests_table,2026_07_10_100003_recreate_reservations_table,2026_07_26_100014_add_stay_timestamps_and_dnd_to_reservations_table,2026_07_12_100009_create_check_in_approvals_table,2026_09_26_110000_add_notes_to_reservations_table}.php`
- `backend/config/hotel.php`, `backend/app/Support/HotelClock.php`, `backend/app/Enums/CheckOutMode.php`, `backend/app/Exceptions/{ReservationStateException,ReservationOutsideStayWindowException,DomainException}.php`
- `backend/app/Console/Commands/ReleaseExpiredHolds.php`, `backend/routes/console.php` — scheduler precedent
- `backend/database/seeders/RolesAndPermissionsSeeder.php`, `backend/tests/Feature/SeederTest.php`, `backend/tests/Feature/Staff/PermissionsGroupedTest.php` — current permission/role counts (19 permissions, 7 roles, 9 groups) that Phase 4 will change
- `backend/app/Base/BaseRequest.php` — confirms `date_format` and `Enum::class` message mappings already exist, no new entries needed
- `backend/database/factories/{GuestFactory,CheckInApprovalFactory}.php` — existing, reusable factories
- `backend/routes/api.php` (lines ~525-665) — exact current route groups for `stays`, `reservations` (guest), `cms/reservations`, `cms/check-in-approvals`, `pre-arrival/documents`
- `backend/docs/API_GUIDE_MOBILE.md` (Module: Stays, lines 819-908), `backend/docs/API_GUIDE_DASHBOARD.md` (section headers) — confirms no "Pre-arrival"/"Check-in approvals" module section exists yet in either guide
- `docs/carlton-tree.html` (lines 280-354) — exact current node names/api-flags for guest directory, online check-in, ID scan, guest preferences, check-in approvals
- `.planning/phases/03-reservations-front-desk-verbs/{SUMMARY.md,03-CONTEXT.md}`, `.planning/phases/04-guests-stay/04-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/ROADMAP.md`, `.planning/STATE.md`, `.planning/research/PITFALLS.md`, `.planning/codebase/{TESTING.md,CONVENTIONS.md}`

### Secondary (MEDIUM confidence)
- Laravel 13 `encrypted` cast, `Schedule::command`, `Rule::enum()->except()` behavior — based on well-established, stable Laravel API surface across recent major versions; not verified against the actual installed vendor source this session (see Open Question 1)

### Tertiary (LOW confidence)
- None — this phase required no external web research; every claim above traces to a file read in this session or a locked decision in `04-CONTEXT.md`

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies, all mechanisms native to the already-pinned Laravel 13.8/PHP 8.3
- Architecture: HIGH — every existing class this phase extends was read in full this session
- Pitfalls: HIGH — pitfalls 2, 5, 6, 7, 8 are drawn directly from reading the actual current implementation (not speculation), pitfalls 1, 3, 4 are drawn from the codebase's own documented conventions plus the locked decisions' explicit requirements

**Research date:** 2026-09-26
**Valid until:** Stable until the next phase touches `Guest`, `Reservation`, `CheckInApproval`, or the `LogsActivity`/activitylog configuration — no external time-based decay (internal-codebase research, not library-version research)
