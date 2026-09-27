# Phase 6: Housekeeping & Guest Services - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-26
**Phase:** 06-housekeeping-guest-services
**Mode:** fully automatic (owner instruction 2026-09-26: decisions go to the Fable 5.1 consultant, not the owner)

## How the decisions were made

1. A Fable 5.1 consultant agent inspected the codebase read-only and produced the decision set below (verbatim).
2. It flagged ten decisions for council review; two ai-council workflows ran: `wf_133d9b98-32d` housekeeping core (Architect, Skeptic, Domain Expert; confidence 78, not split) and `wf_bb83650d-27a` departure services (User Advocate, Skeptic, Architect; confidence 74, not split).
3. Council amendments folded into CONTEXT.md: canonical lock order room → task with DB::transaction retries; dedupe_key derived in the model saving hook plus a housekeeping:reconcile command; ensureOpen re-reads under lock after one unique violation; HousekeepingTaskChanged event (after commit) mirrored to Firestore by a queued listener; a single OperationsQueueType registry read by every queue site; room_number populated for service-request rows from the reservation's first room; late_checkout/luggage default items seeded with price_usd null (a new request never lands on a folio; confirming a transfer is the first time transfers are billed, documented); hotel-local day computed as a UTC window via HotelClock; transfers scheduled before the departure date excluded; meta { count, truncated } beside data.items; derived stage open|in_progress|resolved on every departure row; PATCH accepts an optional source_type hint.
4. Council open questions settled by the consultant role: both task write surfaces stay (HK-05 requires the queue verbs; recorded as a deliberate exception to the no-alias rule); the room board's dirty → available click closes the open turnover task (reason room_board, loop-safe); tasks are mirrored to Firestore because the dashboard keys on type; the SQL UNION rewrite stays deferred; late checkout and luggage carry no charge in v1 and granting a late checkout does not extend check_out (completed request + note); POST /departure-services and GET /departure-services/{uuid} are recorded as known gaps with the guest-route workaround; the stage vocabulary and the luggage_storage → luggage filter rename go in the dashboard handoff.

**Dissent kept:** Skeptic (core): keep the merged queue read-only for tasks and drop the action widening; created_by null is ambiguous with nullOnDelete. Skeptic (departures): check_out_mode duplicates folio.approved_by_guest_at; transfers should be keyed on scheduled_at; ServiceRequest needs a requested_for time (deferred as an additive column). Architect: one adapter per queue type (the registry captures most of it).

---

## Consultant decisions (verbatim)

# Phase 6 — Housekeeping & Guest Services: Consultant Decisions

Consultant: Fable (read-only inspection of `backend/` at `d9b5281`). Requirements HK-01..05, SVC-01..04 are fixed; everything below settles the gray areas so the planner can write executable plans.

## Codebase facts that shaped the decisions

- `rooms.status` is `available|dirty|maintenance` (`RoomStatus::allowedTargets()`: dirty → available|maintenance; maintenance → dirty only). There is no `inspected` state. `UpdateRoomStatusAction::handle(Room, RoomStatus, ?string $reason, ?User $actor)` is the single writer, locks the row, writes `room_status_history`, throws `RoomStatusTransitionException` (`room_status_transition_invalid`).
- `CheckOutReservationAction` already ensure-dirties every distinct assigned room (system actor, reason `check-out`) and dispatches `ReservationCheckedOut(reservation, CheckOutMode, ?actorId)` with `ShouldDispatchAfterCommit`. Existing sync listener precedent: `RevokeDigitalKeyOnCheckOut` (plain class, no `ShouldQueue`). Listeners are auto-discovered (no `Event::listen` in the provider).
- `ServiceRequestPlaced(ServiceRequest $request)` is dispatched synchronously (`event(new …)` *after* the create transaction); its only listener `MirrorServiceRequestToFirestore` is queued.
- `ServiceRequest` columns: `uuid, guest_id, reservation_id, service_item_id?, type (string = category code or legacy free string), department, status new|in_progress|completed|cancelled, priority low|normal|high, assigned_user_id?, notes`. No `room_id`; the room comes from `reservation.rooms.room`.
- `ServiceBooking`: `uuid, guest_id, reservation_id, bookable_type (morph alias `transfer` etc.), bookable_id, scheduled_at, guest_count?, status pending|confirmed|cancelled|completed, notes`. **Nothing in `app/` writes a booking status after creation** — SVC-03 will be the first writer.
- Seeded catalogue categories: `room_service` (catalog/kitchen), `housekeeping` (catalog/housekeeping), `laundry` (catalog/housekeeping), `concierge` (direct/concierge), `transport` (direct/concierge), `restaurant` (link), `maintenance` (direct/maintenance), `do_not_disturb` (toggle). **There are no `late_checkout` / `luggage` / `express_checkout` categories or request types.** Express checkout is `POST /folio/approve` → the shared check-out action in `GUEST_EXPRESS` mode; the mode is not persisted on the reservation.
- Operations queue: `OperationsQueueService` hand-merges two 500-row fetches; `resolve()` / `requiredPermission()` are `match` arms; `AssignRequestAction` / `UpdateRequestStatusAction` are plain `update()` calls typed `ServiceRequest|Ticket` and mirror to Firestore via `OperationsQueueMirror` (`documentId`, `payload`). `UpdateRequestStatusRequest` picks the enum by the `{type}` segment. `OperationsQueueItemResource` emits 8 keys with `type: service_request|ticket`. Queue index route is gated `permission:service_requests.view|tickets.view`.
- Permissions today: 21 permissions / 10 groups / 7 roles (`SeederTest`, `PermissionsGroupedTest` pin these). Presets: `housekeeping` = `service_requests.view, service_requests.update, rooms.status`; `reception` includes `service_requests.view, rooms.status`; `concierge` has `service_requests.assign/update`.
- Filters: `BaseFilter` operators `eq|like|gte|lte|in`, `$safeParms`, `$searchable`, `$sortable` (`?sort=&sort_dir=`), custom params via `applyConditions()` + `reject()` → 422 `validation_failed`. `HotelClock::today()` / `checkOutAt()` exist; `config/hotel.php` has `timezone`, `check_out_time`.
- 35 error codes exist; five locales (`ar en es fr tr`).

## Decisions

### 1. `housekeeping_tasks` schema

**D-01** — Table `housekeeping_tasks` (one additive, reversible migration):
`id`, `uuid` (unique), `room_id` FK rooms `cascadeOnDelete`, `reservation_id` FK reservations nullable `nullOnDelete`, `type` string(20) (`HousekeepingTaskType`: `turnover|stayover|inspection|request`), `status` string(20) default `pending` (`HousekeepingTaskStatus`: `pending|assigned|in_progress|done|cancelled`), `priority` string(10) default `normal` (reuse `ServiceRequestPriority`), `assigned_user_id` FK users nullable `nullOnDelete`, `source_type` string nullable + `source_id` unsignedBigInteger nullable (morph; only `ServiceRequest` in this phase, morph alias `service_request` added to the `Relation::morphMap`), `dedupe_key` string(40) nullable **unique**, `due_at` timestamp nullable, `started_at` nullable, `completed_at` nullable, `completed_by` FK users nullable `nullOnDelete`, `created_by` FK users nullable `nullOnDelete` (null = system/listener), `notes` text nullable, `timestamps`.
Indexes: `(room_id, status)`, `(status, due_at)`, `assigned_user_id`, `reservation_id`, `(source_type, source_id)` unique, `type`.
Rationale: mirrors `service_requests` + `room_status_history` conventions; morph keeps a future Ticket/manual source without a schema change; `created_by` distinguishes listener-made tasks from staff-made ones for the board.
stakes: high · convene: true

**D-02** — Dedupe is enforced at the database, portably: `dedupe_key = "{room_id}:{type}"` while a `turnover|stayover|inspection` task is open (`pending|assigned|in_progress`), set to `NULL` when it reaches `done|cancelled`, always `NULL` for `request` tasks (several guest requests for one room are legitimate; those dedupe on `(source_type, source_id)` instead). A single `CreateHousekeepingTaskAction::ensureOpen(Room, HousekeepingTaskType, array $attrs, ?User $actor)` is the only creator: inside `DB::transaction` it locks the room row (`Room::whereKey()->lockForUpdate()`), looks for an open task with the same key, returns it (`created=false`) or inserts; a `UniqueConstraintViolationException` on insert is caught once and resolved by re-reading. Partial indexes are not used (MySQL has none).
Rationale: PITFALLS #4 asks for a constraint, not a convention; the nullable-unique key works identically on SQLite and MySQL and survives retries/double dispatch.
stakes: high · convene: true

**D-03** — History: table `housekeeping_task_status_history` (`id, housekeeping_task_id` FK cascade, `from_status` nullable, `to_status`, `changed_by` FK users nullable `nullOnDelete`, `reason` string(255) nullable, `created_at` useCurrent; index `(housekeeping_task_id, created_at)`), written in the same transaction as every status change (creation writes `null → pending`). Model also uses `LogsActivity` like every other domain model. Assignment changes are not a status history row unless they flip `pending → assigned` (they do, see D-05); a bare reassignment is captured by the activity log only.
Rationale: milestone rule "status + history"; identical shape to `room_status_history` so the planner copies a proven migration and test.
stakes: medium · convene: false

**D-04** — Idempotent check-out handling: the listener (D-09) calls `ensureOpen` per distinct assigned room; a second `ReservationCheckedOut` for the same reservation, a re-dispatch, or a manual `POST` for the same room all return the existing open turnover task. An open turnover task is *never* re-created while one exists, even if the room was meanwhile set `available` by hand; a new one appears only after the current one closes. Cancelling a turnover task does not touch the room.
Rationale: exactly-one-open-task is the success criterion; the room board remains the source of truth for physical state.
stakes: medium · convene: false

### 2. Lifecycle, permissions, room coupling

**D-05** — Transition table (`HousekeepingTaskStatus::allowedTargets()`, same shape as `RoomStatus`):
`pending → assigned | in_progress | cancelled`; `assigned → in_progress | cancelled`; `in_progress → done | cancelled`; `done`, `cancelled` terminal.
`PATCH /housekeeping/tasks/{task}/status` body `{ "status": enum, "reason": string|null max 255 }`. Rejected transition → 422 `housekeeping_task_transition_invalid` with `context {from, to, allowed}` (new `HousekeepingTaskTransitionException`). `in_progress` stamps `started_at` and, if unassigned, assigns the actor (self-start); `done` stamps `completed_at` + `completed_by`. `PATCH /housekeeping/tasks/{task}/assign` body `{ "user_uuid": exists:users,uuid }`: on a `pending` task it also moves it to `assigned` (history row, reason `assigned`); on `assigned|in_progress` it swaps the assignee only; on `done|cancelled` → 422 `housekeeping_task_closed` (new exception, `context {status}`). Single writers: `AssignHousekeepingTaskAction`, `UpdateHousekeepingTaskStatusAction` (lock task row, write history, D-07 room hook), both under `DB::transaction`.
Rationale: mirrors the Phase 2 transition/exception pattern; self-start avoids forcing a dispatcher for a two-person housekeeping team.
stakes: medium · convene: false

**D-06** — Permissions: three new `{domain}.{action}` strings `housekeeping.view`, `housekeeping.assign`, `housekeeping.update` (seeder 21 → 24 permissions, 10 → 11 groups; `SeederTest`, `PermissionsGroupedTest`, `RolePresetsTest`, `PermissionGuideAccuracyTest` re-pinned). Presets: `housekeeping` += all three; `reception` += `housekeeping.view`, `housekeeping.assign`; `concierge`, `kitchen`, `events` unchanged. Route gates: `GET /housekeeping/tasks`, `GET /housekeeping/tasks/{task}` → `housekeeping.view`; `PATCH …/assign` and `POST /housekeeping/tasks` → `housekeeping.assign`; `PATCH …/status` → `housekeeping.update`. No permission is reused from `service_requests.*` for tasks.
Rationale: PITFALLS #9 (one naming shape, role-shaped grain, same triad as `service_requests.*`/`tickets.*`); reception dispatches but does not clean.
stakes: high · convene: true

**D-07** — Room coupling on `done`: only a `turnover` task touches the room. If `room.status === dirty` → `UpdateRoomStatusAction::handle($room, RoomStatus::AVAILABLE, 'turnover', $actor)` inside the task transaction (its own nested transaction is fine; both lock in the order task → room). If the room is `available` already → no write. If the room is `maintenance` → the task still completes, no room write, and the activity log entry carries `room_left_in_maintenance: true` (no error: maintenance is an engineering decision, not housekeeping's). `stayover`, `inspection`, `request` completion never writes `rooms.status`. The requirement's "inspected/available" is satisfied as `available` (see Roadmap wording fixes).
Rationale: keeps `UpdateRoomStatusAction` the single writer and respects the Phase 2 table without inventing an `inspected` state.
stakes: medium · convene: false

**D-08** — Manual creation is added: `POST /housekeeping/tasks` body `{ "room_uuid": required exists, "type": turnover|stayover|inspection (never `request`), "due_at": nullable date after_or_equal:now, "priority": low|normal|high default normal, "notes": nullable max 1000 }`, permission `housekeeping.assign`, goes through `ensureOpen`; returns 201 with the task when created, 200 with the existing open task when deduped (`meta.created: false` is not used — the resource carries `created_at`; the message key differs: `custom.messages.housekeeping_task_created` vs `…_exists`). No `DELETE`; cancel via status.
Rationale: HK-01's `type` filter and the `stayover|inspection` values are dead without a creator; one small route, dedupe already guaranteed.
stakes: low · convene: false

### 3. Events and listeners

**D-09** — `App\Listeners\CreateTurnoverTaskOnCheckOut` (synchronous, plain class like `RevokeDigitalKeyOnCheckOut`) handles `ReservationCheckedOut`: for each distinct `reservation_rooms.room_id` not null → `ensureOpen(room, TURNOVER, [reservation_id, priority, due_at, created_by: null])`. `due_at = now()->addMinutes(config('hotel.turnover_sla_minutes', 120))` (new config key + `HOTEL_TURNOVER_SLA_MINUTES` in `.env.example`). `priority = high` when another reservation with `check_in = HotelClock::today()` and status `confirmed` has a `reservation_rooms` row on that room (arrival waiting), else `normal`. Runs after commit in its own transaction; if it throws, the check-out response is unaffected only if the listener is queued — it is not, so the action must never throw on business grounds (dedupe returns, missing room skips). Tests: `Event::fake` stays valid for Phase 3 tests; new tests dispatch the real event.
Rationale: the requirement wants the task to exist when the check-out response returns; synchronous matches the Phase 4 precedent and needs no worker.
stakes: medium · convene: false

**D-10** — `App\Listeners\CreateRequestTaskOnServiceRequestPlaced` (synchronous) handles `ServiceRequestPlaced`: only when `$request->department === Department::HOUSEKEEPING` (covers the `housekeeping` and `laundry` categories and the legacy `type=housekeeping|laundry` strings). Room = first `reservation.rooms` row with a `room_id`; if none, no task is created (activity log `housekeeping_task_skipped: no_room`). Attributes: `type=request`, `source=ServiceRequest`, `reservation_id`, `priority` = request priority, `due_at = created_at + (serviceItem.expected_minutes ?? 60) min`, `notes` = request notes. Dedupe by the unique `(source_type, source_id)`.
Rationale: HK-04 second half; department (not category code) is the routing truth already used by `RouteRequestAction`.
stakes: medium · convene: false

**D-11** — Two-way status link, loop-safe: (a) a `request`-type task reaching `done` calls `UpdateRequestStatusAction->handle($serviceRequest, 'completed')` when the request is still `new|in_progress` (mirrors Firestore); task `cancelled` leaves the request alone. (b) `UpdateRequestStatusAction`'s ServiceRequest arm, when the new status is `completed|cancelled`, cancels every *open* task whose source is that request (reason `service_request_closed`, `changed_by` = actor if known). No loop: (a) closes the task before calling (b), so (b) finds nothing open.
Rationale: an orphan open task on a closed request is Pitfall 4 in another costume; the coupling lives in the two single writers only.
stakes: medium · convene: false

### 4. Operations queue integration (HK-05)

**D-12** — Third arm everywhere: `resolve('housekeeping-tasks')` → `HousekeepingTask::where('uuid')->firstOrFail()`; `requiredPermission`: assign → `housekeeping.assign`, status → `housekeeping.update`; `index()` concatenates `HousekeepingTask::with(['assignedUser','room'])->whereIn('status', HousekeepingTaskStatus::open())->latest()->limit(500)` when the caller has `housekeeping.view`; `summary()` adds `housekeeping_tasks: {status: count}` under the same permission. The queue index route gate becomes `permission:service_requests.view|tickets.view|housekeeping.view`. `UpdateRequestStatusRequest` maps `housekeeping-tasks` → `HousekeepingTaskStatus` and accepts the optional `reason`.
stakes: high · convene: true

**D-13** — Action widening without bypassing the single writers: `AssignRequestAction::handle(ServiceRequest|Ticket|HousekeepingTask $item, User $user)` and `UpdateRequestStatusAction::handle(ServiceRequest|Ticket|HousekeepingTask $item, string $status, ?User $actor = null, ?string $reason = null)`; the HousekeepingTask arm delegates to `AssignHousekeepingTaskAction` / `UpdateHousekeepingTaskStatusAction` (transition table, history, room hook, D-11 links) and then mirrors. `OperationsQueueService::assign/updateStatus` pass the actor through (additive optional params; the SR/Ticket arms ignore them). `OperationsQueueMirror::documentId` → `housekeeping_task_{uuid}`; payload gains `task_type`, `room_uuid` for that arm.
Rationale: the polymorphic actions stay the one entry point the queue and dashboard already use, while tasks keep their transition guarantees.
stakes: high · convene: true

**D-14** — Queue item contract is additive only: `type: "housekeeping_task"`, `subject` = the task type value (`turnover|stayover|inspection|request`), `department` = `housekeeping`, `status`, `priority`, `assigned_user_uuid`, `created_at`, **plus a new key on every row** `room_number` (string|null; null for service requests and tickets in this phase). `OperationsQueueTest::test_queue_priority_is_a_consistent_type_across_both_item_types` gains a task row. Scaling note goes in the guide: the merge is still a hand-paginated union of three ≤500-row fetches; a SQL `UNION` rewrite is deferred.
stakes: medium · convene: true

### 5. Staff request board (SVC-01)

**D-15** — `GET /cms/service-requests` (`auth:users`, `permission:service_requests.view`) on a new `Admin\ServiceRequestBoardController extends BaseController` calling `Operations\ServiceRequestBoardService::index()`, `paginatedSuccess` (15/100). New `App\Filters\ServiceRequestFilter`: `safeParms` `status` eq/in, `department` eq/in, `priority` eq/in, `type` eq/in, `created_at` gte/lte; custom params: `assignee` (user uuid, or literal `unassigned`), `room` (room number, `whereHas('reservation.rooms.room', number)`), `date` (Y-m-d hotel-local day of `created_at`), `guest` (guest uuid); `sortable` `created_at`, `priority`, `status` (default `created_at desc, id desc`). Unknown enum values → 422 `validation_failed` on that key. `GET /cms/service-requests/{serviceRequest}` (same permission, same resource) is included for the detail drawer. Default listing shows all statuses; the guide recommends `status[in]=new,in_progress` for the live board.
stakes: medium · convene: false

**D-16** — New `ServiceRequestBoardResource` (staff-only; the guest `ServiceRequestResource` is untouched): `uuid, type, category_code, department, status, priority, notes, created_at, updated_at, guest {uuid, name}, reservation {uuid, booking_code, check_out, room_number}, service_item {uuid, name, expected_minutes, price_usd}|null, assigned_user {uuid, name}|null, housekeeping_task {uuid, status}|null`. Eager loads `guest, reservation.rooms.room, serviceItem.category, assignedUser, housekeepingTask` → bounded to 7 queries for a page (count + 1 + 5 loads).
stakes: low · convene: false

**D-17** — No write aliases under `/cms/service-requests`. The dashboard's assign/status buttons call `PATCH /operations/queue/service-requests/{uuid}/assign|status` (existing permissions `service_requests.assign` / `.update`). The guide's board module links to those verbs and the tree node's `ep` lists both.
Rationale: PROJECT decision "no alias routes"; one test matrix, one permission surface.
stakes: low · convene: false

### 6. Departure services (SVC-02/03)

**D-18** — "Departing reservations" for date `D` (query `date` Y-m-d, default `HotelClock::today()`, bounded `today-30 .. today+30`): reservations with `status in (checked_in, checked_out)` and (`check_out = D` **or** `date(checked_out_at, hotel tz) = D`). `checked_out` is included so transfers and express checkouts remain visible after the guest leaves; `confirmed` never departs.
stakes: medium · convene: false

**D-19** — Kinds and sources (no new table):
- `transfer` → `ServiceBooking` with `bookable_type = 'transfer'` on a departing reservation (any `scheduled_at`); status = `ServiceBookingStatus`.
- `late_checkout` → `ServiceRequest` with `type = 'late_checkout'`; `luggage` → `ServiceRequest` with `type = 'luggage'`; status = `ServiceRequestStatus`.
- `express_checkout` → the reservation itself when `check_out_mode = guest_express` (see D-20); status is the derived constant `completed`; read-only.
Because `late_checkout` and `luggage` do not exist yet, `GuestServiceCatalogSeeder` gains two idempotent `DIRECT` categories: `late_checkout` (department `reception`, one default item "Late Check-out") and `luggage` (department `concierge`, one default item "Luggage Assistance"), and `Department::forServiceType()` gains `'late_checkout' => RECEPTION, 'luggage' => CONCIERGE` for the legacy free-string path. Announced in `CHANGELOG_MOBILE_API.md` (two new catalogue codes; the app renders them as quick-request chips, D-24).
stakes: high · convene: true

**D-20** — Additive column `reservations.check_out_mode` string(20) nullable, written by `CheckOutReservationAction` next to `checked_out_at` with the effective `CheckOutMode` value (`none|staff_force|guest_express`). It is the only way to know an express checkout happened without scanning the activity log. `ReservationResource` exposes it under the existing staff-only branch; guest resources do not.
Rationale: SVC-02 names express checkout; a persisted mode is one nullable column versus an activity-log join per request.
stakes: medium · convene: true

**D-21** — Projection shape, `GET /departure-services` (`auth:users`, `permission:service_requests.view`), unpaginated `data.items` (max 500, ordered `scheduled_at asc nulls last, created_at asc`), optional filters `date`, `kind` (in), `status` (in, raw values):
`{ uuid, kind, source_type ("service_booking"|"service_request"|"reservation"), status, allowed_statuses: [...], scheduled_at|null, notes|null, reservation {uuid, booking_code, check_out, checked_out_at, status}, guest {uuid, name, phone}, room_number|null, assigned_user_uuid|null, created_at }`.
`uuid` **is the source row's uuid** (no prefix; UUIDv4 collisions are impossible and the PATCH resolves by trying booking → request → reservation). `allowed_statuses` is computed from the source's transition table (`[]` for `express_checkout`). Query bound: departing reservation ids (1) + bookings (1) + requests (1) + eager loads (≤4) = ≤7 queries regardless of volume; `DepartureServiceProjection` builds rows in PHP, no query in loops.
stakes: high · convene: true

**D-22** — `PATCH /departure-services/{uuid}/status` (`auth:users`, `permission:service_requests.update`), body `{ "status": string, "reason": string|null }` validated against the union of `ServiceBookingStatus` and `ServiceRequestStatus` values; the service then delegates: request → `UpdateRequestStatusAction` (D-13 signature, so Firestore mirror and D-11 task cancellation happen); booking → new `UpdateServiceBookingStatusAction` (first writer of booking status; `lockForUpdate`; transitions `pending → confirmed|cancelled`, `confirmed → completed|cancelled`, terminal locked; rejected → 422 `service_booking_transition_invalid` with `context {from, to, allowed}`); a status value that belongs to the other enum → 422 `validation_failed` on `errors.status`; `express_checkout` rows → 422 `departure_service_readonly` (`context {kind}`); unknown uuid → 404 `not_found`. Response is the refreshed projection row.
stakes: high · convene: true

### 7. Quick requests (SVC-04) — documentation only

**D-23** — No new route or model. The mobile guide gets a `### Quick requests (chips)` subsection under the service-requests module: chips are the `ServiceItem`s of categories whose `kind = direct` in `GET /public/service-catalog` (`concierge`, `transport`, `maintenance`, and from this phase `late_checkout`, `luggage`); each `direct` category has exactly one `is_default` item, so the chip posts `POST /service-requests { "service_item_uuid": <that item>, "notes": <optional> }`; `type`/`department` are derived server-side; DND stays on its own toggle route. `POST /transport-requests` remains as the legacy alias for the transport chip. The tree node `quick requests` flips to `api:true, mob:"mock"` with `ep: ["GET /public/service-catalog", "POST /service-requests"]`.
stakes: low · convene: false

### 8. Contract and docs

**D-24** — New error codes (five locales, `custom.errors.*`, one exception class each): `housekeeping_task_transition_invalid`, `housekeeping_task_closed`, `service_booking_transition_invalid`, `departure_service_readonly`. New messages: `housekeeping_task_created`, `housekeeping_task_exists`, `housekeeping_task_assigned`, `housekeeping_task_status_updated`, `departure_service_status_updated`. Field names above are frozen once shipped.
stakes: medium · convene: false

**D-25** — Docs/tree: `API_GUIDE_DASHBOARD.md` gains `## Module: Housekeeping (housekeeping.view · housekeeping.assign · housekeeping.update)`, `## Module: Service Request Board (service_requests.view)`, `## Module: Departure Services (service_requests.view · service_requests.update)`, and the Operations Queue module is edited for the third type, the `room_number` key, the widened index gate and the `housekeeping_tasks` summary block; the permission catalogue goes to 11 modules / 24 permissions with the two updated presets. `API_GUIDE_MOBILE.md` gets D-23 and the two new catalogue codes; `CHANGELOG_MOBILE_API.md` gets an entry. Tree flips: `housekeeping` → `api:true`, `ep: ["GET /housekeeping/tasks","POST /housekeeping/tasks","PATCH /housekeeping/tasks/{uuid}/assign","PATCH /housekeeping/tasks/{uuid}/status","PATCH /operations/queue/housekeeping-tasks/{uuid}/assign|status"]`; `departures` → `api:true`, `ep: ["GET /departure-services","PATCH /departure-services/{uuid}/status"]`; `staff request board` → `api:true`, note rewritten, `ep: ["GET /cms/service-requests","GET /cms/service-requests/{uuid}","PATCH /operations/queue/service-requests/{uuid}/assign|status"]`; `quick requests` per D-23. Postman gets every new route. Summary lists the three permissions, the config key, the two seeded categories and the `check_out_mode` column as `[BLOCKING] migrate + seed` items.
stakes: low · convene: false

**D-26** — Tests (per route happy / 401 / 403 / 422, suite green): `tests/Feature/Housekeeping/{HousekeepingTaskIndexTest, HousekeepingTaskAssignTest, HousekeepingTaskStatusTest, HousekeepingTaskCreateTest, TurnoverOnCheckOutTest, RequestTaskOnServiceRequestTest}`, `tests/Feature/Operations/{OperationsQueueHousekeepingTest, ServiceRequestBoardTest, DepartureServicesTest}`, `tests/Unit/Housekeeping/{CreateHousekeepingTaskActionTest (dedupe under a double dispatch), UpdateHousekeepingTaskStatusActionTest (room hook incl. maintenance), HousekeepingTaskStatusTest}`, `tests/Unit/Service/UpdateServiceBookingStatusActionTest`; re-pin `SeederTest`, `PermissionsGroupedTest`, `RolePresetsTest`, `PermissionGuideAccuracyTest`, `OperationsQueueTest`, `CheckOutTest`/`ExpressCheckoutTest` (`check_out_mode`), `SchemaTest`-style migration test for the two new tables. Query-count assertions at service level for the board (≤7) and the departures projection (≤7).
stakes: medium · convene: false

## Roadmap wording fixes

- Success criterion 2 / HK-03: "moves the room to inspected/available" → "moves the room from `dirty` to `available` through `UpdateRoomStatusAction` (Phase 2 kept the minimal `available|dirty|maintenance` lifecycle; no `inspected` state exists; a room in `maintenance` is left untouched)".
- Success criterion 1: "A housekeeping-department service request" → "A service request routed to `Department::HOUSEKEEPING` (the `housekeeping` and `laundry` catalogue categories, or the legacy `type=housekeeping|laundry` strings)".
- Success criterion 4: add "`late_checkout` and `luggage` are new seeded `direct` catalogue categories (they did not exist); `express_checkout` rows are derived from the new `reservations.check_out_mode` column and are read-only".
- Phase 6 header: "Depends on: Phase 2, Phase 3" → add "Phase 4 (`HotelClock::checkOutAt`, sync-listener precedent)".
- Reuses: add `HotelClock`, `GuestServiceCatalogSeeder`, `Relation::morphMap`, `OperationsQueueMirror`; state "adds `housekeeping.view/.assign/.update` (21 → 24 permissions), `hotel.turnover_sla_minutes`, `reservations.check_out_mode`".
- HK-05 wording: "reusing the polymorphic assign/status actions" → "widening the polymorphic actions to a third arm that delegates to the housekeeping single writers".

## Claude's Discretion

- Exact class/file names inside the decided namespaces (`App\Actions\Housekeeping\*`, `App\Services\Housekeeping\HousekeepingTaskService`, `App\Services\Operations\{ServiceRequestBoardService, DepartureServiceService}`, `App\Support\DepartureServiceProjection`), factory shapes, resource key order.
- Whether `HousekeepingTaskResource` embeds `room {uuid, number, floor, status}` and `reservation {uuid, booking_code, check_out}` (recommended) and `history` (last 10) on `show` only.
- `HousekeepingTaskFilter` details beyond the required five (`status` eq/in, `room` by number or uuid, `assignee` uuid|`unassigned`, `type` eq/in, `due_at` gte/lte plus `due_date` Y-m-d convenience; `sortable` `due_at, created_at, priority`; default `due_at asc nulls last, created_at asc`).
- Lang wording, activity-log property names, Postman folder layout, whether `HousekeepingTaskStatus::open()` is named `open()` or `active()`.
- Whether `POST /housekeeping/tasks` sets `created_by` to the actor (yes) and whether the listener-made tasks log the reservation uuid in the activity properties (yes).

## Deferred

- An `inspected` room state and inspection tasks that gate `available` (would change the Phase 2 transition table and the room board contract).
- SQL `UNION`/materialised queue for `/operations/queue` (documented limit stays at 3 × 500 rows).
- `HousekeepingTaskCompleted` event → guest "room ready" push for arriving guests waiting on a turnover.
- Auto-cancelling an open turnover task when a room is moved to `maintenance`, and auto-creating a task when a room is set `dirty` by hand on the board.
- A daily `housekeeping:schedule-stayovers` command creating `stayover` tasks for in-house rooms.
- Posting a late-checkout fee to the folio when a `late_checkout` request is completed.
- Unassign verb and assignment history table (only status history and the activity log record reassignments).
- Guest-visible status of departure services in the mobile app (the guest already sees their own requests/bookings via existing reads).
- Firestore mirroring of the housekeeping board beyond the ops-queue document (no separate collection).
