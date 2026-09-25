# Phase 3: Reservations Front-Desk Verbs - Research

**Researched:** 2026-09-26
**Domain:** Laravel 13 / PHP 8.3 backend — reservation lifecycle verbs (check-in, check-out, notes, available-rooms) on an existing booking/folio domain
**Confidence:** HIGH (every claim below is grounded in direct inspection of the current repo state at commit `1af91ad`, not external sources — this is an internal-integration phase, not a new-library phase)

## Summary

Phase 3 adds four routes to the existing `cms/reservations` group and narrows one (`assign-room`). Everything needed already exists in the codebase in a form to extend, not replace: `AssignRoomAction`'s transaction/lock/event-dispatch shape, `CheckAvailabilityAction`'s overlap predicate, `SettleFolioAction`'s lock-then-check pattern, `GenerateFolioAction`'s idempotent line-item rebuild, `UpdateRoomStatusAction` as the single writer of room status, and `LogsActivity` for the audit trail. No new package is needed — this is a pure application-code phase (new Actions, two new config values, one new table column, three new domain exceptions, two new/reused events).

The highest-risk part of this phase is **not** new technology, it is correctly **narrowing** existing behavior without breaking the four call sites that currently depend on `AssignRoomAction` flipping status: `RoomAssignmentAtBookingTest.php`, `StayTest.php:91`, `ReservationTest.php:78-89` (`test_staff_can_assign_room_at_checkin`, confirmed by direct read — asserts `data.status === checked_in` after `assign-room`), and `NotificationTriggersTest.php:40-87` (asserts `RoomAssigned` fires and pushes a notification off a plain `assign-room` call on a `confirmed` reservation). All four assert behavior that D-03 explicitly removes from `assign-room`; all four must be rewritten to check in first (via the new `check-in` verb), then call `assign-room` to prove the *move* still fires `RoomAssigned`.

The second risk is the two write paths that must never disagree with each other or with existing gates: (1) the shared overlap/auto-pick predicate must be extracted once and reused by `CreateReservationAction` (unchanged), `AssignRoomAction` (narrowed), `CheckInReservationAction` (new), and the new `available-rooms` read — a second, slightly-different copy of `check_in < r.check_out AND check_out > r.check_in` is exactly the kind of drift `CheckAvailabilityAction`'s own comments warn against; (2) `GenerateFolioAction` must gain a "do not rebuild once checked out" guard alongside its existing "do not rebuild once settled" guard, or a forced check-out with an open folio will have its total silently rewritten the next time anyone reads the folio.

**Primary recommendation:** Extend `CheckAvailabilityAction` with two new public methods (`isRoomFree(Room, Reservation): bool` extracted from `AssignRoomAction`'s existing overlap query, and reuse `findFreeRoom` as-is) rather than introducing a new `RoomAvailabilityService` class — this keeps the single source of truth for overlap semantics in the one class every existing caller already imports, and avoids a second class with the same responsibility. Build `CheckInReservationAction` and `CheckOutReservationAction` in `app/Actions/Booking/` (not `app/Actions/FrontDesk/` as the milestone-level `ARCHITECTURE.md` speculated before this phase's decisions were locked) — this matches where `AssignRoomAction`, `CreateReservationAction` and `ConfirmReservationAction` already live, and is what the phase's own canonical_refs and D-01/D-06 name explicitly.

**Important:** `.planning/research/ARCHITECTURE.md` was written before Phase 3's `03-CONTEXT.md` decisions were locked and disagrees with them in three places the planner must ignore in favor of `03-CONTEXT.md`: (1) it proposes `Actions/FrontDesk/` — the locked decision is `Actions/Booking/`; (2) it proposes a new `reservation_notes` table — the locked decision (D-11) is one additive `reservations.notes` column; (3) it proposes `FolioNotSettledException` / `folio_not_settled` — the locked decision (D-06) is `FolioUnsettledException` / `folio_unsettled`. Treat `03-CONTEXT.md` D-01..D-14 as the authoritative source for all naming; `ARCHITECTURE.md` is superseded background only.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Check-in gate (status, stay window, room resolution, maintenance guard) | API / Backend (Action layer) | Database (transaction + row lock) | Business rule enforcement belongs in the Action; the lock is a DB-tier concern the Action orchestrates |
| Check-out gate (folio-settled check, force override, room-dirty write) | API / Backend (Action layer) | Database (transaction + row lock on reservation + folio) | Same as above; cross-domain precondition (folio state) must be synchronous per `ARCHITECTURE.md` Pattern 2 |
| Room-dirty write on check-out | API / Backend, delegated to existing `UpdateRoomStatusAction` | Database (`room_status_history` audit row) | Phase 2 already made this action the single writer of room status; Phase 3 must call it, never write `rooms.status` directly |
| "Room ready" notification on check-in / room move | API / Backend (Event + Listener), queued | — | Side effect that must not block the primary transaction (`ARCHITECTURE.md` Pattern 2) |
| Reservation notes | API / Backend (simple additive column + Action) | — | No new tier; single free-text field, no history requirement stated |
| Available-rooms read | API / Backend (read-only, reuses `CheckAvailabilityAction`) | Database (bounded query) | Pure read projection, must be ≤ 3 queries per D-12 |
| Hotel-local "today" for stay-window and early check-in | API / Backend (`config/hotel.php` + `CarbonImmutable`) | — | Business-date concept, not a UTC storage concern; mirrors the `whereDate()` precedent already proven for SQLite/MySQL parity |
| Activity/audit trail for status changes and overrides | API / Backend (`LogsActivity` trait + explicit `activity()->log()` calls) | Database (`activity_log` table, Spatie) | Already the established pattern from Phase 1/2; no new audit table |

## Standard Stack

### Core

No new libraries. This phase is built entirely on packages already in `composer.json` (Laravel 13.8, Spatie `laravel-permission`, Spatie `laravel-activitylog`, Sanctum). `[VERIFIED: backend/composer.json]`

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^13.8` (installed, confirmed via `composer.json`) | Application framework — `ShouldDispatchAfterCommit`, `DB::transaction`, `lockForUpdate` | Already the project framework; `ShouldDispatchAfterCommit` (the interface D-09 names for `ReservationCheckedOut`) has existed since Laravel 11, available in 13.8 |
| `spatie/laravel-activitylog` | already installed | `LogsActivity` trait + `activity()->performedOn()->causedBy()->withProperties()->log()` for the D-02/D-07/D-08 override log entries | Already the project's audit mechanism (Phase 1/2 precedent); no new dependency |
| `spatie/laravel-permission` | already installed | `permission:reservations.view\|reservations.create\|folios.settle` middleware | No new permissions this phase (D-13) — reuses existing permission strings |

### Supporting

None — no queue driver change, no new HTTP client, no new validation package. `phpunit.xml` already sets `QUEUE_CONNECTION=sync`, so `ShouldQueue` listeners (e.g. `SendRoomReadyNotification`) execute synchronously in tests without `Queue::fake()`, exactly as they do today for `RoomAssigned`.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Extending `CheckAvailabilityAction` with `isRoomFree()` | A new `RoomAvailabilityService` class (per `03-CONTEXT.md` D-04's "or methods on `CheckAvailabilityAction`" discretion) | A new service adds a class with the exact same overlap-predicate responsibility `CheckAvailabilityAction` already owns, and every existing caller (`CreateReservationAction`, `AssignRoomAction`) would need a second import. Recommend the method-on-existing-class option; only worth a separate service if a future phase needs availability logic with no natural home |
| `ShouldDispatchAfterCommit` on `GuestCheckedIn` | Manual post-transaction `event()` call (the existing `AssignRoomAction`/`RoomAssigned` style — dispatched immediately after `DB::transaction()` returns, not via the interface) | D-09 explicitly names `ShouldDispatchAfterCommit` for `ReservationCheckedOut` only; D-05 says only "events dispatch after commit" for `GuestCheckedIn`. Either satisfies "after commit" for a single top-level transaction. Recommend matching the existing `RoomAssigned` manual style for `GuestCheckedIn` for consistency with the shared listener's other trigger, but this is a defensible Claude's-discretion choice either way |

**Installation:** None — no `composer require` needed for this phase.

**Version verification:** `composer.json:15` pins `"laravel/framework": "^13.8"` — confirmed by direct file read, not assumed. `[VERIFIED: backend/composer.json]`

## Package Legitimacy Audit

**Not applicable — this phase installs no new external packages.** Every class, trait and interface used (`ShouldDispatchAfterCommit`, `LogsActivity`, `DB::transaction`, `lockForUpdate`) ships with `laravel/framework` or `spatie/laravel-activitylog`, both already present in `composer.lock`. No `npm view` / `pip index` / `cargo search` step applies.

## Architecture Patterns

### System Architecture Diagram

```
POST /cms/reservations/{r}/check-in                  POST /cms/reservations/{r}/check-out
        │ (permission:reservations.create)                    │ (permission:reservations.create)
        ▼                                                      ▼
AdminReservationController::checkIn                   AdminReservationController::checkOut
        │ resolves optional room_uuid → Room                   │ resolves force/reason
        ▼                                                      ▼
CheckInReservationAction::handle(                     CheckOutReservationAction::handle(
  Reservation, ?Room, User $actor,                       Reservation, CheckOutMode, ?User $actor, ?reason
  bool $earlyCheckIn, ?string $reason)                  )
        │                                                      │
        ▼ DB::transaction + lockForUpdate(reservation)         ▼ DB::transaction + lockForUpdate(reservation, folio)
  1. status must be CONFIRMED                            1. status must be CHECKED_IN
     else ReservationStateException                         else ReservationStateException
  2. stay-window check (hotel-local "today")             2. folio: GenerateFolioAction if missing/open
     (config('hotel.timezone'))                             (guarded: no rebuild once CHECKED_OUT)
     else ReservationOutsideStayWindowException          3. folio OPEN + mode=NONE
     — unless early_check_in + reason, day-before only        → FolioUnsettledException (unless force+folios.settle)
  3. resolve room: explicit → already-assigned          4. set status=CHECKED_OUT, checked_out_at=now()
     → CheckAvailabilityAction::findFreeRoom            5. for each reservation_room: UpdateRoomStatusAction
  4. room-type match check                                  → dirty (system actor = null), idempotent
  5. maintenance room → RoomOutOfOrderException          6. activity log: check_out_forced / check_out_guest_express
  6. overlap check (shared predicate)                          (only for STAFF_FORCE / GUEST_EXPRESS)
     → RoomAlreadyAssignedException                       ▼
  7. write room_id, status=CHECKED_IN, checked_in_at    dispatch ReservationCheckedOut (ShouldDispatchAfterCommit)
        ▼                                                      │  (no listener yet this phase — Phase 6/4 subscribe later)
  dispatch GuestCheckedIn (after commit)                       ▼
        ▼                                              ReservationResource (+ folio{uuid,status,total_usd})
  SendRoomReadyNotification listener (shared with
  RoomAssigned — handle(RoomAssigned|GuestCheckedIn))
        ▼
  ReservationResource (rooms.room, rooms.roomType, guest)

GET /cms/reservations/{r}/available-rooms  (permission:reservations.view, read-only, ≤3 queries)
        │
        ▼
  Q1: reservation + first reservation_room + roomType
  Q2: rooms of that type, is_active, not maintenance
  Q3: overlapping reservation_room rows for the window (shared predicate)
        ▼
  assemble in PHP: assigned:true for the currently-held room, sort assigned desc, number asc

PATCH /cms/reservations/{r}/notes  (permission:reservations.create, any status)
        │
        ▼
  UpdateReservationNotesAction — single-column transactional update, LogsActivity records old/new
```

### Recommended Project Structure

```
app/Actions/Booking/
├── AssignRoomAction.php              # narrowed (D-03) — no longer touches status/checked_in_at
├── CheckInReservationAction.php      # new (D-01)
├── CheckOutReservationAction.php     # new (D-06)
├── CheckAvailabilityAction.php       # gains isRoomFree() — extracted overlap check (D-04)
├── UpdateReservationNotesAction.php  # new (D-11)
├── CreateReservationAction.php       # unchanged
└── ConfirmReservationAction.php / CancelReservationAction.php  # unchanged

app/Enums/
└── CheckOutMode.php                  # new enum: NONE, STAFF_FORCE, GUEST_EXPRESS (D-06)

app/Events/
├── GuestCheckedIn.php                # new (D-05)
├── RoomAssigned.php                  # unchanged (already exists)
└── ReservationCheckedOut.php         # new, ShouldDispatchAfterCommit (D-09)

app/Exceptions/
├── RoomOutOfOrderException.php               # new — room_out_of_order, 422 (D-01)
├── ReservationOutsideStayWindowException.php # new — reservation_outside_stay_window, 422 (D-02)
└── FolioUnsettledException.php               # new — folio_unsettled, 422 (D-06)

app/Http/Requests/Booking/
├── CheckInReservationRequest.php     # new
├── CheckOutReservationRequest.php    # new
└── UpdateReservationNotesRequest.php # new

app/Filters/
└── ReservationFilter.php             # new (D-10) — status + folio_status; none exists today

config/
└── hotel.php                         # new — 'timezone' => env('HOTEL_TIMEZONE', 'Asia/Damascus') (D-02)

database/migrations/
└── 2026_XX_XX_XXXXXX_add_notes_to_reservations_table.php  # additive (D-11)
```

### Pattern 1: Shared overlap predicate, extracted not duplicated

**What:** `AssignRoomAction` currently inlines its own overlap query (lines 47-55) that is *almost* but not textually identical to `CheckAvailabilityAction::overlapping()`'s private query — both filter `holdingInventory()` and compare `check_in`/`check_out`, but `AssignRoomAction` additionally excludes the current reservation (`where('id', '!=', $reservation->id)`) and queries by a specific room rather than by room type. This single-room variant is exactly what `CheckInReservationAction` and `available-rooms` also need.

**When to use:** Any time code needs "is this specific room free for this reservation's dates, ignoring the reservation's own hold."

**Example (extraction target — add to `CheckAvailabilityAction`):**
```php
// Source: extracted from app/Actions/Booking/AssignRoomAction.php:44-59 (existing code, this repo)
public function isRoomFree(Room $room, Reservation $reservation): bool
{
    return ! $room->reservationRooms()
        ->whereNotNull('room_id')
        ->whereHas('reservation', function ($q) use ($reservation) {
            $q->where('id', '!=', $reservation->id)
              ->whereDate('check_in', '<', $reservation->check_out)
              ->whereDate('check_out', '>', $reservation->check_in)
              ->holdingInventory();
        })
        ->exists();
}
```
Note: the existing `AssignRoomAction` code does **not** use `whereDate()` on this specific query (only `CheckAvailabilityAction::overlapping()` does), while `check_in`/`check_out` are cast `'date'` on `Reservation` — this is a pre-existing minor inconsistency. When extracting, prefer `whereDate()` for parity with the rest of the codebase's date-comparison discipline (documented pitfall: raw datetime comparison vs `whereDate()` on SQLite/MySQL).

### Pattern 2: Room resolution order for check-in (D-01)

**What:** `CheckInReservationAction` must resolve which room to check the guest into, in this exact priority: (1) explicit `room_uuid` from the request body, (2) the room already on `reservation_rooms.room_id` (set at booking time by `CreateReservationAction`, or by a pre-arrival `assign-room` call), (3) auto-pick via `CheckAvailabilityAction::findFreeRoom()`.

**When to use:** Only inside `CheckInReservationAction::handle()`.

**Example:**
```php
// Pattern reference: app/Actions/Booking/AssignRoomAction.php:22-38 already has steps (1)-(2);
// this phase adds step (3) as a new fallback when reservation_rooms.room_id is also null.
$reservationRoom = $reservation->rooms()->first();
$target = $room
    ?? ($reservationRoom->room_id ? $reservationRoom->room : null)
    ?? $this->checkAvailability->findFreeRoom(
        $reservationRoom->room_type_id,
        $reservation->check_in->toDateString(),
        $reservation->check_out->toDateString(),
    );
```
`findFreeRoom()` already excludes `maintenance`-independent capacity correctly (comment at `CheckAvailabilityAction.php:41-45` states it filters only on `is_active`), but D-04's picker additionally wants "prefers `available` over `dirty`" — `findFreeRoom()` today orders by `number` only. The auto-pick ordering needs `orderByRaw` or a `CASE` to prefer `RoomStatus::AVAILABLE` before `DIRTY`, then `number` — this is new logic, not present in the current `findFreeRoom()`.

### Pattern 3: Stay-window check via hotel-local business date

**What:** `Reservation::isWithinStayWindow(CarbonImmutable $hotelToday): bool` compares the date-only `check_in`/`check_out` columns (already cast `'date'`, confirmed in `app/Models/Reservation.php:32-33`) against a hotel-local "today" computed from `config('hotel.timezone')`, not server/UTC time.

**When to use:** Inside `CheckInReservationAction`, and to align `FrontDeskService::board()`'s `$date = $filters['date'] ?? now()->toDateString();` default (currently uses server/UTC `now()`, per Phase 2 code at `FrontDeskService.php:49`) to the same hotel-local timezone, per D-02's explicit callout.

**Example:**
```php
// New — no existing precedent for a business-date-scoped predicate on Reservation.
// Config precedent: config/cms.php uses env()-backed config with a documented single source of truth.
// config/hotel.php
return [
    'timezone' => env('HOTEL_TIMEZONE', 'Asia/Damascus'),
];

// app/Models/Reservation.php — new method
public function isWithinStayWindow(CarbonImmutable $hotelToday): bool
{
    $today = $hotelToday->toDateString();
    return $this->check_in->toDateString() <= $today && $today < $this->check_out->toDateString();
}
```
Call sites get "today" via `CarbonImmutable::now(config('hotel.timezone'))`, never `now()`/`Carbon::today()` directly — this mirrors the pitfall already documented for a later phase (`PITFALLS.md` Pitfall 3, night audit) but applies identically here: server wall-clock time is UTC (`config/app.php:68`), and using it directly for a hotel-local business-date decision is the exact bug class already flagged.

### Pattern 4: Domain exception shape (unchanged, three new subclasses)

**What:** Every domain exception extends `App\Exceptions\DomainException` (abstract, `errorCode(): string`, `statusCode(): int`, optional `context()` array via constructor) and the global handler converts it to the envelope automatically — no `Handler.php` change needed.

**Example:**
```php
// Source: app/Exceptions/RoomStatusTransitionException.php (this repo, Phase 2 precedent)
namespace App\Exceptions;

class RoomOutOfOrderException extends DomainException
{
    public function errorCode(): string { return 'room_out_of_order'; }
    public function statusCode(): int   { return 422; }
}
```
Same shape for `ReservationOutsideStayWindowException` (`reservation_outside_stay_window`, 422) and `FolioUnsettledException` (`folio_unsettled`, 422). Each constructor call passes `context` per D-01/D-02/D-06: `new RoomOutOfOrderException(__('custom.errors.room_out_of_order'), ['room_uuid' => $room->uuid, 'housekeeping_status' => $room->status->value])`.

### Pattern 5: Shared listener across two events

**What:** `SendRoomReadyNotification` currently type-hints `handle(RoomAssigned $event): void`. D-05 requires the same listener to also fire on `GuestCheckedIn`. Laravel's event-listener auto-discovery scans listener classes for `handle()` parameter types; a union type on one method is the idiomatic way to bind one listener to two events without an explicit `EventServiceProvider` registration (this project has none currently — confirm no `EventServiceProvider::$listen` array exists before assuming auto-discovery is the only mechanism in use).

**Example:**
```php
// Modify app/Listeners/SendRoomReadyNotification.php
public function handle(RoomAssigned|GuestCheckedIn $event): void
{
    $guest = $event->reservation->guest;
    // ... unchanged body, both events expose ->reservation
}
```
Both `RoomAssigned` and the new `GuestCheckedIn` must expose `public readonly Reservation $reservation` for this to type-check identically.

### Anti-Patterns to Avoid

- **Writing `rooms.status` directly anywhere in `CheckOutReservationAction`:** Phase 2 made `UpdateRoomStatusAction` the single writer (enforced by its own docblock: "Never reads or writes reservations, availability or room assignment"). Check-out must call `UpdateRoomStatusAction::handle($room, RoomStatus::DIRTY, 'check-out', $actor)` for every assigned room not already `dirty` — never `$room->update(['status' => 'dirty'])`.
- **A second overlap-predicate implementation:** see Pattern 1 — any new `whereDate('check_in', ...)` comparison written from scratch instead of extracted/reused is exactly the drift the codebase's own comments warn against.
- **Regenerating folio items after check-out:** `GenerateFolioAction::handle()` today only guards `FolioStatus::SETTLED` (returns unchanged). An `OPEN` folio on a `CHECKED_OUT` reservation (the forced-checkout case) will still have its `items()->delete()` + rebuild run if `GenerateFolioAction` is called again later (e.g., a staff member opens the folio screen after a forced checkout) — this silently changes the total on a reservation that's supposedly closed. Add a second guard: `if ($reservation->status === ReservationStatus::CHECKED_OUT) { return ['data' => $folio->load('items'), 'code' => 200]; }` before the delete-and-rebuild block.
- **Passing the `Request` object into an Action:** the existing pattern strictly extracts validated data in the controller (`$request->validated('room_uuid')`) — `CheckInReservationRequest`/`CheckOutReservationRequest` follow the same shape as `AssignRoomRequest`, never passed to the Action itself.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Row locking during check-in/check-out | A custom mutex/cache lock | `DB::transaction()` + `Model::where(...)->lockForUpdate()->firstOrFail()` | Already the proven pattern in `SettleFolioAction`, `CreateReservationAction`, `UpdateRoomStatusAction`; works on both SQLite (tests) and MySQL (prod) with the same caveat already documented (SQLite lock is a backstop only, MySQL is the real guarantee) |
| Domain-exception-to-HTTP mapping | A `try/catch` in each controller method | `DomainException` subclass + the existing global `Handler` | Zero controller code needed — every existing controller method already relies on this |
| Audit trail for the check-out override | A bespoke `reservation_overrides` table | `activity()->performedOn($reservation)->causedBy($actor)->withProperties([...])->log('reservation.check_out_forced')` via the existing `LogsActivity` trait already on `Reservation` | D-13 explicitly defers a `reservation_status_history` table to a later "night audit" phase; the activity log already captures old/new `status`/`checked_in_at`/`checked_out_at` plus causer automatically via `logOnlyDirty()` |
| Reservation index filtering | Ad hoc `if` chains reading `$request->query()` in the service | `App\Base\BaseFilter` subclass (`ReservationFilter`), constructed in the controller and passed to the service | Matches every other filtered index in the codebase; `status` is a plain column (`safeParms`), but `folio_status` has no column on `reservations` — needs an `apply()` override (see Open Questions) |
| Event-driven side effects vs. blocking preconditions | Mixing the folio-settled check into an event listener | Synchronous check inside `CheckOutReservationAction`'s transaction (blocking); `GuestCheckedIn`/`ReservationCheckedOut` (fire-and-forget, after commit) | `ARCHITECTURE.md` Pattern 2 — already the precedent this codebase follows for `CreateReservationAction` (sync lock) vs `RoomAssigned` (event) |

**Key insight:** every mechanism this phase needs — locking, domain exceptions, activity logging, event dispatch, filters — already has exactly one canonical implementation in this codebase from Phases 1-2 and the existing booking/folio code. The work is disciplined reuse and correct narrowing, not new infrastructure.

## Common Pitfalls

### Pitfall 1: Breaking the four existing tests that assert `assign-room` flips status

**What goes wrong:** `assign-room` no longer sets `status`/`checked_in_at` (D-03) or fires `RoomAssigned` unless the reservation is already `checked_in` AND the room actually changed. Four existing tests assert the old behavior and will fail the moment `AssignRoomAction` is narrowed, before any new test is even written:
- `tests/Feature/Booking/RoomAssignmentAtBookingTest.php` — the check-in section (`test_check_in_without_a_room_uses_the_reserved_one`, `test_staff_can_move_the_guest_to_another_room_at_check_in`, `test_moving_into_a_room_another_booking_holds_is_refused`, confirmed at lines 118-162) all call `app(AssignRoomAction::class)->handle($reservation, ...)` directly and assert `ReservationStatus::CHECKED_IN` / `checked_in_at` afterward.
- `tests/Feature/Booking/StayTest.php:83-91` (`test_checking_in_stamps_the_arrival_time`) calls `app(AssignRoomAction::class)->handle($reservation, $room)` then asserts `checked_in_at` is set.
- `tests/Feature/Booking/ReservationTest.php:78-89` (`test_staff_can_assign_room_at_checkin`) — HTTP test, asserts `data.status === 'checked_in'` after `POST .../assign-room`.
- `tests/Feature/Notification/NotificationTriggersTest.php:40-87` — both tests POST to `assign-room` on a merely `confirmed` reservation and assert `RoomAssigned` fired a notification; under D-03 this reservation is not `checked_in`, so `RoomAssigned` will not fire at all.

**Why it happens:** these tests were written when `AssignRoomAction` *was* the check-in verb (its own docblock still says "Checks a guest in... this is primarily the check-in transition" — confirmed current file content, not yet updated for this phase).

**How to avoid:** Rewrite each test to call the new `CheckInReservationAction`/`POST .../check-in` for the check-in behavior, and keep an `assign-room` test only for the room-move-during-a-stay case (checked-in reservation, room actually changes, `RoomAssigned` fires). `NotificationTriggersTest` needs its fixtures to first transition the reservation to `checked_in` (via the new check-in action/endpoint) before calling `assign-room` to prove the move notification.

**Warning signs:** Full suite regressions in these four files immediately after `AssignRoomAction` is narrowed, before any new Phase 3 test runs.

### Pitfall 2: `findFreeRoom()`'s ordering doesn't implement D-04's "prefer available over dirty" rule

**What goes wrong:** `CheckAvailabilityAction::findFreeRoom()` (current code) orders candidates by `->orderBy('number')` only. D-04 requires the picker to exclude `maintenance`, then prefer `available` over `dirty`, then lowest `number`. Reusing `findFreeRoom()` unmodified for check-in auto-pick will hand out a `dirty` room ahead of an `available` one whenever the dirty room has a lower number.

**Why it happens:** `findFreeRoom()` predates Phase 2's housekeeping-status model entirely (its own comment says "`rooms.status` is a housekeeping state for today and says nothing about whether a room is free three weeks out" — written when `RoomStatus` had no `DIRTY` value and habitability wasn't yet a filter for check-in specifically, only for booking-time reservation which never needs a *habitable-now* room).

**How to avoid:** Either (a) add a `?bool $preferAvailable = false` parameter to `findFreeRoom()` that changes the `orderBy` to `orderByRaw("status = 'available' desc")->orderBy('number')` and excludes `maintenance` explicitly, used only by the check-in path, or (b) add a new `findFreeRoomForCheckIn()` method that layers the maintenance-exclude + available-preference on top of `findFreeRoom()`'s existing capacity logic. Do not change `findFreeRoom()`'s default behavior for the booking-time caller (`CreateReservationAction`), which correctly has no habitability requirement three weeks out.

**Warning signs:** A test with one `dirty` room (lower number) and one `available` room (higher number) of the same type, checking in without an explicit `room_uuid`, that receives the `dirty` room.

### Pitfall 3: `GenerateFolioAction` silently rewrites totals after a forced checkout

**What goes wrong:** Described in Anti-Patterns above — a second call to `GenerateFolioAction` (e.g., staff opens the folio detail screen after a forced checkout with an open folio) deletes and rebuilds every line item, potentially changing `total_usd` if any priced service request or booking's price changed in the interim, or if the room-charge total differs.

**Why it happens:** `GenerateFolioAction`'s existing guard only checks `FolioStatus::SETTLED`; it has no concept of the *reservation's* status.

**How to avoid:** Add the reservation-status guard described in Anti-Patterns before this phase closes — it is explicitly named in D-06 ("`GenerateFolioAction` must not rebuild items for a `checked_out` reservation").

**Warning signs:** A test that force-checks-out a reservation with an open folio, then calls `GET /cms/reservations/{r}/folio` (or re-invokes `GenerateFolioAction` directly) and asserts the total is unchanged from what it was at check-out time.

### Pitfall 4: Hotel-local "today" computed inconsistently between check-in and the room board

**What goes wrong:** `FrontDeskService::board()` currently defaults its date filter to `now()->toDateString()` (server/UTC — `FrontDeskService.php:49`). If `CheckInReservationAction`'s stay-window check uses `CarbonImmutable::now(config('hotel.timezone'))` but the board keeps using bare `now()`, the two can disagree about "today" whenever the hotel's timezone offset crosses UTC midnight before/after the server's own midnight — a guest could check in successfully (hotel-local today is still within window) while the board still shows them as "arriving tomorrow" or vice versa.

**Why it happens:** Phase 2 shipped before `config('hotel.timezone')` existed; D-02 explicitly flags this ("align `FrontDeskService` to `config('hotel.timezone')` in this phase; small change, note in summary") as a required fix, not optional cleanup.

**How to avoid:** Change `FrontDeskService::board()`'s default date to `CarbonImmutable::now(config('hotel.timezone'))->toDateString()` in the same phase. This is a one-line, additive-in-behavior change (only shifts the *default* when `?date=` is omitted) but must not be skipped — it is named explicitly in the locked decisions, not left to discretion.

**Warning signs:** A board test and a check-in test using the same wall-clock time but disagreeing on whether "today" has the same value.

### Pitfall 5: `activity()->log()` calls need a subject that is already `LogsActivity`-enabled — verify `causedBy(null)` behaves for the guest-express path

**What goes wrong:** D-08 requires `reservation.check_out_guest_express` to log with `actor = null` (guest express checkout has no `User` causer). Spatie's `causedBy()` accepts a model or `null`; passing `null` is supported but must be tested explicitly, since every existing activity-log call site in this codebase (Phase 1/2) always has a real `User` causer — there is no existing precedent in this repo for a null-causer activity log entry.

**Why it happens:** Untested code path — `UpdateRoomStatusAction`'s actor also becomes nullable this phase (D-08), and Phase 2's existing tests only ever pass a real `User`. A null actor is new to both call sites simultaneously.

**How to avoid:** Add an explicit unit/feature test asserting `activity_log.causer_id` is `null` and `causer_type` is `null` (or absent) for both the guest-express check-out log entry and a null-actor `UpdateRoomStatusAction::handle()` call, before relying on it in the check-out flow.

**Warning signs:** A test that force-checks-out via `ApproveFolioAction` (guest path) and asserts on `activity_log` fails with a foreign-key or type-coercion error instead of a clean null.

## Runtime State Inventory

Not applicable — this is a greenfield feature-addition phase (new routes, new columns, new Actions), not a rename/refactor/migration phase. No existing runtime state changes names or identity.

## Common Pitfalls (Test Migration Detail)

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RESV-01 | Staff can set and update free-text notes on a reservation (`PATCH /cms/reservations/{reservation}/notes`) | Pattern: additive `reservations.notes` column (D-11); `UpdateReservationNotesAction` follows the existing single-column transactional-update shape used by `SettleFolioAction`'s update calls; `ReservationResource` staff-only exposure via `$request->user('users') !== null`, confirmed this guard pattern exists nowhere yet in `ReservationResource` today — new code, not an extension of an existing conditional |
| RESV-02 | Staff can list the rooms available for a reservation's type and dates (`GET /cms/reservations/{reservation}/available-rooms`) | Reuses `CheckAvailabilityAction`'s overlap predicate (Pattern 1); ≤3-query bound is achievable by mirroring `FrontDeskService`'s set-based-query discipline (Pitfall 8 in `PITFALLS.md`) |
| RESV-03 | Check-in verb: assigns room, sets `checked_in`, stamps `checked_in_at`, stay-window gate with early-check-in override, narrows `assign-room` | `CheckInReservationAction` design in Architecture Patterns 2-3; existing `AssignRoomAction` code is the direct precedent for the transaction/lock/room-resolution shape being narrowed and partially relocated; Pitfall 1 (test migration) and Pitfall 2 (picker ordering) are the primary execution risks |
| RESV-04 | Check-out verb: folio-unsettled gate with staff-force override, room-dirty write, `ReservationCheckedOut` event, shared with guest express checkout | `CheckOutReservationAction` design in Architecture Patterns; reuses `SettleFolioAction`'s lock-then-check pattern and `UpdateRoomStatusAction` as the single room-status writer (Pitfall 3 for the `GenerateFolioAction` guard, Pitfall 5 for the null-actor path) |
| DOCS-01 | Guides/Postman/`carlton-tree.html` updated per phase | `docs/carlton-tree.html:268-274` nodes "assign room · confirm · cancel" and "check in · check out" and "reservation notes" identified exactly (line numbers confirmed by direct read); `API_GUIDE_DASHBOARD.md:927-977` assign-room section and status-reference table identified exactly; `CHANGELOG_MOBILE_API.md`'s existing breaking-change table format (lines 23-38) is the template for announcing the assign-room behavior change |
| XCUT-01 | New permissions seeded + listed | Not triggered this phase — D-13 confirms zero new permissions; the phase summary's Permissions section uses the established empty form: "None — no new permissions this phase." |
</phase_requirements>

## Code Examples

### Existing AssignRoomAction (full current body, to be narrowed)
```php
// Source: backend/app/Actions/Booking/AssignRoomAction.php (this repo, current state)
public function handle(Reservation $reservation, ?Room $room = null): array
{
    if ($reservation->status !== ReservationStatus::CONFIRMED) {
        throw new ReservationStateException(__('custom.errors.reservation_state'));
    }
    $reservationRoom = $reservation->rooms()->first();
    if (! $reservationRoom) {
        throw new ReservationStateException(__('custom.errors.reservation_state'));
    }
    $room ??= $reservationRoom->room;
    if (! $room) {
        throw new ReservationStateException(__('custom.errors.reservation_state'));
    }
    if ($reservationRoom->room_type_id !== $room->room_type_id) {
        throw new ReservationStateException(__('custom.errors.reservation_state'));
    }
    $alreadyAssigned = $room->reservationRooms()
        ->whereNotNull('room_id')
        ->whereHas('reservation', function ($q) use ($reservation) {
            $q->where('id', '!=', $reservation->id)
              ->where('check_in', '<', $reservation->check_out)
              ->where('check_out', '>', $reservation->check_in)
              ->holdingInventory();
        })->exists();
    if ($alreadyAssigned) {
        throw new RoomAlreadyAssignedException(__('custom.errors.room_already_assigned'));
    }
    DB::transaction(function () use ($reservationRoom, $room, $reservation) {
        $reservationRoom->update(['room_id' => $room->id]);
        $reservation->update([
            'status' => ReservationStatus::CHECKED_IN,
            'checked_in_at' => $reservation->checked_in_at ?? now(),
        ]);
    });
    event(new RoomAssigned($reservation));
    return ['data' => $reservation->refresh()->load(['rooms.room', 'rooms.roomType']), 'code' => 200];
}
```
Narrowed version (D-03) keeps the `CONFIRMED`-or-`CHECKED_IN` guard, the type-match check, the overlap check (now via the extracted `isRoomFree()`), and the transaction, but drops the `status`/`checked_in_at` write and only fires `RoomAssigned` when `$reservation->status === ReservationStatus::CHECKED_IN && $reservationRoom->room_id !== $room->id` (checked *before* the update, inside the same lock).

### SettleFolioAction's lock-then-check pattern (template for CheckOutReservationAction)
```php
// Source: backend/app/Actions/Folio/SettleFolioAction.php (this repo, current state)
public function handle(Folio $folio, string $method, float $amount, User $recorder, ?string $notes = null): array
{
    return DB::transaction(function () use ($folio, $method, $amount, $recorder, $notes) {
        $locked = Folio::where('id', $folio->id)->lockForUpdate()->firstOrFail();
        if ($locked->status === FolioStatus::SETTLED) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }
        $this->recordCashPayment->handle($locked, $method, $amount, $recorder, $notes);
        $locked->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
        return ['data' => $locked->fresh()->load(['items', 'payments']), 'code' => 200];
    });
}
```
`CheckOutReservationAction` needs both a reservation lock and a folio lock in the same transaction (`Reservation::where(...)->lockForUpdate()->firstOrFail()` then `$locked->folio()->lockForUpdate()->first()`), following this exact shape.

### ApproveFolioAction (guest express checkout — current body, to be refactored per D-08)
```php
// Source: backend/app/Actions/Folio/ApproveFolioAction.php (this repo, current state)
public function handle(Reservation $reservation): array
{
    return DB::transaction(function () use ($reservation) {
        $result = $this->generateFolio->handle($reservation);
        $folio  = $result['data'];
        $folio->update(['approved_by_guest_at' => now()]);
        $reservation->update([
            'status'         => ReservationStatus::CHECKED_OUT,
            'checked_out_at' => now(),
        ]);
        return ['data' => $folio->fresh()->load('items'), 'code' => 200];
    });
}
```
D-08 refactor: generate folio (unchanged first step) → stamp `approved_by_guest_at` (unchanged) → **delegate the status/timestamp/room-dirty/event work to `CheckOutReservationAction::handle($reservation, CheckOutMode::GUEST_EXPRESS, null)`** instead of inlining the `$reservation->update([...])` call directly. This removes the current duplication between `ApproveFolioAction` and the new staff check-out path, and is the mechanism that makes `ReservationCheckedOut` fire for both staff and guest checkouts from one code path, satisfying RESV-04's "guest express checkout shares the same action."

### BaseRequest::messages() already covers every new rule name

No change needed to `app/Base/BaseRequest.php`. Confirmed by direct read: `required_if` (line 38), `boolean` (line 42), and `exists` (line 61) are already mapped to `custom.validation.*` keys in all cases this phase needs (`reason` required_if `force`/`early_check_in` is true; `room_uuid` boolean/exists checks). The only lang-file work is adding the new `errors.*`/`messages.*` keys named in D-14, not new `validation.*` mappings.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| `AssignRoomAction` is both "assign a room" and "check the guest in" | `AssignRoomAction` narrowed to pure assignment; `CheckInReservationAction` is the only check-in verb | This phase (D-01, D-03) | Documented breaking behavioral change for the dashboard — `POST .../assign-room` no longer changes `status`; `CHANGELOG_MOBILE_API.md` must carry a new breaking-change row following the existing table format |
| `ApproveFolioAction` inlines its own status/timestamp update for guest checkout | `ApproveFolioAction` delegates to `CheckOutReservationAction` (`GUEST_EXPRESS` mode) | This phase (D-08) | One checkout code path instead of two; `ReservationCheckedOut` now fires for guest express checkout too (it never fired any event before) |
| `FrontDeskService::board()` defaults "today" to server/UTC `now()` | Defaults to `CarbonImmutable::now(config('hotel.timezone'))` | This phase (D-02, retroactively fixing a Phase 2 gap) | Only changes behavior when `?date=` is omitted and the server/hotel timezones disagree on the calendar date at the moment of the request |
| `UpdateRoomStatusAction::handle()` requires a non-null `User $actor` | Actor becomes `?User $actor` (nullable, for system-initiated changes) | This phase (D-08) | Existing Phase 2 tests that assert non-null `changed_by` need a new null-actor test case added alongside them, not a rewrite |

**Deprecated/outdated:** None — no library version bumps or removed APIs in this phase.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `ShouldDispatchAfterCommit` (Laravel's after-commit event interface) is available and behaves as expected under `laravel/framework ^13.8` with `QUEUE_CONNECTION=sync` in tests (dispatch deferred to commit, then the `ShouldQueue` listener runs synchronously right after) | Standard Stack, Pattern 5, State of the Art | Low — this is a documented core Laravel feature since v11 and the project is confirmed on `^13.8`; the only untested variable is interaction with `RefreshDatabase`'s test-wrapping transaction, which Laravel explicitly special-cases (`ShouldDispatchAfterCommit` treats the outermost *test* transaction as "no transaction," dispatching immediately in tests) — verify this with one passing test early rather than assuming |
| A2 | No `App\Providers\EventServiceProvider` with an explicself `$listen` array exists, so listener auto-discovery (parameter-type scanning) is the only registration mechanism in play | Pattern 5 | Low-medium — if an explicit `$listen` mapping does exist and only maps `RoomAssigned::class => [SendRoomReadyNotification::class]`, the union-type change to the listener's `handle()` signature is still correct but an additional explicit `GuestCheckedIn::class => [SendRoomReadyNotification::class]` entry may also be needed; confirm during planning by checking `bootstrap/providers.php` / `app/Providers/` for an `EventServiceProvider` |
| A3 | `RoomAvailabilityService` vs. extending `CheckAvailabilityAction` is genuinely Claude's/planner's discretion per D-04's own wording ("or methods on `CheckAvailabilityAction`") | Standard Stack (Alternatives), Pattern 1 | Low — this is an explicit discretion point in `03-CONTEXT.md`, not a disputed fact; the recommendation is a judgment call, not a verified requirement |

## Open Questions

1. **How does `folio_status` filtering get implemented on `GET /cms/reservations` when `BaseFilter` only whitelists direct columns?**
   - What we know: D-10 requires `status` (existing enum column, trivial `safeParms` entry) and `folio_status=open|settled` (no column exists on `reservations`; `Folio` is a separate table via `hasOne`).
   - What's unclear: `BaseFilter::applyCondition()` only calls `$query->where($field, ...)` — it has no built-in relation-join support. A `ReservationFilter` overriding `apply()` (or `applyConditions()`) to add a `whereHas('folio', fn ($q) => $q->where('status', $value))` conditional for `folio_status` specifically is the natural fix, following the same "override `apply()` only for search/joins" guidance already documented in `BaseFilter`'s own docblock.
   - Recommendation: planner should have the `ReservationFilter` constructor accept `folio_status` outside the `safeParms` whitelist (read directly from `$this->params['folio_status'] ?? null` in an overridden `apply()`), matching the pattern `BaseFilter`'s docblock already describes for custom joins, rather than trying to force it through the generic operator DSL.

2. **Exact query count achievable for `available-rooms` (D-12's "≤ 3 queries")**
   - What we know: the three natural queries are (1) reservation + first `reservation_room` + `roomType` (one eager-loaded `show()`-style query), (2) candidate rooms of that type (`is_active`, not `maintenance`), (3) overlapping `reservation_room` rows for the window (the shared predicate).
   - What's unclear: whether the currently-assigned room (needed for the `assigned: true` flag) requires a fourth lookup or can be derived from query (1)'s already-loaded `reservation_rooms.room_id` without an extra query — it can, since `reservation->rooms()->first()->room_id` is already in memory from query (1).
   - Recommendation: confirm with `expectsDatabaseQueryCount(3)` during implementation; the design above should hit exactly 3 if the first reservation_room is eager-loaded once.

3. **Does the `early_check_in` day-before rule need to reject when `check_in` is `today` already (i.e., is early check-in only for exactly one day before, never for a same-day-but-early-hour situation)?**
   - What we know: D-02 states "permits check-in on the day BEFORE `check_in` only (never earlier, never after `check_out`)" — this is unambiguous for the *date* comparison but doesn't address whether a same-day-but-technically-before-typical-checkin-hour scenario needs special handling (it doesn't — `check_in`/`check_out` are `date`-only columns, no time-of-day concept exists in this schema).
   - What's unclear: nothing substantive — flagged only so the planner writes the boundary test explicitly (today == check_in date passes the normal window check and needs no `early_check_in` flag at all; only today == check_in-minus-one-day needs the override).
   - Recommendation: no further research needed; this is a direct reading of D-02, included here only to make the boundary condition explicit for the plan's test list.

## Environment Availability

**SKIPPED (no external dependencies identified)** — this phase is pure application code (Actions, Requests, Resources, one migration, one config file, lang keys, docs) against the already-running Laravel/SQLite(test)/MySQL(prod) stack. No new CLI tool, service, or runtime is introduced.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.12 `[VERIFIED: backend/phpunit.xml + .planning/codebase/TESTING.md]` |
| Config file | `backend/phpunit.xml` (SQLite `:memory:`, `APP_ENV=testing`, `QUEUE_CONNECTION=sync`) |
| Quick run command | `php artisan test --filter=CheckInTest` (or the relevant class name) |
| Full suite command | `php artisan test` (must stay green — 1071/1071 at Phase 2 close) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| RESV-01 | Happy path: staff sets/updates/clears notes | feature | `php artisan test --filter=test_staff_can_update_reservation_notes` | ❌ Wave 0 — `tests/Feature/Reservations/ReservationNotesTest.php` |
| RESV-01 | 401 unauthenticated | feature | `php artisan test --filter=ReservationNotesTest` | ❌ Wave 0 |
| RESV-01 | 403 wrong permission | feature | `php artisan test --filter=ReservationNotesTest` | ❌ Wave 0 |
| RESV-01 | 422 validation (notes too long, wrong type) | feature | `php artisan test --filter=ReservationNotesTest` | ❌ Wave 0 |
| RESV-01 | Guest routes never expose `notes` key | feature | `php artisan test --filter=ReservationNotesTest` | ❌ Wave 0 |
| RESV-02 | Happy path: lists only free rooms of the right type/dates, currently-assigned room marked `assigned:true` | feature | `php artisan test --filter=AvailableRoomsTest` | ❌ Wave 0 — `tests/Feature/Reservations/AvailableRoomsTest.php` |
| RESV-02 | 401 / 403 (`reservations.view`) | feature | `php artisan test --filter=AvailableRoomsTest` | ❌ Wave 0 |
| RESV-02 | Bounded query count (≤3) | feature | `php artisan test --filter=test_available_rooms_is_bounded_to_three_queries` (`expectsDatabaseQueryCount`) | ❌ Wave 0 |
| RESV-02 | `maintenance` rooms excluded; overlapping-stay rooms excluded | feature | `php artisan test --filter=AvailableRoomsTest` | ❌ Wave 0 |
| RESV-03 | Happy path: check-in with explicit room, pre-assigned room, auto-pick | feature + unit | `php artisan test --filter=CheckInTest`; `php artisan test --filter=CheckInReservationActionTest` | ❌ Wave 0 — `tests/Feature/Reservations/CheckInTest.php`, `tests/Unit/Booking/CheckInReservationActionTest.php` |
| RESV-03 | 422 `reservation_state` on non-confirmed reservation | unit + feature | same as above | ❌ Wave 0 |
| RESV-03 | 422 `reservation_outside_stay_window` outside window; early-check-in override day-before only | feature | `php artisan test --filter=CheckInTest` | ❌ Wave 0 |
| RESV-03 | 422 `room_out_of_order` for a `maintenance` room; `dirty` room is allowed | unit | `php artisan test --filter=CheckInReservationActionTest` | ❌ Wave 0 |
| RESV-03 | `GuestCheckedIn` dispatched, `SendRoomReadyNotification` fires (shared listener) | feature | `php artisan test --filter=NotificationTriggersTest` (extended) | 🔶 exists, needs new test method |
| RESV-03 | Activity log records `reservation.early_check_in` with `reason`/`check_in`/`today` | feature | `php artisan test --filter=CheckInTest` | ❌ Wave 0 |
| RESV-03 | `assign-room` no longer flips status; still fires `RoomAssigned` only on a real move while `checked_in` | feature | `php artisan test --filter=ReservationTest` (rewritten) + `RoomAssignmentAtBookingTest` (rewritten) | 🔶 exists, needs rewrite |
| RESV-04 | Happy path: check-out with settled folio | feature + unit | `php artisan test --filter=CheckOutTest`; `php artisan test --filter=CheckOutReservationActionTest` | ❌ Wave 0 — `tests/Feature/Reservations/CheckOutTest.php`, `tests/Unit/Booking/CheckOutReservationActionTest.php` |
| RESV-04 | 422 `folio_unsettled` when open + no force | unit + feature | same as above | ❌ Wave 0 |
| RESV-04 | 403 `force:true` without `folios.settle` | feature | `php artisan test --filter=CheckOutTest` | ❌ Wave 0 |
| RESV-04 | Staff force override: 200, folio stays open, `reservation.check_out_forced` activity log entry with `folio_uuid`/`folio_status`/`total_usd`/`reason`/causer | feature | `php artisan test --filter=CheckOutTest` | ❌ Wave 0 |
| RESV-04 | Guest express checkout: shares the action, logs `reservation.check_out_guest_express`, null causer | feature | `php artisan test --filter=test_guest_express_checkout_shares_the_check_out_action` (regression per Claude's Discretion list) | ❌ Wave 0 |
| RESV-04 | Rooms not already `dirty` moved to `dirty` via `UpdateRoomStatusAction` (system actor), idempotent | unit | `php artisan test --filter=CheckOutReservationActionTest` | ❌ Wave 0 |
| RESV-04 | `ReservationCheckedOut` dispatched after commit, `Event::fake([ReservationCheckedOut::class])` | feature | `php artisan test --filter=CheckOutTest` | ❌ Wave 0 |
| RESV-04 | Concurrency: settle and check-out do not interleave | feature | `php artisan test --filter=test_settle_and_check_out_do_not_interleave` (Claude's Discretion) | ❌ Wave 0 |
| DOCS-01 | Docs/Postman/tree updated | manual + scripted check | route-list + tree-flip verification (Phase 1/2 precedent scripts) | 🔶 pattern exists (`01-04`/`02-04` plans), no phase 3 script yet |

### Sampling Rate

- **Per task commit:** `php artisan test --filter=<ClassJustTouched>`
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** Full suite green (currently 1071/1071 at Phase 2 close) before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Reservations/ReservationNotesTest.php` — covers RESV-01
- [ ] `tests/Feature/Reservations/AvailableRoomsTest.php` — covers RESV-02
- [ ] `tests/Feature/Reservations/CheckInTest.php` — covers RESV-03
- [ ] `tests/Feature/Reservations/CheckOutTest.php` — covers RESV-04
- [ ] `tests/Unit/Booking/CheckInReservationActionTest.php` — covers RESV-03 edge cases (maintenance room, room-type mismatch, stay-window boundaries)
- [ ] `tests/Unit/Booking/CheckOutReservationActionTest.php` — covers RESV-04 edge cases (force gate, idempotent room-dirty, null actor)
- [ ] Rewrite (not new, but a required edit before these pass): `tests/Feature/Booking/RoomAssignmentAtBookingTest.php`, `tests/Feature/Booking/StayTest.php:83-91`, `tests/Feature/Booking/ReservationTest.php:78-89`, `tests/Feature/Notification/NotificationTriggersTest.php:40-87`
- [ ] No framework install needed — PHPUnit and all Laravel testing helpers already present and configured

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-------------------|
| V2 Authentication | No — this phase adds no auth mechanism | Existing Sanctum guards (`auth:users`) unchanged |
| V3 Session Management | No | Existing token guards unchanged |
| V4 Access Control | Yes | `permission:reservations.view` / `permission:reservations.create` middleware on every new route; the check-out force override additionally requires `permission:folios.settle` inside the controller/Action (not just route middleware, since the same route serves both forced and non-forced requests — D-07's 403 mapping must be enforced in code, not just route gating) |
| V5 Input Validation | Yes | `CheckInReservationRequest`/`CheckOutReservationRequest`/`UpdateReservationNotesRequest` extend `App\Base\BaseRequest`; `required_if:force,true`/`required_if:early_check_in,true` for `reason`, `exists:rooms,uuid` for `room_uuid`, `max:255`/`max:2000` length caps — all rule names already mapped in `BaseRequest::messages()` |
| V6 Cryptography | No | No new secrets, tokens, or crypto in this phase |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|----------------------|
| Staff without `folios.settle` sends `force:true` to bypass the folio-unsettled gate | Elevation of Privilege | D-07: controller/Action must explicitly check `folios.settle` before honoring `force:true`, returning 403 `forbidden` (the existing permission-error shape) rather than silently downgrading to `NONE` mode or 422 |
| Race between `SettleFolioAction` and `CheckOutReservationAction` reading/writing the same folio concurrently | Tampering (TOCTOU) | Both actions must `lockForUpdate()` the folio row inside `DB::transaction()` — exactly the P8 TOCTOU-fix pattern already proven in `SettleFolioAction`; this is the concurrency test explicitly named in Claude's Discretion ("a concurrency test that settle and check-out do not interleave") |
| A stale/duplicate `check-out` request re-fires `UpdateRoomStatusAction` on a room already `dirty`, or re-fires `ReservationCheckedOut` | Repudiation / inconsistent state | D-06 requires "idempotent 'ensure dirty'" — skip the `UpdateRoomStatusAction` call entirely when the room is already `dirty` (not merely calling it and letting the transition table reject a same-state change, since `RoomStatus::canTransitionTo()` rejects `dirty→dirty` as an *error*, not a no-op — confirmed by direct read of `RoomStatus::allowedTargets()`, which never lists a state as its own target) |
| Guest express checkout (`ApproveFolioAction`) invoked twice for the same reservation | Tampering / duplicate side effects | `CheckOutReservationAction`'s own status guard (`must be checked_in`) already makes a second call fail with `reservation_state` on the second invocation — no additional idempotency key needed since the guard is a status transition, not a payment |

## Sources

### Primary (HIGH confidence — direct repo inspection, this session)
- `backend/app/Actions/Booking/{AssignRoomAction,CreateReservationAction,CheckAvailabilityAction}.php` — current bodies read in full
- `backend/app/Actions/Folio/{ApproveFolioAction,GenerateFolioAction,SettleFolioAction}.php` — current bodies read in full
- `backend/app/Actions/Cms/UpdateRoomStatusAction.php`, `app/Enums/RoomStatus.php`, `app/Services/Operations/FrontDeskService.php` — Phase 2 precedent read in full
- `backend/app/Models/{Reservation,ReservationRoom,Folio,Room}.php`, `app/Enums/{ReservationStatus,FolioStatus}.php` — read in full
- `backend/app/Events/RoomAssigned.php`, `app/Listeners/SendRoomReadyNotification.php` — read in full
- `backend/app/Http/Controllers/Admin/ReservationController.php`, `app/Http/Resources/Booking/ReservationResource.php`, `app/Services/Booking/ReservationService.php` — read in full
- `backend/routes/api.php:555-731` — reservations, front-desk route groups read directly
- `backend/database/seeders/RolesAndPermissionsSeeder.php` — full file read (confirms zero new permissions needed)
- `backend/tests/Feature/Booking/{RoomAssignmentAtBookingTest,StayTest,ReservationTest}.php`, `tests/Feature/Notification/NotificationTriggersTest.php` — exact sections read, test-migration risk confirmed by direct assertion inspection
- `backend/docs/carlton-tree.html:260-279`, `backend/docs/API_GUIDE_DASHBOARD.md:900-978`, `backend/docs/CHANGELOG_MOBILE_API.md:1-60`, `backend/docs/postman/carlton-api.postman_collection.json` (reservation-related item names grepped) — read directly
- `backend/lang/en/custom.php:1-71` — confirms existing error/message key namespace and naming convention
- `backend/composer.json:15` — `laravel/framework: ^13.8` confirmed
- `backend/.env.example` — confirms `APP_TIMEZONE=UTC`, `QUEUE_CONNECTION=database` (prod default; tests use `sync` per `phpunit.xml`), no `HOTEL_TIMEZONE` entry yet
- `backend/app/Base/{BaseFilter,BaseRequest}.php` — full files read; confirms no `ReservationFilter` exists yet and confirms `required_if`/`boolean`/`exists` are already mapped in `BaseRequest::messages()`

### Secondary (MEDIUM confidence — project-generated planning docs, cross-checked against code)
- `.planning/research/ARCHITECTURE.md` — used for the general event-vs-sync-check pattern (Pattern 2, confirmed still correct), but its concrete class/table/exception names are superseded by `03-CONTEXT.md` where they disagree (flagged explicitly in Summary)
- `.planning/research/PITFALLS.md` — Pitfall 1 (status/availability conflation) and Pitfall 3 (night-audit timezone framing, applied here to the stay-window/hotel-timezone problem) directly informed Pitfalls 2 and 4 above
- `.planning/phases/02-rooms-status-lifecycle-grids/SUMMARY.md` and `02-CONTEXT.md` — confirms `UpdateRoomStatusAction`'s exact signature, the transition table, and the board's current (UTC) "today" default

### Tertiary (LOW confidence)
- None — no web sources were needed for this phase; it is a closed-world extension of an already-fully-inspected codebase.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages, every class/interface verified present in `composer.json`/existing code
- Architecture: HIGH — every pattern is either extracted from existing code (Pattern 1, 4) or names an existing precedent it must match (Pattern 2, 3, 5)
- Pitfalls: HIGH — Pitfall 1 (test breakage) is verified by direct line-by-line read of the four affected test files, not inferred

**Research date:** 2026-09-26
**Valid until:** 30 days (stable internal codebase, no external dependency drift risk) — re-verify only if Phase 2 or the milestone's roadmap changes before Phase 3 execution begins
