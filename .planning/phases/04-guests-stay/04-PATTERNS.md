# Phase 4: Guests & Stay - Pattern Map

**Mapped:** 2026-09-26
**Files analyzed:** 42 (new/modified)
**Analogs found:** 34 exact/role-match / 42 (8 genuinely novel, no in-repo precedent — see "No Analog Found")

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Http/Controllers/Admin/GuestController.php` | controller | request-response | `app/Http/Controllers/Admin/CheckInApprovalController.php` | role-match |
| `app/Services/Guest/GuestService.php` | service | CRUD | `app/Services/Booking/StayService.php` + `BaseService` | role-match |
| `app/Filters/GuestFilter.php` | filter | request-response (query) | `app/Filters/RoomFilter.php` + `BaseFilter::apply()` | role-match (needs override, no exact precedent) |
| `app/Http/Requests/Guest/AddGuestNoteRequest.php` | request | request-response | `app/Http/Requests/Auth/UpdateGuestProfileRequest.php` | role-match |
| `app/Http/Requests/Guest/UpdateGuestPreferencesRequest.php` | request | request-response | `app/Http/Requests/Auth/UpdateGuestProfileRequest.php` (withValidator pattern) | exact (validation shape) |
| `app/Http/Requests/Booking/SubmitOnlineCheckInRequest.php` | request | request-response | Guest-ownership `authorize()` pattern (StayController call sites) | role-match |
| `app/Http/Resources/Guest/GuestProfileResource.php` | resource | transform | `app/Http/Resources/GuestResource.php` | role-match |
| `app/Http/Resources/Guest/GuestNoteResource.php` | resource | transform | `app/Http/Resources/Service/GuestDocumentResource.php` | exact (thin resource shape) |
| `app/Http/Resources/Guest/GuestPreferencesResource.php` | resource | transform | `app/Http/Resources/Service/GuestDocumentResource.php` | role-match |
| `app/Models/GuestNote.php` | model | CRUD | `app/Models/Guest.php` (HasUuid/HasFactory, minus LogsActivity) | role-match |
| `app/Models/Guest.php` (modify: preferences, logExcept) | model | CRUD | itself (existing) + `LogsActivity` trait override novelty | exact (base), novel (override) |
| `app/Models/Reservation.php` (modify: digital key, checkInApproval, logExcept) | model | CRUD | itself (existing) | exact (base), novel (encrypted cast/override) |
| `app/Actions/Guest/AddGuestNoteAction.php` | action | CRUD | `app/Actions/Service/ApproveCheckInAction.php` (thin action, DB::transaction, returns array) | role-match |
| `app/Actions/Guest/UpdateGuestPreferencesAction.php` | action | CRUD | `app/Actions/Auth/UpdateGuestProfileAction.php` | exact |
| `app/Actions/Booking/SubmitOnlineCheckInAction.php` | action | CRUD/state-transition | `app/Actions/Service/ApproveCheckInAction.php` (transaction + guarded status update) | role-match |
| `app/Actions/Booking/IssueDigitalKeyAction.php` | action | event-driven | none (novel) — modeled on transaction shape of `ApproveCheckInAction` | partial |
| `app/Actions/Booking/RevokeDigitalKeyAction.php` | action | event-driven | `CancelReservationAction` (single-purpose state action) | partial |
| `app/Actions/Booking/CancelReservationAction.php` (modify) | action | CRUD | itself (existing, has the transaction-scope bug to fix) | exact |
| `app/Actions/Service/ApproveCheckInAction.php` (modify) | action | CRUD | itself (existing) | exact |
| `app/Support/GuestEntitlement.php` (add `targetReservation`) | utility | transform | itself (existing `currentReservation`) | exact |
| `app/Support/StayPayload.php` | utility | transform | `app/Http/Resources/GuestResource.php` (reservation shape composition) | partial (novel shared builder) |
| `app/Support/PreArrivalChecklist.php` | utility | transform | none — pure derivation, no in-repo precedent | none |
| `app/Events/CheckInApproved.php` | event | event-driven | existing `RoomAssigned`/`GuestCheckedIn` events (ShouldDispatchAfterCommit siblings) | exact |
| `app/Listeners/SendCheckInApprovedNotification.php` | listener | event-driven | `app/Listeners/SendRoomReadyNotification.php` | exact |
| `app/Listeners/RevokeDigitalKeyOnCheckOut.php` | listener | event-driven | `app/Listeners/SendRoomReadyNotification.php` | role-match |
| `app/Exceptions/OnlineCheckInClosedException.php` | exception | error-handling | `app/Exceptions/ReservationOutsideStayWindowException.php` / `ReservationStateException` | exact |
| `app/Enums/PillowType.php` | model (enum) | transform | `app/Enums/BedType.php` | exact |
| `app/Enums/FloorPreference.php` | model (enum) | transform | `app/Enums/BedType.php` | exact |
| `app/Console/Commands/ExpireDigitalKeys.php` | command | batch | `app/Console/Commands/ReleaseExpiredHolds.php` | exact |
| `routes/console.php` (add schedule) | config | batch | existing `Schedule::command(...)` entries | exact |
| `database/migrations/..._create_guest_notes_table.php` | migration | CRUD | recent migration for a `belongsTo` child table w/ uuid+FKs | role-match |
| `database/migrations/..._add_preferences_to_guests_table.php` | migration | CRUD | `2026_07_08_100000_expand_guests_table.php` | exact |
| `database/migrations/..._add_online_check_in_to_reservations_table.php` | migration | CRUD | `2026_07_26_100014_add_stay_timestamps_and_dnd_to_reservations_table.php` | exact |
| `database/factories/GuestNoteFactory.php` | factory | test | `database/factories/GuestFactory.php` | exact |
| `database/seeders/RolesAndPermissionsSeeder.php` (modify) | config | CRUD | itself (existing group/preset pattern) | exact |
| `tests/Feature/Guests/GuestDirectoryTest.php` | test | request-response | existing `tests/Feature/*` filter/list tests (e.g. Room list test) | role-match |
| `tests/Feature/Guests/GuestProfileTest.php` | test | request-response | `tests/Feature/Reservations/*Test.php` (query-count assertions) | role-match |
| `tests/Feature/Guests/GuestNotesTest.php` | test | CRUD | `tests/Feature/Reservations/AssignRoomTest.php` (verb + permission test style) | role-match |
| `tests/Feature/Guests/GuestPreferencesTest.php` | test | CRUD | `tests/Feature/Auth/UpdateGuestProfileTest.php` (if exists) else `AssignRoomTest.php` | role-match |
| `tests/Feature/Stays/OnlineCheckInTest.php` | test | request-response | `tests/Feature/Reservations/ExpressCheckoutTest.php` | exact |
| `tests/Feature/Stays/DigitalKeyLifecycleTest.php` | test | event-driven | `tests/Unit/Booking/CheckInReservationActionTest.php` (action-level transition tests) | role-match |
| `tests/Unit/Guest/*.php` | test | transform | `tests/Unit/Booking/CheckInReservationActionTest.php` | role-match |

## Pattern Assignments

### `app/Filters/GuestFilter.php` (filter, request-response)

**Analog:** `app/Filters/RoomFilter.php` + `app/Base/BaseFilter.php`

**Base shape** (`RoomFilter.php`, full file, 20 lines):
```php
class RoomFilter extends CmsContentFilter
{
    protected array $safeParms = ['status' => ['eq', 'in'], 'floor' => ['eq']];
    protected array $searchable = ['number'];
    protected array $sortable = ['number', 'floor', 'created_at', 'updated_at'];
}
```
`GuestFilter` extends `App\Base\BaseFilter` directly (not `CmsContentFilter` — guests have no translatable/sort_order fields). Declare `$searchable`, `$safeParms`, `$sortable` per D-02, then override `apply()`:
```php
public function apply(Builder $query): Builder
{
    parent::apply($query); // conditions + search + sort, unchanged
    $this->applyStayStatus($query);
    return $query;
}
```
For the 422-on-unknown-value path, reuse `BaseFilter::reject()` (protected method, confirmed present in `BaseFilter` — read the full file for its signature before use) rather than inventing a new exception. There is no existing filter with a derived non-column condition — build `applyStayStatus()` fresh using constrained eager-loads / EXISTS subqueries per D-02, not a per-row loop (Pitfall 3).

### `app/Actions/Booking/SubmitOnlineCheckInAction.php` / `IssueDigitalKeyAction.php` / `RevokeDigitalKeyAction.php` (action, CRUD/state-transition)

**Analog:** `app/Actions/Service/ApproveCheckInAction.php` (full file, 28 lines) — copy the constructor-injection-free, `DB::transaction(function () use (...) {...})` shape returning `['data' => ..., 'code' => ...]`:
```php
class ApproveCheckInAction
{
    public function handle(Reservation $reservation, string $status, User $approver, ?string $notes = null): array
    {
        return DB::transaction(function () use ($reservation, $status, $approver, $notes) {
            $approval = CheckInApproval::where('reservation_id', $reservation->id)->first();
            if (! $approval) { throw new NotFoundException(__('custom.errors.not_found')); }
            $approval->update([...]);
            return ['data' => $approval->fresh()->load('approver'), 'code' => 200];
        });
    }
}
```
Insert the `IssueDigitalKeyAction`/`CheckInApproved` dispatch and `RevokeDigitalKeyAction` (on rejected) calls **inside** this same transaction closure, at the point marked in RESEARCH.md's annotated copy of this file.

**CRITICAL — do not copy `CancelReservationAction`'s current shape as-is.** It has the transaction-scope bug documented in RESEARCH.md Pitfall 6:
```php
// BUGGY current shape — do not replicate
DB::transaction(fn () => $reservation->update(['status' => ReservationStatus::CANCELLED]));
return ['data' => null, 'code' => 204];
```
Fix while extending: wrap the whole method body (update + `RevokeDigitalKeyAction::handle($reservation, 'cancelled')`) inside one `DB::transaction(function () use (...) {...})` closure, matching `ApproveCheckInAction`'s correct shape above, then `return` after the closure resolves.

### `app/Actions/Guest/UpdateGuestPreferencesAction.php` / `AddGuestNoteAction.php` (action, CRUD)

**Analog:** `app/Actions/Auth/UpdateGuestProfileAction.php` for the merge-write pattern; `ApproveCheckInAction`'s transaction shape for wrapping the write + `preferences_updated_at` timestamp set.

### `app/Http/Resources/Guest/GuestNoteResource.php` / `GuestPreferencesResource.php` (resource, transform)

**Analog:** `app/Http/Resources/Service/GuestDocumentResource.php` (full file, 14 lines) — the minimal thin-resource shape:
```php
class GuestDocumentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->uuid, 'type' => $this->type];
    }
}
```
Copy this exact skeleton (`extends BaseResource`, `toArray(Request $request): array`, flat associative return) for both new note/preferences resources.

### `app/Http/Resources/Guest/GuestProfileResource.php` (resource, transform)

**Analog:** `app/Http/Resources/GuestResource.php` (full file, 38 lines) — shows the existing "compose from loaded relations, guard on `relationLoaded`" convention:
```php
$loaded  = $this->relationLoaded('activeReservations');
$booked  = $loaded ? $this->activeReservations->whereIn('status', [...])->filter(...) : collect();
$current = $booked->sortByDesc('check_in')->first();
```
`GuestProfileResource` must NOT reuse `sortByDesc` — it delegates entirely to `GuestEntitlement::targetReservation()`/`StayPayload`/`PreArrivalChecklist` per D-04, never hand-rolling the reservation shape inline the way `GuestResource` does. Use `GuestResource` only for its `extends BaseResource` / flat-array-of-scalars-and-nested-arrays convention, not its reservation-selection logic.

### `app/Models/Guest.php` / `app/Models/Reservation.php` — `getActivitylogOptions()` override (novel, no in-repo precedent)

**Source of the trait being overridden:** `app/Traits/LogsActivity.php` (full file, 15 lines):
```php
trait LogsActivity
{
    use SpatieLogsActivity;
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
```
Both `Guest` and `Reservation` must define their own `getActivitylogOptions()` restating this exact chain plus `->logExcept([...])` — no `parent::` call (traits cannot be targeted by `parent::`). This is genuinely new ground per RESEARCH.md; there is nothing else in the codebase overriding this method today. Flag the `config/activitylog.php` "no model override" comment as stale once this ships (RESEARCH.md State of the Art table).

### `app/Console/Commands/ExpireDigitalKeys.php` (command, batch)

**Analog:** `app/Console/Commands/ReleaseExpiredHolds.php` (full file, 15 lines) — copy verbatim shape:
```php
class ReleaseExpiredHolds extends Command
{
    protected $signature   = 'booking:release-holds';
    protected $description = 'Cancel pending_verification reservations whose hold has expired';
    public function handle(ReleaseExpiredHoldsAction $action): int
    {
        $released = $action->handle();
        $this->info("Released {$released} expired hold(s).");
        return Command::SUCCESS;
    }
}
```

### `app/Listeners/SendCheckInApprovedNotification.php` (listener, event-driven)

**Analog:** `app/Listeners/SendRoomReadyNotification.php` (full file, 33 lines) — `implements ShouldQueue`, constructor-injects `NotificationService`, single `handle($event)` calling `$this->notifications->pushToGuest(...)`. `CheckInApproved` is single-type (not a union like `RoomAssigned|GuestCheckedIn`), so the `handle()` signature simplifies to `handle(CheckInApproved $event): void`. Body text must say "key is ready in the app" — never the code (RESEARCH.md leakage anti-pattern).

### `app/Support/GuestEntitlement.php` — add `targetReservation()` (utility, transform)

**Analog:** the file's own existing `currentReservation()` (full file, 34 lines) — add alongside, do not modify:
```php
public static function currentReservation(Guest $guest): ?Reservation
{
    return self::bookedReservations($guest)->sortByDesc('check_in')->first();
}
```
New method must reuse `bookedReservations()` but select in-house-first, else next-arrival-ascending — never `sortByDesc` (Pitfall 2, D-03).

### `database/factories/GuestNoteFactory.php` (factory, test)

**Analog:** `database/factories/GuestFactory.php` (first 40 lines shown) — `protected $model = ...; public function definition(): array { ... }` plus named `state()` helper methods (`phoneVerified()`, `emailVerified()` pattern) if a note factory needs states (e.g. `->byAuthor($user)`).

## Shared Patterns

### Transaction wrapping for state-changing actions
**Source:** `app/Actions/Service/ApproveCheckInAction.php`
**Apply to:** `SubmitOnlineCheckInAction`, `IssueDigitalKeyAction`, `RevokeDigitalKeyAction`, `AddGuestNoteAction`, `UpdateGuestPreferencesAction`, fixed `CancelReservationAction`
```php
return DB::transaction(function () use (...) {
    // all reads-then-writes that must be atomic
    return ['data' => ..., 'code' => ...];
});
```

### Thin resource skeleton
**Source:** `app/Http/Resources/Service/GuestDocumentResource.php`
**Apply to:** `GuestNoteResource`, `GuestPreferencesResource`, and any nested shape inside `GuestProfileResource`/`StayPayload`
```php
class XResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [ /* flat, explicit keys — never $this->toArray() passthrough */ ];
    }
}
```

### Notification listener on domain event
**Source:** `app/Listeners/SendRoomReadyNotification.php`, `app/Services/Notification/NotificationService.php`
**Apply to:** `SendCheckInApprovedNotification`, `RevokeDigitalKeyOnCheckOut`
```php
class SendXNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}
    public function handle(EventType $event): void
    {
        $guest = $event->reservation->guest;
        if (! $guest) return;
        $this->notifications->pushToGuest($guest, NotificationType::X, __(...), __(...), [...]);
    }
}
```

### Scheduled sweep command
**Source:** `app/Console/Commands/ReleaseExpiredHolds.php`, `routes/console.php`
**Apply to:** `ExpireDigitalKeys`
```php
class ExpireDigitalKeys extends Command
{
    protected $signature = 'stays:expire-digital-keys';
    public function handle(RevokeExpiredDigitalKeysAction $action): int { ... return Command::SUCCESS; }
}
// routes/console.php: Schedule::command('stays:expire-digital-keys')->everyFifteenMinutes();
```

### Filter with `apply()` override for derived state
**Source:** `app/Filters/RoomFilter.php`, `app/Base/BaseFilter.php`
**Apply to:** `GuestFilter` (`stay_status`)

### Migration additive-column style
**Source:** `database/migrations/2026_07_08_100000_expand_guests_table.php`, `2026_07_26_100014_add_stay_timestamps_and_dnd_to_reservations_table.php`
**Apply to:** all three new migrations (guest_notes create, guest preferences add, reservation online-check-in + digital-key add) — Read these two files directly when writing migrations to match column-naming/index conventions exactly (not excerpted here to avoid a redundant re-read; they are short additive migrations consistent with the seen `add_*_to_*_table` naming convention).

## No Analog Found

Files with no close match in the codebase — planner should lean on RESEARCH.md's Pattern 2/3/4 code examples instead:

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `app/Support/PreArrivalChecklist.php` | utility | transform | Pure derived-checklist computation; no existing "checklist over already-loaded relations" utility exists (closest conceptual precedent is derived room-occupancy state from Phase 2, not a reusable class) |
| `app/Support/StayPayload.php` | utility | transform | No existing shared-builder-feeding-multiple-resources utility; must be built fresh per D-12 |
| `app/Actions/Booking/IssueDigitalKeyAction.php` | action | event-driven | First encrypted/HMAC credential-issuance action in the codebase (RESEARCH.md Pattern 3/4) — use those code examples verbatim, not a codebase analog |
| `app/Actions/Booking/RevokeDigitalKeyAction.php` | action | event-driven | Same as above — first credential-revocation action |
| `Reservation::casts()` `digital_key_code => 'encrypted'` | model (cast) | — | No model in the codebase uses the `encrypted` cast today (`grep -rn "encrypted" app/Models` returns nothing per RESEARCH.md) |
| `Guest`/`Reservation` `getActivitylogOptions()` override | model | — | First per-model override of a trait method the codebase's own convention says is "fixed" — genuinely novel, see Pattern Assignments above |
| `GuestFilter::applyStayStatus()` | filter (method) | transform | First filter with a derived, non-column, precedence-ordered condition; every existing filter is column-only |
| `app/Http/Requests/Guest/UpdateGuestPreferencesRequest.php` "at least one key present" rule | request | — | No existing preferences-merge request; closest is `UpdateGuestProfileRequest::withValidator()`'s phone cross-check pattern (partial match, listed above) — the "hasAny of N optional keys" shape itself is new |

## Metadata

**Analog search scope:** `app/Filters`, `app/Base`, `app/Support`, `app/Actions/{Service,Booking,Auth}`, `app/Listeners`, `app/Console/Commands`, `app/Http/Resources`, `app/Models`, `app/Traits`, `database/factories`, `database/migrations`, `tests/Feature/Reservations`
**Files scanned:** ~20 read directly (full or partial) this session, per RESEARCH.md's own file list (~35 files read during research, reused here rather than re-read)
**Pattern extraction date:** 2026-09-26
