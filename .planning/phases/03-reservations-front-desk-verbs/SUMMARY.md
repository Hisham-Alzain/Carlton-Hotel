---
phase: 03-reservations-front-desk-verbs
status: complete
completed: 2026-09-26
requirements-completed: [RESV-01, RESV-02, RESV-03, RESV-04, DOCS-01, XCUT-01]
---

# Phase 3: Reservations — Front-Desk Verbs — Summary

Four explicit desk verbs added to the existing `cms/reservations` group — `PATCH .../notes`, `GET .../available-rooms`, `POST .../check-in`, `POST .../check-out` — `assign-room` narrowed to pure assignment (**breaking**, D-03), the guest express checkout (`POST /folio/approve`) routed through the same shared check-out action, and `status`/`folio_status` list filters added to the admin index. One additive migration (`reservations.notes`), a new config `hotel.timezone` (`HOTEL_TIMEZONE`, read via `App\Support\HotelClock`), zero new permissions. Seven sequential waves (03-01 to 03-07, each sharing `routes/api.php`, the controller/service/resource/model and the five lang files, so no parallelization), closed with the full suite green.

## Endpoints delivered

| Endpoint | Guard/Permission | Notes |
|---|---|---|
| `PATCH /api/cms/reservations/{reservation}/notes` | `auth:users`, `permission:reservations.create` | Staff-only free-text notes; `notes` key must be present (`present`, `nullable`, `string`, `max:2000`); `null` clears them; works on a reservation of any status; the resource key uses `$this->when($request->user() instanceof User, ...)` so guest-facing routes never see it. |
| `GET /api/cms/reservations/{reservation}/available-rooms` | `auth:users`, `permission:reservations.view` | Read-only desk pick list for the reservation first room type: active, not in maintenance, free for its dates (shared D-04 predicate), the assigned room first and flagged. Exactly 3 queries at service level. |
| `POST /api/cms/reservations/{reservation}/check-in` | `auth:users`, `permission:reservations.create` | The only `confirmed to checked_in` transition and the only writer of `checked_in_at`. Room given, else pre-assigned, else auto-picked (`pickRoomFor`); enforces the hotel-local stay window with a day-before `early_check_in`+`reason` override; refuses maintenance (`room_out_of_order`, 422), an inactive room, a type mismatch, or an already-held room (`room_already_assigned`, 409); `no_availability` (409) when auto-pick finds nothing. Dispatches `GuestCheckedIn` after commit (room-ready push). |
| `POST /api/cms/reservations/{reservation}/check-out` | `auth:users`, `permission:reservations.create`; `force` additionally needs `folios.settle` | The single check-out path (staff plain/forced and guest express all go through `CheckOutReservationAction`). Generates/refreshes the folio under lock; an open folio without `force` is refused as `folio_unsettled` (422, thrown after commit so the folio is kept and `context.folio_uuid` is settleable); `force` overrides it and requires `reason`; dirties every assigned room via `UpdateRoomStatusAction` (system actor, no history causer for guest express); dispatches `ReservationCheckedOut` after commit. |
| `POST /api/cms/reservations/{reservation}/assign-room` (narrowed, BREAKING) | `auth:users`, `permission:reservations.create` | No longer checks a guest in. Pure assignment/move: writes only `reservation_rooms.room_id`, valid for `confirmed` or `checked_in`, refuses maintenance/inactive/type-mismatch/held rooms the same way check-in does. Fires `RoomAssigned` (room-ready push) only on a real move during `checked_in`. |
| `GET /api/cms/reservations?status=&folio_status=` | `auth:users`, `permission:reservations.view` | Additive list filters via `ReservationFilter` (D-10); empty/omitted means no filter. |
| `POST /api/folio/approve` | `auth:guests` + in-room gate | Now delegates to `CheckOutReservationAction(GUEST_EXPRESS, null)` inside `ApproveFolioAction` own transaction; a refused check-out rolls back the approval stamp; `causedByAnonymous()` marks the activity log, never the authenticated guest. |

## Waves

1. **03-01 - Reservation notes**: migration (`reservations.notes`), `UpdateReservationNotesAction`/`Request`, resource key, route.
2. **03-02 - Shared predicate/picker + HotelClock**: `isRoomFree`/`freeRoomsFor`/`pickRoomFor` added to `CheckAvailabilityAction` on one private overlap constraint (booking paths unchanged), `App\Support\HotelClock`, `config/hotel.php` (`HOTEL_TIMEZONE`), `FrontDeskService` default-date fix.
3. **03-03 - Check-in**: `CheckInReservationAction`, `CheckInReservationRequest`, `GuestCheckedIn` event, `SendRoomReadyNotification` listener widened to a union type, controller/service/route.
4. **03-04 - Assign-room narrowed**: `AssignRoomAction` stripped of the check-in side effect; 4 legacy tests rewritten in place.
5. **03-05 - Check-out**: `CheckOutReservationAction`, `CheckOutMode` enum, `CheckOutReservationRequest`, `FolioUnsettledException`, `ReservationCheckedOut` event, nullable actor on `UpdateRoomStatusAction`, `GenerateFolioAction` checked-out guard, folio summary in the response.
6. **03-06 - Guest express delegation + list filters**: `ApproveFolioAction` delegation, `ReservationFilter` (`status`, `folio_status`).
7. **03-07 - Close**: docs (`API_GUIDE_DASHBOARD.md`, `CHANGELOG_MOBILE_API.md`, `API_GUIDE_MOBILE.md`), Postman collection, `carlton-tree.html`, the phase-closing gate and this summary.

Each new QA spec uses real bearer tokens (`createToken()->plainTextToken` + `withToken()`, `staffToken()`/`presetToken()` helpers copied per test class from `RoomStatusTransitionTest`), seeds `RolesAndPermissionsSeeder` in `setUp()`, and asserts the conditional-reason validation failure as `errors.reason` under the `required` key.

## Key decisions (with sources)

| # | Decision | Source |
|---|---|---|
| 1 | Every decision D-01 to D-14 in `03-CONTEXT.md` implemented exactly as locked. | consultant + two ai-councils |
| 2 | `folio_unsettled` is thrown after the transaction commits, so the generated folio survives the refusal and `context.folio_uuid` is real (FA-05-7; approved planner deviation). | consultant |
| 3 | Lock order at check-in and assign-room is reservation, then room-type row (FA-03-3; approved planner deviation). | consultant |
| 4 | Auto-pick finding nothing free at check-in reuses 409 `no_availability` rather than a new error code (FA-03-1). | consultant |
| 5 | The guest express marker uses `causedByAnonymous()`, never the authenticated guest as causer (approved). | consultant |
| 6 | `staffToken`/`presetToken` are copied per test class from `RoomStatusTransitionTest`; no shared trait, the plan grep gate checks for the literal `private function staffToken`. | consultant |
| 7 | The notes resource check `$request->user() instanceof User` is proven equivalent to D-11 `$request->user('users') !== null` on every route serving `ReservationResource`. | consultant |
| 8 | No mobile-app compatibility shim for FA-06-1 (guest express now 422s `reservation_state` when the resolved reservation is not the checked-in stay); shipped as-is, carried to Phase 4. | consultant |
| 9 | `Rule::requiredIf(fn () => $this->boolean(flag))` in both check requests, with no new `BaseRequest::messages()` mappings, every rule name is already mapped and translated in all five locales. | consultant |
| 10 | Inactive rooms (`is_active = false`) are refused with `reservation_state` (422) at both check-in and assign-room, immediately after the type-mismatch check and before the maintenance check. | consultant |
| 11 (build) | `ApproveFolioAction` locks the reservation first, before delegating to `CheckOutReservationAction`, and stamps `approved_by_guest_at` on the folio the check-out action already built rather than calling `GenerateFolioAction` a second time, fixes a lock-order deadlock risk and a doubled folio rebuild found in QA. | build (QA) |
| 12 (build) | Both `CheckInReservationAction` and `AssignRoomAction` re-read the target room under `lockForUpdate` immediately before the maintenance/overlap checks, closing a race where a concurrent status change could land between the unlocked read and the commit. | build (QA) |
| 13 (build) | `ReservationController::checkIn`/`assignRoom` no longer resolve `Room` from the route by querying inside the controller; the uuid is passed through and `ReservationService` resolves it, keeping controllers query-free. | build (QA) |
| 14 (build) | `GenerateFolioAction` skips the item rebuild for a `checked_out` reservation unless the folio `wasRecentlyCreated` (FA-05-3). | build |
| 15 (build) | The resource `folio` key uses `whenLoaded`; only the check-out response eager-loads it. | build |
| 16 (build) | Check-out locks assigned rooms in `id` order, skips rooms already dirty, and allows `maintenance to dirty`. | build |

## Permissions (XCUT-01)

None, no new permissions this phase.

Presets unchanged (seeder byte-identical to `b928abf`, `SeederTest` green); `available-rooms` uses `reservations.view`; `notes`, `check-in` and `check-out` use `reservations.create`; the check-out override rides on the existing `folios.settle` (D-13).

## Dashboard & App Path Changes (DOCS-01)

| Client | Method | Path | Change | Notes |
|---|---|---|---|---|
| app | POST | `/folio/approve` | changed: behavioural | shared check-out, room dirty, event, response unchanged; the FA-06-1 edge now returns 422 `reservation_state` |
| dashboard | GET | `/cms/reservations` | changed: additive | `status`/`folio_status` filters |
| dashboard | GET | `/cms/reservations/{uuid}` | changed: additive | staff-only `notes` |
| dashboard | POST | `/cms/reservations/{uuid}/assign-room` | changed, BREAKING | no longer checks in (D-03, consultant + council); the React team must switch to check-in |
| dashboard | GET | `/cms/reservations/{uuid}/available-rooms` | added | mock `GET /reservations/{id}/available-rooms` |
| dashboard | POST | `/cms/reservations/{uuid}/check-in` | added | mock `POST /reservations/{id}/check-in` |
| dashboard | POST | `/cms/reservations/{uuid}/check-out` | added | mock `POST /reservations/{id}/check-out` |
| dashboard | PATCH | `/cms/reservations/{uuid}/notes` | added | mock `PATCH /reservations/{id}/notes` |

Existing paths changed in behaviour are assign-room (breaking) and guest approve; fields added are `notes` (staff) and `folio` (check-out response only); error codes added are `reservation_outside_stay_window`, `room_out_of_order` and `folio_unsettled` (422); existing codes newly reachable are `no_availability` (409) from check-in and `room_out_of_order` from assign-room; the room-ready push now fires on check-in and on moves only.

## Docs Updated (DOCS-01)

- [x] `API_GUIDE_DASHBOARD.md`: new `###` sections for available-rooms, check-in, check-out and notes; assign-room rewritten with the Behaviour-change callout; index filter table; detail notes; status reference; Folios paragraph; 3 new error rows plus the `no_availability` row widened.
- [x] `CHANGELOG_MOBILE_API.md`: breaking row (three to four), push timing paragraph, a Changed (non-breaking) row for `/folio/approve`.
- [x] `API_GUIDE_MOBILE.md`: push sentence plus `reservation_state` on `/folio/approve`; no index change.
- [x] Postman `05 - Booking (Admin)` has 17 requests: 9 new and 1 renamed (Assign room at check-in (Layla) to Assign room, pre-arrival (Layla, status stays confirmed)); folder 10 approve description updated.
- [x] `carlton-tree.html`: check in check out and reservation notes set to `api:true`, and assign room confirm cancel meta updated. Verified: 94 nodes, 73 `api:true`, `var TREE` parses as valid JS.

## Test counts

- Full suite: 1229/1229 passing, 6327 assertions (`php artisan test`, serial, `--parallel` is unavailable, `paratest` is not installed), 0 failures.
- Engineer-stage baseline was 1066/1071 with 5 legacy failures by design; all closed by the rewritten-in-place legacy tests plus the new Phase 3 specs.
- New/updated specs: `tests/Feature/Reservations/{ReservationNotesTest, AvailableRoomsTest, CheckInTest, AssignRoomTest, CheckOutTest, ExpressCheckoutTest, ReservationIndexFilterTest}.php`; `tests/Unit/Booking/{RoomAvailabilityPredicateTest, HotelClockTest, CheckInReservationActionTest, CheckOutReservationActionTest}.php`; additive methods on `RoomBoardTest`, `AvailabilityGridTest`, `UpdateRoomStatusActionTest`; rewritten-in-place `RoomAssignmentAtBookingTest`, `StayTest`, `ReservationTest`, `NotificationTriggersTest` (method counts grew vs `b928abf`, none deleted).

## Deviations from PLAN.md

- No per-plan 03-01 to 03-06 `SUMMARY.md` files (consolidated here and in `03-07-SUMMARY.md`, same as Phase 2).
- The engineer did the mechanical Task 2s of 03-01/03/05 directly rather than delegating.
- 03-07 docs and Postman were delegated to Sonnet sub-agents.
- The Postman force-checkout request uses request-level auth (the verify script requires it).
- Close-stage QA fixes (post engineer/QA pass): `ApproveFolioAction` lock order and doubled folio build, missing room lock at check-in/assign-room, and the controller-side `Room` lookup in `checkIn`/`assignRoom`, see decisions 11-13.

## Flagged carry-forwards

- FA-02-1: the room board night-based occupancy is unchanged, so a checked-in guest reads vacant with `departing_today` on the departure day until checked out.
- FA-03-2 and FA-03-3 (Phase 2 pricing carry-forwards): still open.
- FA-06-1, to Phase 4: `GuestEntitlement::currentReservation()` should resolve the checked-in stay. 8 call sites: `StayController`, `TableReservationController`, `TransportRequestController`, `FolioService::myFolio`/`approveMyFolio`, `PreArrivalService`, `ServiceBookingService`, `ServiceRequestService`.
- FA-07-1: the RESV-04 unclassified probe stays flagged.
- MySQL-only backstops: notes, check-in/assign-room room-type race, settle/check-out (row locks unverifiable on SQLite).
- FA-07-2: Postman dates are in UTC.
- Deferred: multi-room check-in, no-show handling, early-check-in pricing, receivables/write-off, `reservation_status_history`, split permissions, guarding guest express by balance.

## Production deploy notes

- [BLOCKING] Run `php artisan migrate`, one additive migration (`reservations.notes`, nullable, reversible).
- Set `HOTEL_TIMEZONE` in `.env` when the property is not on Asia/Damascus (the default), then `config:cache` if config is cached.
- Re-run `event:cache`/`optimize` if events are cached, so `GuestCheckedIn` reaches `SendRoomReadyNotification`.
- A queue worker is needed for the push (unchanged from earlier phases).
- No seeder run needed (permissions unchanged, seeder byte-identical to `b928abf`).
- Announce to the React team that assign-room no longer checks in and the dashboard must switch to the check-in/check-out verbs; announce the push timing and the approve 422 edge to Flutter.

## Files touched

Migration `2026_09_26_110000_add_notes_to_reservations_table.php`; `app/Models/Reservation.php`; `app/Http/Requests/Booking/{UpdateReservationNotesRequest, CheckInReservationRequest, CheckOutReservationRequest}.php`; `app/Actions/Booking/{UpdateReservationNotesAction, CheckAvailabilityAction, CheckInReservationAction, CheckOutReservationAction, AssignRoomAction}.php`; `app/Actions/Cms/UpdateRoomStatusAction.php`; `app/Actions/Folio/{GenerateFolioAction, ApproveFolioAction}.php`; `app/Enums/CheckOutMode.php`; `app/Events/{GuestCheckedIn, ReservationCheckedOut}.php`; `app/Exceptions/{RoomOutOfOrderException, ReservationOutsideStayWindowException, FolioUnsettledException}.php`; `app/Filters/ReservationFilter.php`; `app/Listeners/SendRoomReadyNotification.php`; `app/Services/Booking/ReservationService.php`; `app/Services/Operations/FrontDeskService.php`; `app/Http/Controllers/Admin/ReservationController.php`; `app/Http/Resources/Booking/ReservationResource.php`; `app/Support/HotelClock.php`; `config/hotel.php`; `.env.example`; `routes/api.php`; all five `lang/*/custom.php`; `docs/carlton-tree.html`; `docs/{API_GUIDE_DASHBOARD.md, CHANGELOG_MOBILE_API.md, API_GUIDE_MOBILE.md}`; `docs/postman/carlton-api.postman_collection.json`; the new/updated test files listed above.

See `03-07-SUMMARY.md` for the docs/Postman/tree delegate results and the closing gate output.
