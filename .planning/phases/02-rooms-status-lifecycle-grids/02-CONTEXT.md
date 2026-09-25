# Phase 2: Rooms — Status Lifecycle & Grids - Context

**Gathered:** 2026-09-25
**Status:** Ready for planning

<domain>
## Phase Boundary

The front desk sees every room's live housekeeping and occupancy state on one board, moves rooms through a small audited housekeeping lifecycle, and reads 14-day availability and rate grids per room type. Four routes (public paths, no `/api` prefix in docs): `GET /front-desk/room-board`, `PATCH /cms/rooms/{room}/status`, `GET /front-desk/availability-grid`, `GET /front-desk/rates-grid`. One new permission `rooms.status`, one new table `room_status_history`, one column-type migration on `rooms.status`. Requirements ROOMS-01..04 plus the DOCS-01 / XCUT-01 gates. Housekeeping *tasks* (assignment, due times) are Phase 6; check-in/check-out verbs that will set rooms occupied/dirty automatically are Phase 3; rate editing is out of scope (grid is read-only).

</domain>

<decisions>
## Implementation Decisions

### Status model & occupancy (user-confirmed)
- **D-01:** Two-axis model. `rooms.status` becomes the *housekeeping* status only, with values `available | dirty | maintenance` (`RoomStatus` enum: keep `AVAILABLE` and `MAINTENANCE`, add `DIRTY`, remove `OCCUPIED`). Migration changes the column from a DB enum to `string(20)` (default `available`, keep the index) and maps existing `occupied` rows to `available` in the same migration. `CreateRoomRequest` keeps `Rule::enum(RoomStatus::class)` for the initial status; `UpdateRoomRequest` **drops** `status` so every later change goes through the transition endpoint (single writer, complete history).
- **D-02:** Occupancy is derived, never stored. For a business date `D` (default: today in the app timezone, `config('app.timezone')` = UTC, as `now()->toDateString()`; optional `?date=` on the board): a room is `occupied` when a `reservation_rooms` row links it to a reservation with status `checked_in` and `check_in <= D < check_out`; `arriving_today` when a non-cancelled, non-checked-out reservation with an assigned room has `check_in = D`; `departing_today` when a `checked_in` reservation has `check_out = D`; `stayover` when `checked_in` and `check_in < D < check_out`. Nothing in Phase 2 sets or reads an "occupied" status.
- **D-03:** New table `room_status_history` (`id`, `room_id` FK cascadeOnDelete, `from_status` string(20) nullable, `to_status` string(20), `changed_by` FK users nullOnDelete, `reason` string(255) nullable, `created_at`; index on `room_id`, `created_at`). Additionally `rooms` gains denormalised `status_changed_at` (timestamp nullable) and `status_changed_by` (FK users nullOnDelete) so the board reads them without a join; the sync owner is `UpdateRoomStatusAction`, which writes the history row and the two columns in one `DB::transaction` (documented in the migration and the model).

### Transitions & permissions (user-confirmed)
- **D-04:** Minimal lifecycle. Allowed transitions: `available → dirty`, `dirty → available`, `available → maintenance`, `dirty → maintenance`, `maintenance → dirty`. Leaving maintenance always lands on `dirty` so the room is cleaned before it is sold (`maintenance → available` is rejected). No `cleaning` / `inspected` states in this phase (REQUIREMENTS ROOMS-02 and ROADMAP criterion 2 were narrowed to match).
- **D-05:** A disallowed transition, including same-state (`dirty → dirty`), returns 422 with a new domain exception `RoomStatusTransitionException` → `error_code: room_status_transition_invalid`, context `{from, to, allowed: [...]}`; lang key in all five locales. No history row is written for rejected requests.
- **D-06:** `PATCH /cms/rooms/{room}/status` body `{ "status": "dirty|available|maintenance", "reason": "optional, max 255" }`, middleware `auth:users` + `permission:rooms.status`. `rooms.status` is added to `RolesAndPermissionsSeeder` under a new `rooms` group and to the `housekeeping` and `reception` presets; entering/leaving maintenance uses the same permission (no `cms.edit` required). Production note for the summary: re-run `php artisan db:seed --class=RolesAndPermissionsSeeder` (idempotent) after deploy.
- **D-07:** Any `rooms.status` holder may mark a room `dirty` manually from `available` (spills, stayover refresh). Phase 3's check-out verb will set `dirty` automatically through the same action.

### Grid semantics (user-confirmed)
- **D-08:** `GET /front-desk/availability-grid?from=YYYY-MM-DD&days=N`: `from` defaults to today, `days` defaults to 14, allowed 1..31; anything else (bad date, `days` out of range, `from` before today minus 365 days) → 422 `validation_failed`. Response `data`: `{ from, days, room_types: [ { uuid, name (localized map as elsewhere), total, cells: [ { date, free, booked, out_of_order } ] } ] }` where `booked` uses exactly the overlap semantics of `CheckAvailabilityAction::occupiedCount` (non-cancelled reservations incl. unexpired holds, rows with null `room_id` still count), `free = total − booked` (identical to what the booking availability check returns, so the grid never disagrees with `/availability`), and `out_of_order` = rooms of that type currently in `maintenance` (a today snapshot repeated on every cell, informational only, never subtracted from `free`).
- **D-09:** Query bound for the availability grid: ≤ 5 queries regardless of room-type count or `days` (room types + totals in one; overlapping reservation rows for the whole window in one, expanded per night in PHP; maintenance counts in one). Feature test asserts it with `expectsDatabaseQueryCount`.
- **D-10:** `GET /front-desk/rates-grid?from&days` (same params/validation) returns `{ from, days, room_types: [ { uuid, name, base_price_usd, cells: [ { date, rate_usd, rule_scope } ] } ] }`. `rate_usd` is the effective nightly rate: `base_price_usd` with every active `PricingRule` whose `starts_on <= date <= ends_on` applied in the same order as `QuoteReservationAction` (percentage modifiers first, then fixed); `rule_scope` is the applied rule's scope (`seasonal|weekend|holiday`) or `null`. Extract this into `PricingService::nightlyRate(RoomType $type, CarbonInterface $date, Collection $rules): array` and load all rules for the window in one query. `QuoteReservationAction` is **not** changed in this phase (its whole-stay rule application stays as is; making the quote per-night is deferred). Money stays DECIMAL USD, rounded to 2 places.
- **D-11:** Permissions for reads: board requires `permission:rooms.status|reservations.view` (housekeeping holds the former, reception the latter); both grids require `permission:reservations.view`. Routes live in a new `Route::middleware('auth:users')->prefix('front-desk')` group in `routes/api.php`, controller `App\Http\Controllers\Admin\FrontDeskController` (board, availabilityGrid, ratesGrid), service `App\Services\Operations\FrontDeskService`; the status verb lives in `AdminRoomController::updateStatus` backed by `App\Actions\Cms\UpdateRoomStatusAction` and `App\Http\Requests\Cms\UpdateRoomStatusRequest`.

### Room board shape (user-confirmed)
- **D-12:** `GET /front-desk/room-board?date&status&floor&room_type` returns an **unpaginated** `data.items` array (plus `data.date`) sorted by `floor` then `number`, filters optional (`status` ∈ enum, `floor` int, `room_type` uuid). Each row: `{ uuid, number, floor, room_type: { uuid, name }, housekeeping_status, status_changed_at, status_changed_by: { uuid, name } | null, occupancy: "occupied" | "vacant", arriving_today, departing_today, stayover, reservation: { uuid, guest_name, check_in, check_out, status } | null }` where `reservation` is the checked-in stay, else today's arrival, else null. Only active rooms (`is_active = true`).
- **D-13:** Board query bound: ≤ 4 queries regardless of room count (rooms with room type; today's reservation rows with reservation + guest in one eager load keyed by room; status changers). Test asserts with `expectsDatabaseQueryCount`.

### Docs & contract (DOCS-01 / XCUT-01)
- **D-14:** Flip `docs/carlton-tree.html` nodes "availability grid" (`ep: ["GET /front-desk/availability-grid"]`), "rate grid" (`ep: ["GET /front-desk/rates-grid"]`) and "room board · mark clean" (`ep: ["GET /front-desk/room-board", "PATCH /cms/rooms/{room}/status"]`) to `api:true` with updated `meta`. Update `API_GUIDE_DASHBOARD.md` (new "Module: Front desk" section + the status verb under Rooms, and a note that `PUT /cms/rooms/{room}` no longer accepts `status`), Postman, and the phase summary per the Phase 1 contract: permission `rooms.status` (presets housekeeping, reception), path changes for the dashboard (`PATCH /rooms/{id}/status` → `PATCH /cms/rooms/{uuid}/status`; `GET /availability/grid` → `GET /front-desk/availability-grid`; `GET /rates/grid` → `GET /front-desk/rates-grid`; `GET /front-desk/room-board` unchanged), and the seeder re-run note.

### Claude's Discretion
- Exact resource class names (`RoomBoardRowResource`, grid resources vs plain arrays built in the service) as long as no queries run inside resources.
- Whether `reason` is required when entering `maintenance` (default: optional everywhere).
- How `status_changed_by` is exposed for system-initiated changes in later phases (null is acceptable now).
- Whether the availability grid also returns `booked` per-room lists (default: counts only).
- Test file names (`tests/Feature/Rooms/RoomStatusTransitionTest.php`, `RoomBoardTest.php`, `AvailabilityGridTest.php`, `RatesGridTest.php`) and a unit test for `PricingService::nightlyRate` and for the transition table.
- The `from` lower bound (default: reject dates more than 365 days in the past; past dates within that window are allowed for audit views).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Conventions (hard gate)
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` and `references/developer-guide.md` — layered architecture, envelope, domain exceptions, §15 database rules (status + history, FK ON DELETE, indexes, DECIMAL money), §17 checklist
- `.claude/skills/laravel-conventions/SKILL.md`, `module-slice`, `test-discipline`, `naive-reviewer`
- `.planning/codebase/CONVENTIONS.md` — includes the Phase Summary Contract section written in Phase 1
- `.planning/phases/01-access-settings/01-CONTEXT.md` and `01-04-SUMMARY.md` — five-locale rule, real-bearer-token tests, summary format to repeat

### Existing code this phase extends
- `backend/app/Models/Room.php`, `backend/app/Enums/RoomStatus.php`, `backend/database/migrations/2026_07_09_100002_create_rooms_table.php` — current enum column
- `backend/app/Actions/Booking/CheckAvailabilityAction.php` — `availableCount`, `occupiedCount`, `findFreeRoom` (overlap semantics the grid must reuse; comment states status is independent of availability)
- `backend/app/Actions/Booking/QuoteReservationAction.php`, `backend/app/Services/Booking/PricingService.php`, `backend/app/Models/PricingRule.php`, `backend/app/Enums/{PricingScope,ModifierType}.php` — rule application order for `nightlyRate`
- `backend/app/Models/{Reservation,ReservationRoom,Guest}.php`, `backend/app/Enums/ReservationStatus.php` — occupancy derivation
- `backend/app/Http/Controllers/Admin/AdminRoomController.php`, `backend/app/Services/Cms/RoomService.php`, `backend/app/Filters/RoomFilter.php`, `backend/app/Http/Requests/Cms/{CreateRoomRequest,UpdateRoomRequest}.php`
- `backend/database/seeders/RolesAndPermissionsSeeder.php` — permission groups and presets
- `backend/app/Services/Operations/OperationsQueueService.php` — precedent for a bounded, hand-assembled operational read
- `backend/app/Exceptions/ReservationStateException.php` — template for the new 422 domain exception

### API contract & docs
- `backend/docs/API_GUIDE_DASHBOARD.md` — envelope, error codes, Rooms module, format for the new Front desk module
- `backend/docs/postman/carlton-api.postman_collection.json`
- `docs/carlton-tree.html` — nodes to flip (D-14)

### Planning artifacts
- `.planning/REQUIREMENTS.md` ROOMS-01..04, DOCS-01, XCUT-01; `.planning/ROADMAP.md` Phase 2
- `.planning/research/FEATURES.md` (two-axis room status), `PITFALLS.md` (status/availability conflation, N+1 in grids, permission sprawl), `ARCHITECTURE.md` (table vs projection)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `CheckAvailabilityAction::occupiedCount / availableCount` — single source of truth for "booked" per type and window; grid must call or replicate its predicate exactly
- `QuoteReservationAction` rule loop (percentage then fixed) — lift into `PricingService::nightlyRate`
- `OperationsQueueService` — pattern for assembling a merged operational payload with bounded queries
- `BaseFilter` / `RoomFilter` — optional filters for the board (`status`, `floor`, `room_type`)
- `ReservationStateException` — 422 domain exception shape; `lang/*/custom.php` `errors` group for the new key
- `RolesAndPermissionsSeeder` — add `rooms.status` + presets; tests already seed it via `setUp`
- `tests/TestCase.php` `staffToken('rooms.status')` helper for permission-scoped tokens

### Established Patterns
- Migrations are additive; enum→string change must keep data and index (write it with `Schema::table` + raw `ALTER` fallback that works on SQLite in tests and MySQL in prod, or a create-copy-swap for SQLite)
- Status + history table, transaction around multi-step writes, domain exception → envelope with `error_code`
- Localized names as `{en, ar, ...}` maps via `localized()`; money DECIMAL USD
- Feature tests per route: happy / 401 / 403 (wrong permission) / 422, with `expectsDatabaseQueryCount` for the bounded reads

### Integration Points
- `routes/api.php`: new `front-desk` group; `PATCH /cms/rooms/{room}/status` inside the existing `cms` group next to the rooms routes
- `AdminRoomController` / `RoomService` (status verb), new `FrontDeskController` / `FrontDeskService`
- `RoomStatus` enum consumers: `CreateRoomRequest`, `UpdateRoomRequest`, Postman env refresh command (`RefreshPostmanEnvironment` filters by `RoomStatus::AVAILABLE`, still valid)

</code_context>

<specifics>
## Specific Ideas

- The grid's `free` number must equal what `/availability` says for the same type and night; a test should assert the two agree.
- Keep the phase a clean precedent for "status + history + transition table" that Phase 6 (housekeeping tasks) and Phase 7 (tickets) copy.

</specifics>

<deferred>
## Deferred Ideas

- `cleaning` and `inspected` housekeeping states (chosen minimal lifecycle; revisit when housekeeping tasks land in Phase 6)
- Per-night pricing inside `QuoteReservationAction` (quote currently applies rules to the whole stay); the grid's `nightlyRate` is the seed for that change
- Rate editing from the grid (CMS pricing rules remain the editing surface)
- Room notes on the board (Phase 3 adds reservation notes)

</deferred>

---

*Phase: 02-rooms-status-lifecycle-grids*
*Context gathered: 2026-09-25*
