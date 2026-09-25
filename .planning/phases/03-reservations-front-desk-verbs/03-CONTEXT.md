# Phase 3: Reservations Front-Desk Verbs - Context

**Gathered:** 2026-09-26
**Status:** Ready for planning
**Decided by:** Fable 5.1 consultant (owner delegated all decisions); D-01 and D-02 were additionally deliberated by a three-member ai-council (Architect, Skeptic, User Advocate / Risk & Security) whose amendments are folded in.

<domain>
## Phase Boundary

Staff run the desk flow on a reservation with explicit verbs: keep notes, list the rooms that can be assigned, check in, and check out against a settled folio. Four routes inside the existing `cms/reservations` group: `PATCH /cms/reservations/{reservation}/notes`, `GET /cms/reservations/{reservation}/available-rooms`, `POST /cms/reservations/{reservation}/check-in`, `POST /cms/reservations/{reservation}/check-out`. One additive column (`reservations.notes`), one small config (`hotel.timezone`), no new permissions. `assign-room` is narrowed to pure assignment (documented behaviour change). Requirements RESV-01..04 plus the DOCS-01 / XCUT-01 gate. Out of scope: no-show marking, multi-room check-in, online payments, folio line items (Phase 5), housekeeping tasks (Phase 6).

</domain>

<decisions>
## Implementation Decisions

### Check-in verb and room assignment (consultant + council, high stakes)
- **D-01:** `POST /cms/reservations/{reservation}/check-in` becomes the ONLY verb that sets `status = checked_in` and `checked_in_at`. Body `{ "room_uuid": uuid|null, "early_check_in": bool (default false), "reason": string|null (required when early_check_in is true, max 255) }`, permission `reservations.create`. New `App\Actions\Booking\CheckInReservationAction::handle(Reservation, ?Room, User $actor, bool $earlyCheckIn = false, ?string $reason = null)`: `DB::transaction` + `lockForUpdate()` on the reservation; status must be `confirmed` else `ReservationStateException` (`reservation_state`, 422, context `{status, allowed: ["confirmed"]}`); room resolution order explicit `room_uuid` → already-assigned `reservation_rooms.room_id` → auto-pick (shared picker, D-04) ; room type must match the first `reservation_room`; overlap check via the shared predicate (D-04); a room in `maintenance` → new `RoomOutOfOrderException` (`room_out_of_order`, 422, context `{room_uuid, housekeeping_status}`); `dirty` rooms are allowed. Writes `room_id`, `status`, `checked_in_at` (keep an existing stamp). Never writes `rooms.status` (occupancy is derived, Phase 2 D-02).
- **D-02:** Stay window uses the hotel-local business date: add `config/hotel.php` with `timezone` (env `HOTEL_TIMEZONE`, default `Asia/Damascus`) and a single predicate `Reservation::isWithinStayWindow(CarbonImmutable $hotelToday): bool` = `check_in <= today < check_out`. Outside the window → new `ReservationOutsideStayWindowException` (`reservation_outside_stay_window`, 422, context `{check_in, check_out, today}`). Override: `early_check_in: true` with a mandatory `reason` permits check-in on the day BEFORE `check_in` only (never earlier, never after `check_out`); the override is written to the activity log as `reservation.early_check_in` (properties `reason`, `check_in`, `today`). Pricing implications of early arrival are deferred. Phase 2's board keeps using the same hotel-local "today" (align `FrontDeskService` to `config('hotel.timezone')` in this phase; small change, note in summary).
- **D-03:** `AssignRoomAction` is narrowed to pure assignment: allowed when status is `confirmed` (pre-arrival assignment / move) or `checked_in` (room move during the stay); same type + overlap + maintenance checks; writes only `reservation_rooms.room_id`; never `status`/`checked_in_at`; fires `RoomAssigned` only when the reservation is `checked_in` and `room_id` actually changed. Route `POST /cms/reservations/{reservation}/assign-room` and its permission are unchanged; the response includes `status` so callers see it did not flip. This is a documented behavioural change (see D-12).
- **D-04:** One shared availability predicate and picker: extract `AssignRoomAction`'s overlap check and `CheckAvailabilityAction::findFreeRoom` into a `RoomAvailabilityService` (or methods on `CheckAvailabilityAction`) used by booking, assign-room, check-in and available-rooms: `holdingInventory` overlap on `check_in < r.check_out AND check_out > r.check_in`, other reservations only, null `room_id` rows still count for capacity; the picker excludes `maintenance`, prefers `available` over `dirty`, then lowest `number`.
- **D-05:** Notifications: check-in dispatches a new event `GuestCheckedIn(Reservation)` bound to the existing `SendRoomReadyNotification` listener so the guest's "room ready" push still fires at check-in; `RoomAssigned` keeps one meaning (room move during a checked-in stay) and also triggers the room-ready push. Events dispatch after commit.

### Check-out gate (consultant + council, high stakes)
- **D-06:** `POST /cms/reservations/{reservation}/check-out`, body `{ "force": bool (default false), "reason": string|null (required when force is true, max 255) }`, permission `reservations.create`. New `App\Actions\Booking\CheckOutReservationAction::handle(Reservation, CheckOutMode $mode, ?User $actor, ?string $reason = null)` with enum `CheckOutMode { NONE, STAFF_FORCE, GUEST_EXPRESS }`. Inside one `DB::transaction`: `lockForUpdate()` the reservation and its folio (create/refresh via `GenerateFolioAction` inside the lock if missing/open; `GenerateFolioAction` must not rebuild items for a `checked_out` reservation); status must be `checked_in` else `reservation_state` (context `{status, allowed: ["checked_in"]}`); if the folio is `open` and mode is `NONE` → new `FolioUnsettledException` (`folio_unsettled`, 422, context `{folio_uuid, total_usd, can_force}`); set `status = checked_out`, `checked_out_at = now()`; for every `reservation_rooms` row with a room not already `dirty`, call `UpdateRoomStatusAction` to `dirty` with reason `check-out` (idempotent "ensure dirty"; `maintenance → dirty` is allowed by Phase 2's table); folio status is never touched. No date guard (same-day and early departure allowed).
- **D-07:** Controller gate mapping: `force: true` without `folios.settle` → 403 `forbidden` (the existing permission error); `force` absent/false with an open folio → 422 `folio_unsettled`; `force: true` with `folios.settle` → `STAFF_FORCE`, 200, folio stays `open`, activity log entry `reservation.check_out_forced` (properties `folio_uuid`, `folio_status`, `total_usd`, `reason`, causer = actor). A settled folio ignores `force`.
- **D-08:** Guest express checkout (`ApproveFolioAction`) is refactored to: generate folio if missing → stamp `approved_by_guest_at` → delegate to `CheckOutReservationAction` with mode `GUEST_EXPRESS`, actor `null`. Guest behaviour stays unguarded (no online gateway exists) but is logged as `reservation.check_out_guest_express`, never as `check_out_forced`. `UpdateRoomStatusAction::handle` actor becomes `?User` (null = system; `room_status_history.changed_by` and `rooms.status_changed_by` are already nullable); Phase 2 tests asserting a non-null `changed_by` gain a null-actor case.
- **D-09:** Event `App\Events\ReservationCheckedOut` (`Dispatchable, SerializesModels`, `public readonly Reservation $reservation`, `public readonly CheckOutMode $mode`, `public readonly ?int $actorId`), dispatched after commit (`ShouldDispatchAfterCommit`). No listener in this phase (Phase 6 turnover task and Phase 4 key invalidation subscribe later); tests use `Event::fake([ReservationCheckedOut::class])`.
- **D-10:** Visibility of overrides: `GET /cms/reservations` gains filters `status` (existing enum) and `folio_status=open|settled` so "checked-out with open folio" is queryable; the summary notes the override log is read there and in the future night audit.

### Notes and available rooms (consultant)
- **D-11:** Notes: additive migration `reservations.notes` `text()->nullable()`. `PATCH /cms/reservations/{reservation}/notes` body `{ "notes": string|null }` (`present|nullable|string|max:2000`; `null` clears), permission `reservations.create`, `UpdateReservationNotesRequest` + `UpdateReservationNotesAction` (transactional update), 200 with the reservation resource. Allowed in any status. The shared `ReservationResource` exposes `notes` only to staff requests (`$request->user('users') !== null`); a test proves guest routes omit the key. Edit history comes from the existing `LogsActivity`; no `reservation_notes` table (Phase 4's `guest_notes` is a different, per-guest object).
- **D-12 (available rooms):** `GET /cms/reservations/{reservation}/available-rooms`, permission `reservations.view`, no params, pure read: rooms of the first `reservation_room`'s type, `is_active`, not `maintenance`, free for the reservation's dates per D-04; the currently assigned room is included with `assigned: true`. Response `data`: `{ room_type: {uuid, name}, check_in, check_out, items: [ { uuid, number, floor, housekeeping_status, assigned } ] }` sorted `assigned desc, number asc`, unpaginated, ≤ 3 queries (`expectsDatabaseQueryCount`). Multi-room reservations: first row is authoritative (as `AssignRoomAction` today).

### Permissions, history, contract
- **D-13:** No new permissions: `available-rooms` → `reservations.view`; `notes`, `check-in`, `check-out` → `reservations.create`; the money override rides on the existing `folios.settle`. Summary states "no new permissions; presets unchanged". No `reservation_status_history` table this phase: `Reservation` already has `LogsActivity` (old/new `status`, `checked_in_at`, `checked_out_at`, causer); revisit at night audit.
- **D-14 (contract & docs):** Routes inside the existing `cms/reservations` group (`GET .../available-rooms` under `reservations.view`; `PATCH .../notes`, `POST .../check-in`, `POST .../check-out` under `reservations.create`). Controller methods on `AdminReservationController` (alias of `App\Http\Controllers\Admin\ReservationController`): `availableRooms`, `updateNotes`, `checkIn`, `checkOut`; requests in `app/Http/Requests/Booking`. Lang keys in all five locales: `errors.reservation_outside_stay_window`, `errors.room_out_of_order`, `errors.folio_unsettled`, `messages.reservation_checked_in`, `messages.reservation_checked_out`, `messages.reservation_notes_updated`, plus validation mapping for any new rule names in `BaseRequest::messages()`. Check-in/out respond 200 with the reservation resource loaded with `rooms.room`, `rooms.roomType`, `guest` (+ `folio {uuid, status, total_usd}` on check-out). `docs/carlton-tree.html`: flip "check in · check out" (`ep`: `POST /cms/reservations/{r}/check-in`, `POST /cms/reservations/{r}/check-out`, `GET /cms/reservations/{r}/available-rooms`) and "reservation notes" (`PATCH /cms/reservations/{r}/notes`) to `api:true`; update the meta of "assign room · confirm · cancel" to "assign only; check-in is its own verb". `API_GUIDE_DASHBOARD.md`: document the four verbs, mark the assign-room change as behavioural/breaking in the same style as `CHANGELOG_MOBILE_API.md`, update the status reference table (`checked_in` → check-in verb); `CHANGELOG_MOBILE_API.md`: note that "room ready" fires on check-in and on room moves. Postman updated. Tests to migrate: `tests/Feature/Booking/RoomAssignmentAtBookingTest.php` (~128-162), `StayTest.php:91`, `ReservationTest.php:86`, `tests/Feature/Notification/NotificationTriggersTest.php:56,82`.

### Claude's Discretion
- Placement of the shared predicate/picker (service vs methods on `CheckAvailabilityAction`), class/file names above are defaults.
- Whether the config lives in `config/hotel.php` or `config/app.php` under a `hotel` key (default: `config/hotel.php`).
- Test files: `tests/Feature/Reservations/{ReservationNotesTest,AvailableRoomsTest,CheckInTest,CheckOutTest}.php`, `tests/Unit/Booking/{CheckInReservationActionTest,CheckOutReservationActionTest}.php`, an express-checkout regression (room turns dirty, event fires, guest marker logged), a concurrency test that settle and check-out do not interleave.
- Wording of the five-locale strings; Postman request ordering.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Conventions (hard gate)
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` + `references/developer-guide.md`; `.claude/skills/{laravel-conventions,module-slice,test-discipline,naive-reviewer}/SKILL.md`
- `.planning/codebase/CONVENTIONS.md` (Phase Summary Contract) and `.planning/phases/02-rooms-status-lifecycle-grids/02-CONTEXT.md` (two-axis status, `UpdateRoomStatusAction`, transition table, board "today")
- `.planning/phases/01-access-settings/01-04-SUMMARY.md` (summary format), `.planning/research/PITFALLS.md` (money/folio TOCTOU, status/availability conflation, contract breakage)

### Existing code this phase extends
- `backend/app/Actions/Booking/AssignRoomAction.php` (to narrow), `CreateReservationAction.php` (room written at booking), `CheckAvailabilityAction.php` (overlap predicate, `findFreeRoom`, `occupiedRoomIds`), `ConfirmReservationAction.php`, `CancelReservationAction.php`
- `backend/app/Actions/Folio/{ApproveFolioAction,GenerateFolioAction,SettleFolioAction}.php`, `backend/app/Models/Folio.php`, `backend/app/Enums/FolioStatus.php`
- `backend/app/Services/Booking/ReservationService.php`, `backend/app/Http/Controllers/Admin/ReservationController.php`, `backend/app/Http/Requests/Booking/AssignRoomRequest.php`, `backend/app/Http/Resources/Booking/ReservationResource.php`, `backend/app/Models/{Reservation,ReservationRoom,Room}.php`, `backend/app/Enums/ReservationStatus.php`
- `backend/app/Events/RoomAssigned.php`, `backend/app/Listeners/SendRoomReadyNotification.php` (listeners auto-discovered)
- `backend/app/Actions/Cms/UpdateRoomStatusAction.php` and `backend/app/Exceptions/RoomStatusTransitionException.php` (Phase 2), `backend/app/Exceptions/ReservationStateException.php` (422 exception template)
- `backend/database/seeders/RolesAndPermissionsSeeder.php` (no change; reception holds `reservations.*` and `folios.settle`)
- `backend/config/app.php` (timezone UTC), `backend/phpunit.xml` (`QUEUE_CONNECTION=sync`)

### API contract & docs
- `backend/docs/API_GUIDE_DASHBOARD.md` (Reservations module, assign-room section ~line 927, status reference table), `backend/docs/CHANGELOG_MOBILE_API.md`, `backend/docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html` (~lines 268-273)

### Planning artifacts
- `.planning/REQUIREMENTS.md` RESV-01..04, DOCS-01, XCUT-01; `.planning/ROADMAP.md` Phase 3; `.planning/research/ARCHITECTURE.md` (events for side effects; sync in-transaction checks for gates)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `AssignRoomAction` transaction + `RoomAssigned` dispatch pattern; `SettleFolioAction` lock-then-check pattern; `GenerateFolioAction` for folio creation; `ReservationStateException` shape
- `CheckAvailabilityAction::findFreeRoom/occupiedRoomIds` — basis of the shared predicate and picker
- Phase 2's `UpdateRoomStatusAction` (single writer of room status) and `FrontDeskService` "today" logic
- `ReservationResource` shared by guest and staff routes; `LogsActivity` on `Reservation` and `Room`

### Established Patterns
- Domain exceptions → envelope `error_code`; additive error codes are contracts to announce
- Events dispatched after commit; listeners auto-discovered; tests `Event::fake`
- Five-locale lang keys; `BaseRequest::messages()` mapping for new rule names
- Feature tests: happy / 401 / 403 / 422 with real bearer tokens and the per-class `staffToken()` helper

### Integration Points
- `routes/api.php` `cms/reservations` group (~line 570); `AdminReservationController`; `ReservationService`
- `ApproveFolioAction` (guest express) → shared check-out action
- `FrontDeskService` (Phase 2) → `config('hotel.timezone')`

</code_context>

<specifics>
## Specific Ideas

- The gate must be skipped only by an explicit mode, never by a missing check; every path through check-out defines `checked_out_at`, the room-dirty write, the lock and the event exactly once.
- Keep `assign-room`'s response self-describing (status included) so the dashboard's switch to `check-in` is observable.

</specifics>

<deferred>
## Deferred Ideas

- Multi-room check-in / available-rooms per `reservation_room`; no-show marking; check-in after `check_out`
- Early-check-in pricing; a "transfer to receivables / write-off" closing path for open folios on checked-out reservations (night audit will surface them)
- `reservation_status_history` table with reasons; distinct `reservations.checkin/checkout` permissions
- Guarding guest express checkout by folio balance once an online gateway exists

</deferred>

---

*Phase: 03-reservations-front-desk-verbs*
*Context gathered: 2026-09-26*
