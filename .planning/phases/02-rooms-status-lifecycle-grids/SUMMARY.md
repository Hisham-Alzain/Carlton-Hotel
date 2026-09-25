---
phase: 02-rooms-status-lifecycle-grids
status: complete
completed: 2026-09-26
requirements-completed: [ROOMS-01, ROOMS-02, ROOMS-03, ROOMS-04, DOCS-01, XCUT-01]
---

# Phase 2: Rooms — Status Lifecycle & Grids — Summary

A two-axis room model where `rooms.status` becomes housekeeping-only (`available|dirty|maintenance`) with a full audit trail, a live front-desk room board deriving occupancy at read time from reservations, and 1-31 night availability/rates grids that agree cell-by-cell with the existing public availability endpoint and the one-night quote — built as four waves (02-01 -> 02-02 -> 02-03 -> 02-04, strictly sequential since 02-01/02-02/02-03 share `routes/api.php`), closed with the full suite green.

## Endpoints delivered

| Endpoint | Guard/Permission | Notes |
|---|---|---|
| `PATCH /api/cms/rooms/{room}/status` | `auth:users`, `permission:rooms.status` | Single writer `UpdateRoomStatusAction`; body `status` (required enum), `reason` (optional, max 255); allowed transitions available<->dirty, available->maintenance, dirty->maintenance, maintenance->dirty; rejected transition -> 422 `room_status_transition_invalid` with `context{from,to,allowed}`, no history row; writes `room_status_history` + denormalized `status_changed_at`/`status_changed_by` inside one `DB::transaction` with `lockForUpdate` |
| `GET /api/front-desk/room-board` | `auth:users`, `permission:rooms.status` or `reservations.view` | Unpaginated `data.items`, 12 D-12 keys, ordered floor asc (null first) then number; occupancy derived from reservations at read time; bounded to 4 queries |
| `GET /api/front-desk/availability-grid` | `auth:users`, `permission:reservations.view` | `from` (Y-m-d, >= today-365, default today), `days` (1..31, default 14); per room type `total` + per-night `cells[free,booked,out_of_order]`; bounded to 4 queries; agrees cell-by-cell with `GET /public/availability` |
| `GET /api/front-desk/rates-grid` | `auth:users`, `permission:reservations.view` | Same query shape; per room type `base_price_usd` + per-night `cells[rate_usd, rule_scope]`; bounded to 2 queries; `rate_usd` agrees with `QuoteReservationAction`'s one-night quote |
| `PUT /api/cms/rooms/{uuid}` | `auth:users`, `permission:cms.edit` | Changed: a `status` key is now silently ignored (200, no error); status changes only through the PATCH verb |

## Waves

1. **02-01 - Status lifecycle & audit trail**: migrations (`rooms.status` -> string, `room_status_history` table), `RoomStatus` enum, `RoomStatusHistory` model, `RoomStatusTransitionException`, `UpdateRoomStatusAction`, `UpdateRoomStatusRequest`, `RoomController` PATCH verb, route.
2. **02-02 - Front desk room board**: `ShowRoomBoardRequest`, `FrontDeskService::board()`, `FrontDeskController::roomBoard`, route.
3. **02-03 - Availability & rates grids**: `ShowFrontDeskGridRequest`, `FrontDeskService::availabilityGrid()`/`ratesGrid()`, `PricingService::nightlyRate()` (deliberate clone of the quote's rule loop, not a call into `QuoteReservationAction`), controller verbs, routes.
4. **02-04 - Docs, contract gate and phase-closing summary**: dashboard/website guides, Postman folder 18 + four Rooms status requests, three `carlton-tree.html` nodes flipped, phase summary.

Each wave's QA specs use real bearer tokens (`createToken()->plainTextToken` + `withToken()`, `staffToken()` helper copied per test class from `OperationsQueueTest`), seed `RolesAndPermissionsSeeder` in `setUp()`, and assert exact query counts at the service level (not around the HTTP call).

## Key decisions (with sources)

| # | Decision | Source |
|---|---|---|
| 1 | FA-01-1 (ROOMS-02) / FA-03-1 (ROOMS-03) edge-probe ledger rows stay unresolved, flagged; only the plans' already-specified truths/tests are implemented for them, no concurrency test on SQLite (backstop only via `lockForUpdate`). | consultant |
| 2 | PATCH status doc section placed at the end of the CMS Content module in `API_GUIDE_DASHBOARD.md`, before `## Module: Reservations`; Front Desk is its own module after Operations Queue. | consultant |
| 3 | `reason` is optional on every transition, including into `maintenance` - no `required_if`, no conditional rule, no new lang keys. | consultant |
| 4 | Room board keeps D-02's strict occupancy window verbatim; an overstay (checked_in past check_out) reads as vacant with `reservation: null` until Phase 3's check-out verb. No overstay-as-occupied widening. | consultant |
| 5 | `QuoteReservationAction` and `CheckAvailabilityAction` are byte-unmodified; their logic is copied (`PricingService::nightlyRate`), never called into or changed (D-08, D-10). | plan (D-08/D-10) |

## Test counts

- Full suite: **1071/1071 passing**, 5434 assertions (`php artisan test`), 0 failures.
- New QA specs: `Feature/Rooms/RoomStatusTransitionTest`, `RoomStatusSchemaTest`, `RoomBoardTest`, `AvailabilityGridTest`, `RatesGridTest`; `Unit/Rooms/UpdateRoomStatusActionTest`; `Unit/Booking/PricingServiceNightlyRateTest`.
- Re-pinned pre-existing tests: `SeederTest` (18->19 permissions), `PermissionsGroupedTest` (8->9 groups), `Cms/RoomTest` (PUT ignores `status`).

## Deviations from PLAN.md

- Both Phase 2 migrations use one SQLite-guarded `DB::statement` each to restore the partial unique index `rooms_number_live_unique` after SQLite's table rebuild (deviates from 02-01-PLAN's `grep -c DB::statement ... = 0` criterion); required to keep the index scoped to live rows, MySQL path untouched, pinned by `RoomStatusSchemaTest`.
- 02-01/02-02/02-03 have no separate per-plan `SUMMARY.md` files in this crew run; their outcomes are consolidated into `02-04-SUMMARY.md` and this file.
- 02-01-T3 mechanical work (five-locale keys, `BookingSeeder`, `DemoShowcaseSeeder`) done directly rather than via a delegate, using the plan's exact sentences.

## Flagged carry-forwards (not blockers)

- FA-01-1 (ROOMS-02) / FA-03-1 (ROOMS-03) edge-probe ledger rows - see decision 1. Ledger equality holds: 7 surfaced = 5 covered + 2 flagged.
- FA-02-1: occupancy is night-based; a still-checked-in guest on the check-out day reads `vacant`/`departing_today: true` until Phase 3's check-out verb.
- FA-03-2 (MySQL only): `QuoteReservationAction`'s `ends_on > check_in` comparison (no `whereDate`) can exclude a pricing rule's last day on MySQL DATE columns, while the rates grid follows D-10's inclusive window - recommended fix noted for the per-night pricing phase.
- FA-03-3: quote and grid both iterate rules with no `ORDER BY`; recommend pinning `id` order in both paths together.
- ROOMS-02 concurrency backstop: `lockForUpdate` inside one transaction, unverifiable on SQLite, MySQL-only guarantee.
- `rooms.floor` has no index (negligible at hotel scale; board is a fixed 4 queries); MySQL migration path proven on SQLite only in this run.
- Postman mutation note: the Rooms status requests mutate the seeded room; run top to bottom (dirty, then available) to leave it `available`.

## Production deploy notes

- **[BLOCKING] Run `php artisan migrate`** - two migrations: `rooms.status` -> `string(20)` default `available` (legacy in-house rows mapped to `available`); `room_status_history` table plus `rooms.status_changed_at`/`status_changed_by`. Both have reversible `down()`.
- **[BLOCKING] Run `php artisan db:seed --class=RolesAndPermissionsSeeder`** (idempotent) - until it runs, `rooms.status` does not exist and the status route / housekeeping room-board path answer 403 for everyone except the super admin.

## Files touched

See `02-04-SUMMARY.md` front-matter for the full key-files list; highlights: the two Phase 2 migrations, `RoomStatus` enum, `RoomStatusHistory` model, `RoomStatusTransitionException`, `UpdateRoomStatusAction`, `UpdateRoomStatusRequest`, `RoomController`, `FrontDeskService`, `FrontDeskController`, `ShowRoomBoardRequest`, `ShowFrontDeskGridRequest`, `PricingService::nightlyRate`, `routes/api.php`, `RolesAndPermissionsSeeder`, all five `lang/*/custom.php`, `docs/API_GUIDE_DASHBOARD.md`, `docs/API_GUIDE_WEBSITE.md`, `docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html`, the seven new/updated test files under `tests/Feature/Rooms/`, `tests/Unit/Rooms/`, `tests/Unit/Booking/`.
