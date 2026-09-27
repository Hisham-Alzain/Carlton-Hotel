# Phase 6: Housekeeping & Guest Services - Context

**Gathered:** 2026-09-26
**Status:** Ready for planning (build after Phase 5 has landed; touches `CheckOutReservationAction` (check_out_mode), `OperationsQueueService`, the polymorphic queue actions, `GenerateFolioAction` reads booking status)
**Decided by:** Fable 5.1 consultant (owner delegated all decisions); ten decisions were deliberated by two ai-councils (`wf_133d9b98-32d` housekeeping core, `wf_bb83650d-27a` departure services) whose amendments are folded in. Full consultant text: `06-DISCUSSION-LOG.md`.

<domain>
## Phase Boundary

Housekeeping works from a task board wired to room status; staff see and progress service requests and departure services through shared operational endpoints. Routes: `GET|POST /housekeeping/tasks`, `GET /housekeeping/tasks/{task}`, `PATCH /housekeeping/tasks/{task}/assign|status`; `/operations/queue/housekeeping-tasks/{uuid}/assign|status` (third queue type); `GET /cms/service-requests`, `GET /cms/service-requests/{serviceRequest}`; `GET /departure-services`, `PATCH /departure-services/{uuid}/status`. New permissions `housekeeping.view|assign|update`. New tables `housekeeping_tasks`, `housekeeping_task_status_history`; additive `reservations.check_out_mode`; two seeded catalogue categories. Out of scope: an `inspected` room state, SQL UNION queue, stay-extension for late checkout, fees for late checkout/luggage, staff-created departure services, guest-visible departure status.

</domain>

<decisions>
## Implementation Decisions

### housekeeping_tasks schema and dedupe (council-amended)
- **D-01:** Table `housekeeping_tasks`: `id`, `uuid` unique, `room_id` FK rooms cascadeOnDelete, `reservation_id` FK nullable nullOnDelete, `type` string(20) (`HousekeepingTaskType { TURNOVER, STAYOVER, INSPECTION, REQUEST }`), `status` string(20) default pending (`HousekeepingTaskStatus { PENDING, ASSIGNED, IN_PROGRESS, DONE, CANCELLED }`), `priority` string(10) default normal (reuse `ServiceRequestPriority`), `assigned_user_id` FK users nullable nullOnDelete, `source_type`/`source_id` nullable morph (alias `service_request` in `Relation::morphMap`), `dedupe_key` string(40) nullable UNIQUE, `due_at`, `started_at`, `completed_at`, `completed_by` FK users nullable, `created_by` FK users nullable (null = system; documented ambiguity with nullOnDelete), `notes` text, timestamps. Indexes `(room_id, status)`, `(status, due_at)`, `assigned_user_id`, `reservation_id`, unique `(source_type, source_id)`, `type`.
- **D-02:** `dedupe_key` is never assigned by action code: `HousekeepingTask::saving()` sets `"{room_id}:{type}"` when `type ∈ {turnover, stayover, inspection}` and status is open (`pending|assigned|in_progress`), else `NULL`; request tasks dedupe on the unique `(source_type, source_id)`. Single creator `CreateHousekeepingTaskAction::ensureOpen(Room, type, attrs, ?actor)`: `DB::transaction(fn, 3)`, `lockForUpdate` the room row, find-open-or-insert, catch one `UniqueConstraintViolationException` then re-read with `lockForUpdate()`, rethrow if still nothing. A `housekeeping:reconcile` artisan command nulls stale keys on closed tasks and reports dirty rooms with no open turnover task (guide: Postgres would need a savepoint here).
- **D-03:** History table `housekeeping_task_status_history` (`housekeeping_task_id` FK, `from_status` nullable, `to_status`, `changed_by` nullable, `reason` nullable, `created_at`; index `(task_id, created_at)`) written in the same transaction as every status change (creation writes `null → pending`); model also `LogsActivity`. Bare reassignment is activity-log only.
- **D-04:** Idempotency: repeated `ReservationCheckedOut`, re-dispatch or a manual POST for the same room return the existing open turnover; a new one appears only after it closes. Cancelling a task never touches the room. The room board's `dirty → available` transition (Phase 2 `UpdateRoomStatusAction`, reason other than `turnover`) closes the room's open turnover task with reason `room_board` (loop-safe: the task hook finds the room already available and writes nothing).

### Lifecycle, permissions, room coupling
- **D-05:** Transitions `pending → assigned|in_progress|cancelled`, `assigned → in_progress|cancelled`, `in_progress → done|cancelled`; `done`/`cancelled` terminal. `PATCH /housekeeping/tasks/{task}/status` body `{status, reason?}`; invalid → 422 `housekeeping_task_transition_invalid` `{from, to, allowed}`. `in_progress` stamps `started_at` and self-assigns if unassigned; `done` stamps `completed_at/completed_by`. `PATCH …/assign` body `{user_uuid}`: `pending → assigned` (history row), `assigned|in_progress` swap assignee, `done|cancelled` → 422 `housekeeping_task_closed`. Single writers `AssignHousekeepingTaskAction`, `UpdateHousekeepingTaskStatusAction`. **Lock order (council): room row first, then task row, in every task writer**; `UpdateRoomStatusAction`'s nested room lock is a re-lock in the same transaction; writers use `DB::transaction(fn, 3)`.
- **D-06:** Permissions `housekeeping.view`, `housekeeping.assign`, `housekeeping.update` (24 permissions / 11 groups; re-pin `SeederTest`, `PermissionsGroupedTest`, `RolePresetsTest`, `PermissionGuideAccuracyTest`; assert concierge and kitchen presets unchanged). Presets: housekeeping += all three; reception += `view`, `assign`. Gates: list/show → view; POST + assign → assign; status → update.
- **D-07:** Room hook on `done`, turnover only: room `dirty` → `UpdateRoomStatusAction($room, AVAILABLE, 'turnover', $actor)` inside the task transaction; `available` → no write; `maintenance` → task completes, no room write, activity property `room_left_in_maintenance: true`. Other task types never touch `rooms.status`.
- **D-08:** `POST /housekeeping/tasks` `{room_uuid, type: turnover|stayover|inspection, due_at?, priority?, notes?}`, permission `housekeeping.assign`, via `ensureOpen`; 201 when created, 200 with the existing open task when deduped (distinct message keys). No DELETE. This is also the recovery path if the sync listener ever fails.

### Events and listeners
- **D-09:** `CreateTurnoverTaskOnCheckOut` (synchronous) on `ReservationCheckedOut`: one `ensureOpen` per distinct assigned room; `due_at = now + config('hotel.turnover_sla_minutes', 120)` (new config key + `.env.example`); `priority = high` when a `confirmed` reservation with `check_in = today` holds that room, else `normal`; never throws on business grounds.
- **D-10:** `CreateRequestTaskOnServiceRequestPlaced` (synchronous): only when `department === HOUSEKEEPING`; room = first `reservation.rooms.room_id`, none → skip (logged); `type = request`, morph source, priority = request priority, `due_at = created_at + (expected_minutes ?? 60)`; dedupe via the unique `(source_type, source_id)`.
- **D-11:** Loop-safe two-way link: request-task `done` → `UpdateRequestStatusAction(request, 'completed')` if still active (task cancel leaves the request alone); `UpdateRequestStatusAction` service-request arm to `completed|cancelled` → cancels open tasks sourced from it (reason `service_request_closed`).
- **D-11b (council):** `HousekeepingTaskChanged` event (`ShouldDispatchAfterCommit`) dispatched by the three housekeeping single writers, handled by a queued `MirrorHousekeepingTaskToFirestore` listener writing `ops_queue/housekeeping_task_{uuid}` via `OperationsQueueMirror` (payload + `task_type`, `room_uuid`, `room_number`, `guest_uuid` via reservation or null). The queue actions' task arm does not mirror separately. Guide states `ops_queue` now carries a third status vocabulary.

### Ops queue (HK-05, council-amended)
- **D-12:** Third arm `housekeeping-tasks`: a single `OperationsQueueType` registry (model class, permission map, status enum, mirror id prefix, open-status list) read by `resolve()`, `requiredPermission()`, `index()`, `summary()`, `UpdateRequestStatusRequest` and `OperationsQueueMirror`; assign → `housekeeping.assign`, status → `housekeeping.update`; index concatenates open tasks (≤500) when `housekeeping.view`; `summary()` adds `housekeeping_tasks`; index route gate `service_requests.view|tickets.view|housekeeping.view`; `UpdateRequestStatusRequest` maps the segment to `HousekeepingTaskStatus` and accepts `reason`. The 3 × 500 hand-union is documented (per-type cap truncates); SQL UNION deferred.
- **D-13:** Widen `AssignRequestAction::handle(ServiceRequest|Ticket|HousekeepingTask, User)` and `UpdateRequestStatusAction::handle(…, string $status, ?User $actor = null, ?string $reason = null)`; the task arm delegates to the housekeeping single writers (actor + reason passed through). Both task write surfaces (dedicated verbs and queue verbs) are kept because HK-05 requires the queue verbs; recorded in the summary as a deliberate exception to the no-alias-routes rule.
- **D-14:** Queue item contract (additive): `type: housekeeping_task`, `subject` = task type, `department: housekeeping`, plus `room_number` (string|null) on every row (tasks from room; service requests from the reservation's first room via one eager load; tickets null) and `allowed_statuses[]` on every row for parity with departures. Guide notes: assign on a pending task changes status; new 422 codes can surface from the queue verbs.

### Staff request board (SVC-01)
- **D-15:** `GET /cms/service-requests` (`service_requests.view`), `Admin\ServiceRequestBoardController` on `BaseController` + `paginatedSuccess`; `ServiceRequestFilter`: `status/department/priority/type` eq/in, `created_at` gte/lte; custom `assignee` (uuid|`unassigned`), `room` (number via `reservation.rooms.room`), `date` (Y-m-d hotel-local), `guest`; sortable `created_at, priority, status`; default `created_at desc, id desc`. Plus `GET /cms/service-requests/{serviceRequest}`.
- **D-16:** `ServiceRequestBoardResource` (staff-only): `uuid, type, category_code, department, status, priority, notes, created_at, updated_at, guest{uuid,name}, reservation{uuid,booking_code,check_out,room_number}, service_item{…}|null, assigned_user{uuid,name}|null, housekeeping_task{uuid,status}|null`; ≤7 queries per page.
- **D-17:** No write aliases under `/cms/service-requests`; the dashboard uses `PATCH /operations/queue/service-requests/{uuid}/assign|status`.

### Departure services (SVC-02/03, council-amended)
- **D-18:** Departing for date `D` (`date` param, default `HotelClock::today()`, bound today ± 30): status in `(checked_in, checked_out)` and (`check_out = D` or `checked_out_at` within the hotel-local `[D 00:00, D+1 00:00)` window computed by `HotelClock` and queried as a UTC BETWEEN, never `date()` in SQL).
- **D-19:** Kinds: `transfer` ← `ServiceBooking` `bookable_type = transfer` for the departing set, excluding bookings whose hotel-local `scheduled_at` date is before `D` (arrival pickups); `late_checkout` / `luggage` ← `ServiceRequest` `type` of those codes; `express_checkout` ← reservations in the set with `check_out_mode = guest_express` (derived `resolved`, read-only). Seed two idempotent `DIRECT` categories in `GuestServiceCatalogSeeder`: `late_checkout` (reception) and `luggage` (concierge), one `is_default` item each with **`price_usd = null`** (an un-granted request never lands on a folio; fees deferred); add both codes to `Department::forServiceType()`; `CHANGELOG_MOBILE_API.md` entry (default chip icon fallback). Granting a late checkout does not extend `check_out` in v1 (completed request + note). Documented: confirming a transfer is the first time transfers are billed by the next folio generation.
- **D-20:** Additive `reservations.check_out_mode` string(20) nullable, written by `CheckOutReservationAction` in the same update as `checked_out_at` with the effective `CheckOutMode`; staff-only in `ReservationResource` (historical rows read null, honestly).
- **D-21:** `GET /departure-services` (`service_requests.view`), unpaginated `data.items` (≤500, `scheduled_at asc nulls last`) plus `meta { count, truncated }`, filters `date, kind[in], status[in]`. Row: `{uuid (bare source uuid), kind, source_type (service_booking|service_request|reservation), status, stage (open|in_progress|resolved: booking pending→open, confirmed→in_progress, completed|cancelled→resolved; request new→open, in_progress→in_progress, completed|cancelled→resolved; express_checkout→resolved), allowed_statuses[], scheduled_at, notes, reservation{uuid,booking_code,check_out,checked_out_at,status}, guest{uuid,name,phone}, room_number, assigned_user_uuid, created_at}`; ≤7 queries, built in PHP by `DepartureServiceProjection`.
- **D-22:** `PATCH /departure-services/{uuid}/status` (`service_requests.update`) `{status, reason?, source_type?}`: validate `status` against the union of both enums; resolve by the `source_type` hint when given, else probe booking → request → reservation; request → `UpdateRequestStatusAction`; booking → new `UpdateServiceBookingStatusAction` (`lockForUpdate`; `pending → confirmed|cancelled`, `confirmed → completed|cancelled`; terminal locked → 422 `service_booking_transition_invalid` `{from,to,allowed}`); wrong-family value → 422 `validation_failed` on `status`; `express_checkout` → 422 `departure_service_readonly`; unknown → 404. Returns the refreshed row. `POST /departure-services` and `GET /departure-services/{uuid}` are recorded as known gaps (workaround: `POST /service-requests` as the guest).

### Quick requests (SVC-04)
- **D-23:** Documentation only: chips = `is_default` items of `kind = direct` categories from `GET /public/service-catalog` (`concierge, transport, maintenance, late_checkout, luggage`); a chip posts `POST /service-requests {service_item_uuid, notes?}`; DND stays on its toggle route; `POST /transport-requests` remains legacy. Tree node "quick requests" → `api:true`, `ep: ["GET /public/service-catalog", "POST /service-requests"]`.

### Contract and docs
- **D-24:** New error codes (five locales, one exception each): `housekeeping_task_transition_invalid`, `housekeeping_task_closed`, `service_booking_transition_invalid`, `departure_service_readonly`; message keys for task created/exists/assigned/status-updated, departure status updated.
- **D-25:** Guide modules: Housekeeping, Service Request Board, Departure Services; Operations Queue module edited (third type, `room_number`, `allowed_statuses`, widened gate, summary block, third Firestore vocabulary, per-type cap); permission catalogue 11 modules / 24 permissions. Mobile guide + changelog per D-23/D-19. Dashboard handoff: rename `luggage_storage` filter to `luggage`, hide actions when `allowed_statuses` is empty, `data.items` unpaginated with `meta.truncated`, `stage` vocabulary. Tree flips: `housekeeping`, `departures`, `staff request board`, `quick requests` → `api:true` with real `ep`s. Postman updated. Summary lists `[BLOCKING] migrate + seed`, `HOTEL_TURNOVER_SLA_MINUTES`, queue worker for the Firestore mirror, the no-alias exception, and known gaps.
- **D-26:** Tests: `tests/Feature/Housekeeping/{Index,Assign,Status,Create,TurnoverOnCheckOut,RequestTaskOnServiceRequest,RoomBoardClosesTurnover}Test`, `tests/Feature/Operations/{OperationsQueueHousekeeping,ServiceRequestBoard,DepartureServices}Test`, `tests/Unit/Housekeeping/{CreateHousekeepingTaskAction (double dispatch dedupe, stale key), UpdateHousekeepingTaskStatusAction (room hook incl. maintenance, lock order assertion), HousekeepingTaskStatus}Test`, `tests/Unit/Service/UpdateServiceBookingStatusActionTest`; re-pin seeder/permission tests, `OperationsQueueTest`, `CheckOutTest`/`ExpressCheckoutTest` (check_out_mode); query-count assertions (≤7) for board and projection; timezone boundary at hotel midnight; arrival transfer excluded; `truncated` flag at 501 rows; folio unchanged for a new `late_checkout` request; Firestore mirror payload for tasks (fake).

### Claude's Discretion
- Class/file names inside `App\Actions\Housekeeping\*`, `App\Services\Housekeeping\HousekeepingTaskService`, `App\Services\Operations\{ServiceRequestBoardService, DepartureServiceService}`, `App\Support\{DepartureServiceProjection, OperationsQueueType}`; factories; resource key order.
- `HousekeepingTaskResource` embedding `room{uuid,number,floor,status}`, `reservation{uuid,booking_code,check_out}`, `history` (last 10) on show.
- `HousekeepingTaskFilter` details beyond the required five (`status` eq/in, `room` number|uuid, `assignee` uuid|`unassigned`, `type` eq/in, `due_at` gte/lte + `due_date`; sortable `due_at, created_at, priority`; default `due_at asc nulls last`).
- Whether the room-board closer lives in `UpdateRoomStatusAction` or a listener on a new `RoomStatusChanged` event (either; loop-safe, room-before-task lock order).
- Lang wording, activity-log property names, Postman layout, `open()` vs `active()` scope naming.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Conventions (hard gate)
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` + `references/developer-guide.md`; `.claude/skills/{laravel-conventions,module-slice,test-discipline,naive-reviewer}/SKILL.md`
- `.planning/codebase/CONVENTIONS.md` (Phase Summary Contract); phase summaries `.planning/phases/02-*/SUMMARY.md` (room status writer, transition table), `03-*/SUMMARY.md` + `03-CONTEXT.md` (`ReservationCheckedOut`, `CheckOutMode`, `HotelClock`), `04-*/SUMMARY.md` (sync listener precedent, `HotelClock::checkOutAt`), `05-*/SUMMARY.md` (folio billing of bookings/requests)
- `.planning/research/PITFALLS.md` (housekeeping duplication, ops-queue scaling, permission sprawl), `.planning/research/ARCHITECTURE.md` (table vs projection)

### Existing code this phase extends
- `backend/app/Models/{ServiceRequest,ServiceBooking,Transfer,Room,Reservation,Ticket,ServiceItem,ServiceCategory}.php` + migrations; `backend/app/Enums/{ServiceRequestStatus,ServiceRequestPriority,Department,BookableType,ServiceBookingStatus,ServiceCategoryKind,RoomStatus}.php`
- `backend/app/Actions/Operations/{AssignRequestAction,UpdateRequestStatusAction,RouteRequestAction}.php`, `backend/app/Services/Operations/OperationsQueueService.php`, `backend/app/Http/Controllers/Admin/OperationsQueueController.php`, `backend/app/Http/Requests/Operations/UpdateRequestStatusRequest.php`, `backend/app/Support/OperationsQueueMirror.php` (or equivalent), `backend/app/Listeners/MirrorServiceRequestToFirestore.php`
- `backend/app/Actions/Cms/UpdateRoomStatusAction.php`, `backend/app/Actions/Booking/CheckOutReservationAction.php`, `backend/app/Events/{ReservationCheckedOut,ServiceRequestPlaced}.php`, `backend/app/Listeners/RevokeDigitalKeyOnCheckOut.php`, `backend/app/Support/HotelClock.php`, `backend/config/hotel.php`
- `backend/app/Services/Service/{ServiceRequestService,ServiceBookingService,TransferService}.php`, `backend/app/Actions/Service/{PlaceServiceRequestAction,RequestTransportAction}.php`, `backend/database/seeders/{RolesAndPermissionsSeeder,GuestServiceCatalogSeeder}.php`, `backend/app/Filters/{RoomFilter,ReservationFilter,GuestFilter}.php`, `backend/app/Actions/Folio/GenerateFolioAction.php` (reads booking/request status; read-only here)
- `backend/routes/api.php` (`operations/queue/{type}/{uuid}`, `/dashboard/summary`, guest `service-requests`, `cms` catalogue)

### API contract & docs
- `backend/docs/API_GUIDE_DASHBOARD.md` (Operations queue module; new modules), `backend/docs/API_GUIDE_MOBILE.md` (Service requests / catalogue), `backend/docs/CHANGELOG_MOBILE_API.md`, `backend/docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html`

### Planning artifacts
- `.planning/REQUIREMENTS.md` HK-01..05, SVC-01..04, DOCS-01, XCUT-01; `.planning/ROADMAP.md` Phase 6 (criteria reworded per the consultant: `dirty → available`, `Department::HOUSEKEEPING` routing, seeded `late_checkout`/`luggage`, `check_out_mode`, widened polymorphic actions, Phase 4 dependency)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `UpdateRoomStatusAction` (single writer, history), `CheckOutReservationAction` + `ReservationCheckedOut`, `RevokeDigitalKeyOnCheckOut` (sync listener shape), `HotelClock`
- `OperationsQueueService` merged index, `AssignRequestAction`/`UpdateRequestStatusAction` + Firestore mirror, `UpdateRequestStatusRequest` enum picker
- `BaseFilter` (`applyConditions()`, `reject()`), `RoomFilter`/`ReservationFilter`/`GuestFilter` precedents, `BaseController::paginatedSuccess`
- `GuestServiceCatalogSeeder` (idempotent categories/items), `Department::forServiceType()`

### Established Patterns
- Status + history tables written in one transaction; row locks in a fixed order; domain exceptions with `error_code` + context; five-locale keys; real-bearer-token tests; `expectsDatabaseQueryCount` bounds on service paths; events after commit; listeners auto-discovered
- Derived projections over source tables (no duplicated state); bare source uuids with `source_type`

### Integration Points
- `routes/api.php`: new `housekeeping` group, `cms/service-requests` reads, `departure-services`, widened ops-queue gate
- `ReservationCheckedOut` and `ServiceRequestPlaced` listeners; `UpdateRoomStatusAction` room-board closer; `UpdateRequestStatusAction` SR arm cancelling tasks
- Seeder presets `housekeeping`, `reception`; `GuestServiceCatalogSeeder`

</code_context>

<specifics>
## Specific Ideas

- Every task writer locks room → task and retries on deadlock, so a turnover completion can never 500 against a concurrent check-out.
- Departure services and the ops queue both expose `allowed_statuses` (and departures a `stage`) so the dashboard never learns two state machines.

</specifics>

<deferred>
## Deferred Ideas

- `inspected` room state; inspection-gated availability; auto-cancel turnover on `maintenance`; auto-create on manual `dirty`; daily `stayover` scheduling command
- SQL `UNION` / materialised operations queue; one adapter per queue type
- `HousekeepingTaskCompleted` → room-ready push for waiting arrivals; guest-visible departure status; separate Firestore housekeeping collection
- Late-checkout fee posting; stay-extension action; `ServiceRequest.requested_for` time; staff-created departure services (`POST /departure-services`, `GET /departure-services/{uuid}`)
- Unassign verb / assignment history table; explicit `origin` column instead of `created_by null = system`

</deferred>

---

*Phase: 06-housekeeping-guest-services*
*Context gathered: 2026-09-26*
