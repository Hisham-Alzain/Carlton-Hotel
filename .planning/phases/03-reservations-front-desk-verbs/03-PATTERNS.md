# Phase 3: Reservations Front-Desk Verbs - Pattern Map

**Mapped:** 2026-09-26
**Files analyzed:** 27 (new + modified)
**Analogs found:** 22 / 27 (5 have no close in-repo analog; use RESEARCH.md Code Examples instead)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Actions/Booking/CheckInReservationAction.php` | service (action) | CRUD (transactional state transition) | `app/Actions/Booking/AssignRoomAction.php` (current, pre-narrow) | exact |
| `app/Actions/Booking/CheckOutReservationAction.php` | service (action) | CRUD (lock-then-check transition) | `app/Actions/Folio/SettleFolioAction.php` | exact |
| `app/Actions/Booking/AssignRoomAction.php` (narrowed) | service (action) | CRUD | itself, current body | exact (self-narrowing) |
| `app/Actions/Booking/CheckAvailabilityAction.php` (+`isRoomFree`) | service (action) | request-response (read) | itself, `overlapping()`/`findFreeRoom()` | exact |
| `app/Actions/Booking/UpdateReservationNotesAction.php` | service (action) | CRUD (single-column update) | `app/Actions/Folio/SettleFolioAction.php` (transactional update shape, simpler) | role-match |
| `app/Actions/Folio/GenerateFolioAction.php` (guard added) | service (action) | CRUD | itself, existing `SETTLED` guard | exact |
| `app/Actions/Folio/ApproveFolioAction.php` (refactored) | service (action) | CRUD | itself, current body | exact |
| `app/Actions/Cms/UpdateRoomStatusAction.php` (nullable actor) | service (action) | CRUD (transactional + history) | itself, current body | exact |
| `app/Enums/CheckOutMode.php` | model (enum) | — | `app/Enums/FolioStatus.php` (backed enum, no analog read but same convention) | role-match |
| `app/Exceptions/ReservationOutsideStayWindowException.php` | model (exception) | — | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Exceptions/RoomOutOfOrderException.php` | model (exception) | — | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Exceptions/FolioUnsettledException.php` | model (exception) | — | `app/Exceptions/RoomStatusTransitionException.php` | exact |
| `app/Events/GuestCheckedIn.php` | event | event-driven | `app/Events/RoomAssigned.php` | exact |
| `app/Events/ReservationCheckedOut.php` | event | event-driven | `app/Events/RoomAssigned.php` (shape) + Laravel `ShouldDispatchAfterCommit` (no repo precedent) | role-match |
| `app/Listeners/SendRoomReadyNotification.php` (union type) | event handler | event-driven | itself, current body | exact |
| `app/Http/Requests/Booking/CheckInReservationRequest.php` | request | request-response | `app/Http/Requests/Booking/AssignRoomRequest.php` | exact |
| `app/Http/Requests/Booking/CheckOutReservationRequest.php` | request | request-response | `app/Http/Requests/Booking/AssignRoomRequest.php` | role-match (needs `required_if`) |
| `app/Http/Requests/Booking/UpdateReservationNotesRequest.php` | request | request-response | `app/Http/Requests/Booking/AssignRoomRequest.php` | role-match |
| `app/Http/Controllers/Admin/ReservationController.php` (+4 methods) | controller | request-response | itself, `assignRoom()` method | exact |
| `app/Http/Resources/Booking/ReservationResource.php` (+notes, staff-only) | transform (resource) | request-response | itself, current body | exact |
| `app/Filters/ReservationFilter.php` | filter | request-response (query building) | `app/Filters/RoomFilter.php` (simple `safeParms` subclass) + `app/Base/BaseFilter.php` (`apply()` override point) | role-match |
| `app/Models/Reservation.php` (+`isWithinStayWindow`, `notes` fillable) | model | CRUD | itself, `scopeHoldingInventory`/`isDndActive` (predicate methods) | exact |
| `app/Services/Booking/ReservationService.php` (+notes/checkin/checkout/available-rooms delegation) | service | request-response | itself, `assignRoom()` delegation pattern (not yet read in full; same file) | exact |
| `app/Services/Operations/FrontDeskService.php` (`today` → hotel tz) | service | request-response | itself, current `now()` default | exact |
| `config/hotel.php` | config | — | `config/cms.php` (env-backed, documented single source of truth) | role-match |
| `database/migrations/..._add_notes_to_reservations_table.php` | migration | — | any additive Phase 1/2 migration (not read; convention is well-established, additive `text()->nullable()`) | role-match |
| `tests/Feature/Reservations/{ReservationNotesTest,AvailableRoomsTest,CheckInTest,CheckOutTest}.php` | test | request-response | `tests/Feature/Rooms/RoomStatusTransitionTest.php` | exact |
| `tests/Unit/Booking/{CheckInReservationActionTest,CheckOutReservationActionTest}.php` | test | CRUD | `tests/Feature/Rooms/RoomStatusTransitionTest.php` (unit-style transition assertions within it) | role-match |
| `tests/Feature/Notification/NotificationTriggersTest.php` (extended) | test | event-driven | itself, existing `Event::fake` usage (not re-read; named in RESEARCH.md) | exact |
| `tests/Feature/Booking/{RoomAssignmentAtBookingTest,StayTest,ReservationTest}.php` (rewrites) | test | CRUD | themselves, current bodies (per RESEARCH.md Pitfall 1 line numbers) | exact |

## Pattern Assignments

### `app/Actions/Booking/CheckInReservationAction.php` (service, CRUD)

**Analog:** `app/Actions/Booking/AssignRoomAction.php` (current pre-narrow body — this *is* the check-in transition today, being relocated/renamed)

**Imports pattern** (lines 1-11):
```php
namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Events\RoomAssigned;
use App\Exceptions\ReservationStateException;
use App\Exceptions\RoomAlreadyAssignedException;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
```
Add: `App\Events\GuestCheckedIn`, `App\Exceptions\ReservationOutsideStayWindowException`, `App\Exceptions\RoomOutOfOrderException`, `App\Enums\RoomStatus`, `Carbon\CarbonImmutable`, `App\Models\User`.

**Core transactional pattern** (lines 22-76, whole file):
```php
public function handle(Reservation $reservation, ?Room $room = null): array
{
    if ($reservation->status !== ReservationStatus::CONFIRMED) {
        throw new ReservationStateException(__('custom.errors.reservation_state'));
    }
    $reservationRoom = $reservation->rooms()->first();
    $room ??= $reservationRoom->room;
    if ($reservationRoom->room_type_id !== $room->room_type_id) {
        throw new ReservationStateException(__('custom.errors.reservation_state'));
    }
    // ... overlap check via CheckAvailabilityAction::isRoomFree() (extracted, see below)
    DB::transaction(function () use ($reservationRoom, $room, $reservation) {
        $reservationRoom->update(['room_id' => $room->id]);
        $reservation->update([
            'status' => ReservationStatus::CHECKED_IN,
            'checked_in_at' => $reservation->checked_in_at ?? now(),
        ]);
    });
    event(new RoomAssigned($reservation)); // → replace with GuestCheckedIn
    return ['data' => $reservation->refresh()->load(['rooms.room', 'rooms.roomType']), 'code' => 200];
}
```
Apply D-01/D-02: add stay-window check (`ReservationOutsideStayWindowException`), maintenance guard (`RoomOutOfOrderException`), and lock via `Reservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail()` inside `DB::transaction()` (SettleFolioAction's lock-then-check shape, see below) instead of the bare `update()` this action currently uses — the current file does not row-lock; Phase 3 must add it since this is now the authoritative CONFIRMED→CHECKED_IN transition guarding a resource other paths can also touch.

**Error handling pattern:** every guard throws a `DomainException` subclass immediately (no try/catch) — see Exception pattern below. Exceptions thrown *before* `DB::transaction()` starts abort with nothing written.

---

### `app/Actions/Booking/CheckOutReservationAction.php` (service, CRUD)

**Analog:** `app/Actions/Folio/SettleFolioAction.php` (lock-then-check pattern)

**Full pattern** (lines 1-32):
```php
namespace App\Actions\Folio;

use App\Enums\FolioStatus;
use App\Exceptions\ReservationStateException;
use App\Models\Folio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SettleFolioAction
{
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
}
```
`CheckOutReservationAction::handle()` must double-lock: `Reservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail()` then `$locked->folio()->lockForUpdate()->first()` (or generate via `GenerateFolioAction` inside the same transaction if missing), check status/folio state, write `status`/`checked_out_at`, loop `reservation_rooms` calling `UpdateRoomStatusAction::handle($room, RoomStatus::DIRTY, 'check-out', $actor)` skipping already-`dirty` rooms, then dispatch `ReservationCheckedOut` (`ShouldDispatchAfterCommit`, so dispatch call can sit inside or right after the transaction closure — the interface handles the after-commit timing itself).

---

### `app/Actions/Booking/AssignRoomAction.php` (narrowed, D-03)

**Analog:** itself — full current body already read above (77 lines). Remove the `status`/`checked_in_at` write from the `DB::transaction` closure; change the leading guard to accept `CONFIRMED` OR `CHECKED_IN`; move the overlap block to call the new `CheckAvailabilityAction::isRoomFree($room, $reservation)`; guard `event(new RoomAssigned($reservation))` behind `$reservation->status === ReservationStatus::CHECKED_IN && $reservationRoom->room_id !== $room->id` (checked before the update). Response must include `status` (already present via full resource reload).

---

### `app/Actions/Booking/CheckAvailabilityAction.php` (+`isRoomFree`)

**Analog:** itself, `overlapping()` (private) at lines 79-89 and `findFreeRoom()` at lines 46-63.

**Extraction target** (mirrors `AssignRoomAction`'s inline query at old lines 47-55, adapted to the class's `whereDate()` convention used at line 85-86):
```php
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
For the D-04 picker ("prefer `available` over `dirty`, exclude `maintenance`"), add a parameter or sibling method layered on top of `findFreeRoom()` (lines 46-63) rather than changing its default `orderBy('number')` — `CreateReservationAction` (booking time) must keep today's behavior unchanged.

---

### `app/Actions/Cms/UpdateRoomStatusAction.php` (nullable actor, D-08)

**Analog:** itself, full 58-line body already read above.

Change signature `User $actor` → `?User $actor`; guard the two write sites:
```php
$locked->forceFill([
    'status'            => $to,
    'status_changed_at' => now(),
    'status_changed_by' => $actor?->getKey(),
])->save();

RoomStatusHistory::create([
    'room_id'     => $locked->id,
    'from_status' => $from->value,
    'to_status'   => $to->value,
    'changed_by'  => $actor?->getKey(),
    'reason'      => $reason,
]);
```
Confirmed nullable columns already exist per D-08 (`room_status_history.changed_by`, `rooms.status_changed_by`).

---

### Domain exceptions: `ReservationOutsideStayWindowException`, `RoomOutOfOrderException`, `FolioUnsettledException`

**Analog:** `app/Exceptions/RoomStatusTransitionException.php` (full file, 9 lines):
```php
<?php

namespace App\Exceptions;

class RoomStatusTransitionException extends DomainException
{
    public function errorCode(): string { return 'room_status_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
```
Copy this shape exactly for all three new exceptions, swapping `errorCode()`'s string (`reservation_outside_stay_window`, `room_out_of_order`, `folio_unsettled`); all are 422. Context is passed via the base `DomainException` constructor at the throw site, e.g. (per RESEARCH.md Pattern 4):
```php
new RoomOutOfOrderException(__('custom.errors.room_out_of_order'), ['room_uuid' => $room->uuid, 'housekeeping_status' => $room->status->value]);
```
`ReservationStateException` (also 9 lines, identical shape) is the second concrete precedent confirming the pattern is uniform across the codebase.

---

### `app/Events/GuestCheckedIn.php`, `app/Events/ReservationCheckedOut.php`

**Analog:** `app/Events/RoomAssigned.php` (full 14-line file):
```php
<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Reservation $reservation) {}
}
```
`GuestCheckedIn` copies this exactly. `ReservationCheckedOut` additionally implements `\Illuminate\Contracts\Events\ShouldDispatchAfterCommit` (no in-repo precedent — first use of this interface; confirmed available in `laravel/framework ^13.8` per RESEARCH.md) and carries extra readonly properties `CheckOutMode $mode`, `?int $actorId`.

---

### `app/Listeners/SendRoomReadyNotification.php` (union type, D-05)

**Analog:** itself, full 29-line body already read above. Change only the `handle()` signature:
```php
public function handle(RoomAssigned|GuestCheckedIn $event): void
{
    $guest = $event->reservation->guest;
    if (! $guest) return;
    $this->notifications->pushToGuest(
        $guest,
        NotificationType::ROOM_READY,
        __('custom.notifications.room_ready_title'),
        __('custom.notifications.room_ready_body'),
        ['reservation_uuid' => $event->reservation->uuid],
    );
}
```
Add `use App\Events\GuestCheckedIn;` import. Both events must expose `public readonly Reservation $reservation` — confirmed identical property name/type on `RoomAssigned` and `GuestCheckedIn`.

---

### `app/Http/Requests/Booking/{CheckInReservationRequest,CheckOutReservationRequest,UpdateReservationNotesRequest}.php`

**Analog:** `app/Http/Requests/Booking/AssignRoomRequest.php` (full 18-line file):
```php
<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;

class AssignRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'room_uuid' => ['nullable', 'string', 'exists:rooms,uuid'],
        ];
    }
}
```
`CheckInReservationRequest`: `room_uuid` (same rule), `early_check_in` (`['sometimes','boolean']`), `reason` (`['required_if:early_check_in,true','nullable','string','max:255']`).
`CheckOutReservationRequest`: `force` (`['sometimes','boolean']`), `reason` (`['required_if:force,true','nullable','string','max:255']`).
`UpdateReservationNotesRequest`: `notes` (`['present','nullable','string','max:2000']`).
No `BaseRequest::messages()` changes needed — `required_if`/`boolean`/`exists` already mapped (confirmed by RESEARCH.md direct read of `app/Base/BaseRequest.php`).

---

### `app/Http/Controllers/Admin/ReservationController.php` (+4 methods)

**Analog:** itself, `assignRoom()` method (lines 55-63) and the class's overall shape (64 lines, full file read above):
```php
public function assignRoom(AssignRoomRequest $request, Reservation $reservation): JsonResponse
{
    $roomUuid = $request->validated('room_uuid');
    $room     = $roomUuid ? Room::where('uuid', $roomUuid)->firstOrFail() : null;

    $result = $this->service->assignRoom($reservation, $room);
    $result['data'] = new ReservationResource($result['data']);
    return $this->respondFromService($result, request: $request);
}
```
New methods (`checkIn`, `checkOut`, `updateNotes`, `availableRooms`) follow this exact shape: resolve validated params to models/scalars in the controller, delegate all business logic to `$this->service->*()`, wrap `$result['data']` in the resource, return via `$this->respondFromService()`. `cancel()` (lines 47-51) is the template for a no-body-resource action (`$this->success(...)`), not needed here since all four new verbs return the reservation resource.

---

### `app/Http/Resources/Booking/ReservationResource.php` (+staff-only `notes`)

**Analog:** itself, full 35-line body already read above.
```php
'dnd' => [
    'enabled' => $this->isDndActive(),
    'until'   => $this->dnd_until?->toIso8601String(),
],
```
This is the file's own precedent for staff-visible-only-in-context data—but no existing conditional narrows a whole key by guard type. New code:
```php
'notes' => $this->when($request->user('users') !== null, $this->notes),
```
placed alongside `'checked_out_at'`. On check-out response also load and add `'folio' => $this->whenLoaded('folio', fn () => ['uuid' => $this->folio->uuid, 'status' => $this->folio->status, 'total_usd' => $this->folio->total_usd])`.

---

### `app/Filters/ReservationFilter.php` (D-10)

**Analog:** `app/Filters/RoomFilter.php` (full 19-line file):
```php
<?php

namespace App\Filters;

class RoomFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'status' => ['eq', 'in'],
        'floor'  => ['eq'],
    ];

    protected array $searchable = ['number'];

    protected array $sortable = ['number', 'floor', 'created_at', 'updated_at'];
}
```
`ReservationFilter` should extend `App\Base\BaseFilter` directly (not `CmsContentFilter`, which is CMS-translation-specific), with `safeParms = ['status' => ['eq','in']]` for the plain column, then override `apply()` (per `BaseFilter`'s own docblock, lines 10-40, describing custom-join extension points) to additionally handle `folio_status` via `$query->whereHas('folio', fn ($q) => $q->where('status', $this->params['folio_status']))` since `folio_status` has no column on `reservations` and cannot go through the generic `safeParms` DSL.

---

### `app/Models/Reservation.php` (+`isWithinStayWindow`, `notes`)

**Analog:** itself, `isDndActive()` (lines 87-91) and `scopeHoldingInventory()` (lines 55-67) — both existing predicate-method precedents on this exact model:
```php
public function isDndActive(): bool
{
    return $this->dnd_until !== null && $this->dnd_until->isFuture();
}
```
New method:
```php
public function isWithinStayWindow(CarbonImmutable $hotelToday): bool
{
    $today = $hotelToday->toDateString();
    return $this->check_in->toDateString() <= $today && $today < $this->check_out->toDateString();
}
```
Add `'notes'` to `$fillable` (line 21-26 list) and no new cast (plain string/text column).

---

### `config/hotel.php`

**Analog:** `config/cms.php` (header comment block, lines 1-30, env-backed single-source-of-truth convention):
```php
return [
    'timezone' => env('HOTEL_TIMEZONE', 'Asia/Damascus'),
];
```
Follows the same "documented single source of truth, env-overridable" convention `cms.php` establishes for locales.

---

### `app/Actions/Folio/GenerateFolioAction.php` (guard added)

**Analog:** itself, existing guard at lines 24-28:
```php
if ($folio->status === FolioStatus::SETTLED) {
    return ['data' => $folio->load('items'), 'code' => 200];
}
```
Add a second guard directly after, in the same style, checking `$reservation->status === ReservationStatus::CHECKED_OUT` (needs `use App\Enums\ReservationStatus;` added to imports) before the `$folio->items()->delete()` rebuild block at line 31.

---

### `app/Actions/Folio/ApproveFolioAction.php` (refactored, D-08)

**Analog:** itself, full 28-line current body already read above:
```php
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
Replace the inline `$reservation->update([...])` with a delegated call: inject `CheckOutReservationAction`, call `$this->checkOutReservation->handle($reservation, CheckOutMode::GUEST_EXPRESS, null)` after the `approved_by_guest_at` stamp, inside the same or a nested transaction (its own action already opens one).

---

## Shared Patterns

### Domain exception shape (422, error_code)
**Source:** `app/Exceptions/RoomStatusTransitionException.php`, `app/Exceptions/ReservationStateException.php`
**Apply to:** `ReservationOutsideStayWindowException`, `RoomOutOfOrderException`, `FolioUnsettledException`
```php
class RoomStatusTransitionException extends DomainException
{
    public function errorCode(): string { return 'room_status_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
```
No `Handler.php` change needed — the global handler already converts any `DomainException` subclass to the envelope.

### Lock-then-check transaction
**Source:** `app/Actions/Folio/SettleFolioAction.php`, `app/Actions/Cms/UpdateRoomStatusAction.php`
**Apply to:** `CheckInReservationAction`, `CheckOutReservationAction`
```php
return DB::transaction(function () use (...) {
    $locked = Model::where('id', $model->id)->lockForUpdate()->firstOrFail();
    if (<invalid state>) { throw new SomeDomainException(...); }
    $locked->update([...]);
    return ['data' => $locked->fresh()->load([...]), 'code' => 200];
});
```

### Single writer of room status
**Source:** `app/Actions/Cms/UpdateRoomStatusAction.php`
**Apply to:** `CheckOutReservationAction` (must call this action for every `dirty` transition, never `$room->update(['status' => ...])` directly)

### Request → controller → service/action delegation, resource wrap
**Source:** `app/Http/Controllers/Admin/ReservationController.php::assignRoom()`
**Apply to:** all 4 new controller methods — validated data extracted in controller, business logic in service/action, `$result['data'] = new ReservationResource(...)`, `respondFromService()`.

### Event dispatched after commit, shared listener via union type
**Source:** `app/Events/RoomAssigned.php` + `app/Listeners/SendRoomReadyNotification.php`
**Apply to:** `GuestCheckedIn` (manual `event()` call, matching `RoomAssigned` style per RESEARCH.md's own recommendation) and `ReservationCheckedOut` (`ShouldDispatchAfterCommit` interface, per D-09).

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Enums/CheckOutMode.php` | model (enum) | — | No plain backed-enum file was read in full this pass; convention (PHP 8.1 backed enum, `App\Enums` namespace) is well-established project-wide per RESEARCH.md/`ReservationStatus`/`FolioStatus` usage — no new pattern needed, just standard PHP enum syntax |
| `app/Events/ReservationCheckedOut.php` (`ShouldDispatchAfterCommit` part only) | event | event-driven | First use of this interface in the repo; RESEARCH.md Pattern 5/Assumption A1 is the only guidance — verify with one passing test early |
| `database/migrations/..._add_notes_to_reservations_table.php` | migration | — | Not read directly this pass; any additive Phase 1/2 migration establishes the `Schema::table(...)->text('notes')->nullable()` convention, low risk |
| Lang files (5 locales) | config/i18n | — | `lang/en/custom.php:1-71` confirms existing `errors.*`/`messages.*` key namespace (per RESEARCH.md Sources); no single "add a key" analog needed beyond following that flat array shape |
| `docs/carlton-tree.html`, `API_GUIDE_DASHBOARD.md`, `CHANGELOG_MOBILE_API.md`, Postman collection | docs | — | Doc-only edits; RESEARCH.md already pinpoints exact line ranges (tree ~268-274, guide ~900-978, changelog breaking-change table lines 23-38) — no code pattern applies |

## Metadata

**Analog search scope:** `backend/app/{Actions,Events,Listeners,Exceptions,Filters,Http,Models,Services}`, `backend/config`, `backend/tests/Feature/Rooms`
**Files scanned:** 17 fully read (AssignRoomAction, CheckAvailabilityAction, SettleFolioAction, GenerateFolioAction, ApproveFolioAction, UpdateRoomStatusAction, RoomStatusTransitionException, ReservationStateException, RoomAssigned, SendRoomReadyNotification, RoomFilter, ReservationController, AssignRoomRequest, ReservationResource, Reservation model, config/cms.php header, BaseFilter docblock) + directory listing of all `app/Filters/*.php` for size comparison
**Pattern extraction date:** 2026-09-26
