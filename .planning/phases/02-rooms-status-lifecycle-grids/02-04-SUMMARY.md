---
phase: 02-rooms-status-lifecycle-grids
plan: 04
subsystem: rooms
tags: [laravel, rooms, housekeeping, front-desk, availability, pricing, docs, postman, rbac]

requires:
  - phase: 02-rooms-status-lifecycle-grids (02-01, 02-02, 02-03)
    provides: audited room status verb, room board, availability grid, rates grid
provides:
  - PATCH /cms/rooms/{uuid}/status (single writer UpdateRoomStatusAction, room_status_history audit trail)
  - GET /front-desk/room-board, GET /front-desk/availability-grid, GET /front-desk/rates-grid
  - rooms.status permission (new rooms group) with housekeeping and reception presets
  - Dashboard/website guides, Postman folder 18 and four Rooms status requests, three carlton-tree nodes flipped
affects: [dashboard room board / grids screens, dashboard room edit form, website room payload consumers, Phase 3 check-in/check-out verbs, per-night pricing work]

tech-stack:
  added: []
  patterns:
    - "Two-axis room model: rooms.status is housekeeping-only; occupancy is derived at read time from reservations"
    - "Bounded hand-assembled operational reads (FrontDeskService) with exact query counts asserted at service level"
    - "Deliberate clone of the quote's rule loop (PricingService::nightlyRate) instead of calling into QuoteReservationAction"

key-files:
  created:
    - backend/database/migrations/2026_09_26_100000_change_rooms_status_to_string.php
    - backend/database/migrations/2026_09_26_100100_create_room_status_history_table.php
    - backend/app/Models/RoomStatusHistory.php
    - backend/app/Exceptions/RoomStatusTransitionException.php
    - backend/app/Actions/Cms/UpdateRoomStatusAction.php
    - backend/app/Http/Requests/Cms/UpdateRoomStatusRequest.php
    - backend/app/Http/Requests/Operations/ShowRoomBoardRequest.php
    - backend/app/Http/Requests/Operations/ShowFrontDeskGridRequest.php
    - backend/app/Services/Operations/FrontDeskService.php
    - backend/app/Http/Controllers/Admin/FrontDeskController.php
    - .planning/phases/02-rooms-status-lifecycle-grids/02-04-SUMMARY.md
  modified:
    - backend/app/Enums/RoomStatus.php
    - backend/app/Models/Room.php
    - backend/app/Http/Requests/Cms/UpdateRoomRequest.php
    - backend/app/Http/Controllers/Admin/RoomController.php
    - backend/app/Services/Booking/PricingService.php
    - backend/routes/api.php
    - backend/database/seeders/RolesAndPermissionsSeeder.php
    - backend/database/seeders/BookingSeeder.php
    - backend/database/seeders/DemoShowcaseSeeder.php
    - backend/lang/{en,ar,fr,tr,es}/custom.php
    - backend/docs/API_GUIDE_DASHBOARD.md
    - backend/docs/API_GUIDE_WEBSITE.md
    - backend/docs/postman/carlton-api.postman_collection.json
    - docs/carlton-tree.html

key-decisions:
  - "reason is optional on every transition, including into maintenance (plan-q3)"
  - "PATCH status section sits at the end of the CMS Content module; Front Desk is its own module after Operations Queue (plan-q2, FA-04-1)"
  - "Edge-probe rows FA-01-1 and FA-03-1 stay unresolved, flagged; no concurrency test on SQLite (plan-q1)"
  - "QuoteReservationAction and CheckAvailabilityAction are byte-unmodified; their logic is copied, not changed (D-10, D-08)"

patterns-established:
  - "Status history table + denormalized status_changed_at/status_changed_by, written only by UpdateRoomStatusAction inside one transaction with lockForUpdate"

requirements-completed: [ROOMS-01, ROOMS-02, ROOMS-03, ROOMS-04, DOCS-01, XCUT-01]

duration: n/a
completed: 2026-09-26
status: complete
---

# Phase 2 Plan 04: Docs, contract gate and phase-closing summary

**The front desk now has a live room board, an audited housekeeping status verb, and 1-31 night availability and rate grids that agree cell-by-cell with the public availability endpoint and the one-night quote. One permission is added (`rooms.status`), and two existing contract details change for the dashboard and website: the room status value set, and PUT /cms/rooms/{uuid} no longer applying status.**

## Permissions (XCUT-01)

| Permission | Presets granted | Gates | Notes |
|---|---|---|---|
| `rooms.status` | `housekeeping`, `reception` | `PATCH /cms/rooms/{uuid}/status`; `GET /front-desk/room-board` (together with `reservations.view`, either one suffices) | New `rooms` group, independent of `cms.edit`; catalog is now 9 modules, 19 permissions |

The two grids (`GET /front-desk/availability-grid`, `GET /front-desk/rates-grid`) reuse the existing `reservations.view`; no other permission or preset changed.

## Dashboard & App Path Changes (DOCS-01)

| Client (dashboard / app / website) | Method | Path | Change (added / changed / removed) | Notes |
|---|---|---|---|---|
| dashboard | GET, POST | `/cms/rooms` | changed | Room `status` value set is now `available`, `dirty`, `maintenance` (housekeeping state only); the old in-house value is gone. Create still accepts `status` |
| dashboard | PATCH | `/cms/rooms/{uuid}/status` | added | Replaces the dashboard mock `PATCH /rooms/{id}/status`. `auth:users`, `permission:rooms.status` (not `cms.edit`). Body `status` (required enum), `reason` (optional, max 255). Allowed: available↔dirty, available→maintenance, dirty→maintenance, maintenance→dirty. Rejected transitions: 422 `room_status_transition_invalid` with `context.from/to/allowed`, no history row |
| dashboard | GET | `/cms/rooms/{uuid}` | changed | Same status value set change |
| dashboard | PUT | `/cms/rooms/{uuid}` | changed | A `status` key is ignored (200, no error, no effect); change status through the PATCH verb. Same status value set change on the response |
| dashboard | GET | `/front-desk/availability-grid` | added | Replaces the mock `GET /availability/grid`. `permission:reservations.view`. Query `from` (Y-m-d, default today, not earlier than today-365), `days` (1..31, default 14). Per room type `total`, then per night `cells[]` of `date`, `free`, `booked`, `out_of_order`; `free` never subtracts maintenance rooms |
| dashboard | GET | `/front-desk/rates-grid` | added | Replaces the mock `GET /rates/grid`. `permission:reservations.view`. Same query as the availability grid. Per room type `base_price_usd`, then per night `cells[]` of `date`, `rate_usd` (2-decimal string, equal to the one-night quote) and `rule_scope` |
| dashboard | GET | `/front-desk/room-board` | added | Same path as the mock. `permission:rooms.status|reservations.view`. Query `date`, `status`, `floor`, `room_type` (uuid). Unpaginated `items`, ordered floor (null first) then number; occupancy derived from reservations |
| website | GET | `/public/rooms` | changed | Room `status` value set is now `available`, `dirty`, `maintenance`; bookability comes from `GET /public/availability` |
| website | GET | `/public/rooms/{uuid}` | changed | Same status value set change |

Existing fields changed this phase: the room `status` value set (retired in-house value, new `dirty`) and `PUT /cms/rooms/{uuid}` no longer applying `status`, both user-confirmed in D-01 and needing a note to the React team and the website team. No existing path or error_code changed; one error_code was added, `room_status_transition_invalid` (422). No app (Flutter) route or field changed.

## Docs Updated (DOCS-01)

- [x] `backend/docs/API_GUIDE_DASHBOARD.md`: permission catalog now "9 modules, 19 permissions" with `rooms.status`; role presets note that `housekeeping` and `reception` hold `rooms.status`; Reference Data `GET /api/permissions` lists 9 modules including `rooms`, and the `GET /api/roles` example adds `rooms.status` to reception and housekeeping; the CMS **Room** field line lists `available|dirty|maintenance` and says PUT ignores status; new `### PATCH /cms/rooms/{uuid}/status — rooms.status` at the end of the CMS Content module; new `## Module: Front Desk` (after Operations Queue, before the error-code reference) with `### GET /front-desk/room-board`, `### GET /front-desk/availability-grid`, `### GET /front-desk/rates-grid`; error-code table row `room_status_transition_invalid | 422`. `### Genuinely inert` unchanged; PermissionGuideAccuracyTest passes (7 tests).
- [x] `backend/docs/API_GUIDE_WEBSITE.md`: the public Room `status` sentence now describes the housekeeping state `available`, `dirty`, `maintenance` and points at `GET /public/availability` for bookability.
- [x] `backend/docs/API_GUIDE_MOBILE.md`: unchanged (no app-facing route or field changed; zero diff).
- [x] Postman `11 - CMS Content (Admin)` → `Rooms` (now 11 requests): `Update` body is `{"is_active": true}` with no status; after it, in order: `Update status → dirty (as Housekeeping)`, `Update status → available (as Housekeeping)`, `❌ Update status, same state (expect 422 room_status_transition_invalid)`, `❌ Update status (as Kitchen — expect 403, no rooms.status)`.
- [x] Postman new top-level folder `18 - Front Desk (Admin)` (collection now 18 folders) with 7 requests: `Room board (as Housekeeping)`, `Room board — dirty rooms (as Reception)`, `Availability grid — 14 days from today (as Reception)`, `Availability grid — 31 days (as Reception)`, `Rates grid — 14 days (as Reception)`, `❌ Availability grid (as Housekeeping — expect 403, no reservations.view)`, `❌ Rates grid, days=32 (expect 422 validation_failed)`. Only environment token variables; no fixed `from` date.
- [x] `docs/carlton-tree.html`: nodes `availability grid`, `rate grid`, `room board · mark clean` are `api:true` with the D-14 `ep` arrays and meta (`dash:'mock'`, `mob:'na'` kept). Totals 94 nodes, 71 `api:true`; the other 91 nodes hash to baseline `1bd19663518bb9f9c39d8aeaee3b3d826971511d` (`tree ok`).

## Production deploy

- **[BLOCKING] Run `php artisan migrate`**: two migrations. `2026_09_26_100000_change_rooms_status_to_string` turns `rooms.status` into `string(20)` default `available` and maps legacy in-house rows to `available` (the `rooms_status_index` is kept); `2026_09_26_100100_create_room_status_history_table` adds `room_status_history` plus `rooms.status_changed_at` and `rooms.status_changed_by`. Both have reversible `down()` (dirty rows map back to `available` before the enum is restored).
- **[BLOCKING] Run `php artisan db:seed --class=RolesAndPermissionsSeeder`** (idempotent): until it runs, `rooms.status` does not exist, so the status route and the housekeeping path to the room board answer 403 for everyone except the super admin.

## Phase gate

- Route contract (`route:list --path=api --json`): `routes ok`. `PATCH api/cms/rooms/{room}/status` → `Authenticate:users`, `PermissionMiddleware:rooms.status`; `GET api/front-desk/room-board` → `Authenticate:users`, `PermissionMiddleware:rooms.status|reservations.view`; `GET api/front-desk/availability-grid` and `GET api/front-desk/rates-grid` → `Authenticate:users`, `PermissionMiddleware:reservations.view`. None carries `cms.edit` or `cms.view`.
- Seeder: `grep -c "'rooms.status'" database/seeders/RolesAndPermissionsSeeder.php` = 3 (definition + two presets).
- Scratch-database migration (scratch sqlite outside the repo, `DB_CONNECTION=sqlite DB_DATABASE=<scratch>`): `migrate:fresh --seed` OK (demo seeders run clean on the new value set), `migrate:rollback --step=2` OK, legacy value injected into 2 rooms, `migrate` OK, legacy rows remaining 0, `room_status_history` present. `backend/database/database.sqlite` sha1 identical before and after (dev database untouched).
- `QuoteReservationAction.php`, `CheckAvailabilityAction.php` and `API_GUIDE_MOBILE.md`: `git diff --quiet HEAD` exits 0.
- Docs verifications: `postman ok`, `tree ok`, PermissionGuideAccuracyTest 7/7.
- Full suite at engineer hand-off (before the QA specs of 02-01-T1, 02-02-T1, 02-03-T1, 02-03-T3 land): 966 tests, 962 passed, 4 failing — exactly the four pre-existing assertions D-01/D-06 intentionally change and 02-01-T1 re-pins: `SeederTest::test_all_18_permissions_seeded` and `::test_seeder_idempotent` (18→19), `PermissionsGroupedTest::test_permissions_grouped_by_module` (8→9), `Cms\RoomTest::test_admin_can_crud_room` (PUT status now ignored). **Final suite total (QA, after the phase specs landed): `php artisan test` — 1071 tests, 1071 passed, 5434 assertions, 0 failures.** New specs: `Feature/Rooms/{RoomStatusTransitionTest, RoomStatusSchemaTest, RoomBoardTest, AvailabilityGridTest, RatesGridTest}`, `Unit/Rooms/UpdateRoomStatusActionTest`, `Unit/Booking/PricingServiceNightlyRateTest`; re-pinned `SeederTest`, `PermissionsGroupedTest`, `Cms/RoomTest`. With ~1,070 tests the run outgrew PHP's 128M CLI default (fatal "Premature end of PHP process" late in the run), so `phpunit.xml` now sets `memory_limit` to 512M. QA re-ran the scratch-database gate independently: fresh --seed / rollback --step=2 / legacy injection / migrate OK, 0 legacy rows, `rooms_number_live_unique` still partial (`where "deleted_at" is null`), dev database sha1 unchanged.

## Flagged carry-forwards

- **FA-03-2 (MySQL only).** `QuoteReservationAction` compares `ends_on > check_in` without `whereDate`; on MySQL DATE columns the quote excludes a rule's last day, while the rates grid follows D-10's inclusive window. Grid and quote can differ on a rule's final day in production only. Recommended fix when per-night pricing lands: `whereDate('ends_on', '>=', $checkIn)` in the quote.
- **FA-03-3 (natural rule order).** Quote and grid both iterate rules with no ORDER BY; MySQL could order overlapping rules with different windows differently from SQLite. Pin `id` order in both paths with the per-night quote work.
- **FA-02-1.** Occupancy is night-based: on the check-out day a still-checked-in guest's room reads `vacant` with `departing_today: true` until Phase 3's check-out verb changes the reservation status.
- **FA-01-1 (ROOMS-02) and FA-03-1 (ROOMS-03) edge-probe rows: unresolved, flagged** (manual-review truths authored alongside). Ledger equality holds: 7 surfaced = 5 covered + 2 flagged. The verifier decides; this phase does not close them.
- **ROOMS-02 concurrency backstop.** `UpdateRoomStatusAction` locks the room row (`lockForUpdate`) inside one transaction; SQLite ignores the lock, so this is a backstop verified only on MySQL, not a test gate.
- **Postman mutation note.** The Rooms status requests mutate the seeded room (`{{room_uuid}}`); run top to bottom (dirty, then available) to leave it `available`. The 422 same-state example assumes it is `available` at that point.
- **`rooms.floor` has no index.** The room board filters on and orders by `rooms.floor` unindexed; negligible at hotel scale (board is a fixed 4 queries over a small table). Optional follow-up: composite index on `rooms (floor, number)` in a later additive migration if room count grows. Both migrations' MySQL path (native `MODIFY`, FK auto-index, explicit `status_changed_by` index dropped in `down()`) was proven on SQLite only in this run; run the migrate/rollback/migrate sequence once against a MySQL instance before production deploy.

## Deviations

- The 02-01-T3 mechanical work (five-locale keys, BookingSeeder, DemoShowcaseSeeder) was done by the engineer rather than a delegate, with the exact sentences the plan specifies.
- 02-01/02-02/02-03 have no separate per-plan SUMMARY files in this crew run; their outcomes are consolidated here.
- Both Phase 2 migrations (`2026_09_26_100000_change_rooms_status_to_string`, `2026_09_26_100100_create_room_status_history_table`) call `DB::statement` once each, guarded to the `sqlite` driver only, to restore the partial unique index `rooms_number_live_unique` (`where "deleted_at" is null`) after SQLite's `->change()`/FK table-rebuild replaces it with a plain unique index. This deviates from 02-01-PLAN's acceptance criterion `grep -c DB::statement ... returns 0`. It is required: without it, soft-deleted rooms would collide on `number` with live ones on SQLite. The MySQL path is untouched (native `MODIFY` keeps the partial index). Pinned by `RoomStatusSchemaTest::test_live_room_number_unique_stays_scoped_to_live_rows` and the scratch-DB gate (`rooms_number_live_unique` confirmed partial after `migrate:fresh --seed`).
