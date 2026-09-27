# Phase 6: Housekeeping & Guest Services - Pattern Map

**Mapped:** 2026-09-27
**Files analyzed:** ~45 new/modified files
**Analogs found:** 40 / 45 (5 registry/projection files are genuinely new shapes per RESEARCH.md — no direct precedent)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/*_create_housekeeping_tasks_table.php` | migration | CRUD | `database/migrations/*_create_room_status_history_table.php` (+ rooms table) | role-match |
| `database/migrations/*_create_housekeeping_task_status_history_table.php` | migration | event-driven | `database/migrations/*_create_room_status_history_table.php` | exact |
| `database/migrations/*_add_check_out_mode_to_reservations_table.php` | migration | CRUD | any additive-column migration on `reservations` | role-match |
| `app/Enums/HousekeepingTaskType.php` | model (enum) | transform | `app/Enums/CheckOutMode.php` (simple string enum) | exact |
| `app/Enums/HousekeepingTaskStatus.php` | model (enum) | transform | `app/Enums/RoomStatus.php` | exact |
| `app/Models/HousekeepingTask.php` | model | CRUD | `app/Models/Room.php` (status + `LogsActivity`) + `saving()` hook is new | role-match |
| `app/Models/HousekeepingTaskStatusHistory.php` | model | event-driven | `app/Models/RoomStatusHistory.php` | exact |
| `app/Actions/Housekeeping/CreateHousekeepingTaskAction.php` (`ensureOpen`) | service (action) | event-driven | `app/Actions/Notification/RegisterDeviceTokenAction.php` (unique-violation-inside-transaction idiom) | role-match |
| `app/Actions/Housekeeping/AssignHousekeepingTaskAction.php` | service (action) | request-response | `app/Actions/Cms/UpdateRoomStatusAction.php` (lock→write→history shape, simplified) | role-match |
| `app/Actions/Housekeeping/UpdateHousekeepingTaskStatusAction.php` | service (action) | request-response | `app/Actions/Cms/UpdateRoomStatusAction.php` | exact |
| `app/Actions/Service/UpdateServiceBookingStatusAction.php` | service (action) | request-response | `app/Actions/Cms/UpdateRoomStatusAction.php` | exact |
| `app/Actions/Operations/AssignRequestAction.php` (widen) | service (action) | request-response | itself (current version) — dispatcher pattern from `CheckOutReservationAction`'s delegation to `UpdateRoomStatusAction` | role-match |
| `app/Actions/Operations/UpdateRequestStatusAction.php` (widen) | service (action) | request-response | itself (current version) | role-match |
| `app/Actions/Cms/UpdateRoomStatusAction.php` (room-board closer) | service (action) | event-driven | itself; loop-safety like `RevokeDigitalKeyOnCheckOut` idempotency check | role-match |
| `app/Actions/Booking/CheckOutReservationAction.php` (check_out_mode write) | service (action) | CRUD | itself (current version, `dirtyAssignedRooms` shows same-transaction multi-write pattern) | exact |
| `app/Events/HousekeepingTaskChanged.php` | event | event-driven | `app/Events/ReservationCheckedOut.php` (`ShouldDispatchAfterCommit`) | exact |
| `app/Listeners/CreateTurnoverTaskOnCheckOut.php` | event-driven listener | event-driven | `app/Listeners/RevokeDigitalKeyOnCheckOut.php` | exact |
| `app/Listeners/CreateRequestTaskOnServiceRequestPlaced.php` | event-driven listener | event-driven | `app/Listeners/MirrorServiceRequestToFirestore.php` (sync `ServiceRequestPlaced` handler shape) | role-match |
| `app/Listeners/MirrorHousekeepingTaskToFirestore.php` | event-driven listener | event-driven | `app/Listeners/MirrorServiceRequestToFirestore.php` | exact |
| `app/Support/OperationsQueueType.php` | config/registry | transform | none — new pattern (RESEARCH.md Pattern 4); shape verified against 5 touch points | no analog |
| `app/Support/OperationsQueueMirror.php` (widen) | utility | transform | itself (current version) | exact |
| `app/Support/DepartureServiceProjection.php` | utility | transform | none — new pattern; discipline copied from room-board (≤7 set-based queries, Pitfall 8) | no analog |
| `app/Services/Housekeeping/HousekeepingTaskService.php` | service | CRUD | `app/Services/Operations/OperationsQueueService.php` (index/assign/status/assertCan shape) | role-match |
| `app/Services/Operations/ServiceRequestBoardService.php` | service | CRUD (read) | `app/Services/Operations/OperationsQueueService.php::index()` | role-match |
| `app/Services/Operations/DepartureServiceService.php` | service | CRUD (read) | `app/Services/Operations/OperationsQueueService.php` (assertCan + delegate-to-action shape) | role-match |
| `app/Services/Operations/OperationsQueueService.php` (widen `resolve`/`requiredPermission`) | service | CRUD | itself (current version) | exact |
| `app/Filters/HousekeepingTaskFilter.php` | utility (filter) | transform | `app/Filters/ReservationFilter.php` | exact |
| `app/Filters/ServiceRequestFilter.php` | utility (filter) | transform | `app/Filters/ReservationFilter.php` (custom non-column filters: `assignee`, `room`, `date`, `guest`) | exact |
| `app/Http/Controllers/Admin/HousekeepingTaskController.php` | controller | request-response | `app/Http/Controllers/Admin/OperationsQueueController.php` | role-match |
| `app/Http/Controllers/Admin/ServiceRequestBoardController.php` | controller | request-response | `app/Http/Controllers/Admin/OperationsQueueController.php::index()` (read-only slice) | role-match |
| `app/Http/Controllers/Admin/DepartureServiceController.php` | controller | request-response | `app/Http/Controllers/Admin/OperationsQueueController.php` | role-match |
| `app/Http/Controllers/Admin/OperationsQueueController.php` (no change expected; service absorbs registry) | controller | request-response | itself | exact |
| `app/Http/Requests/Housekeeping/CreateHousekeepingTaskRequest.php` | middleware (validation) | request-response | `app/Http/Requests/Operations/UpdateRequestStatusRequest.php` (BaseRequest + Rule::enum) | role-match |
| `app/Http/Requests/Housekeeping/AssignHousekeepingTaskRequest.php` | middleware (validation) | request-response | `app/Http/Requests/Operations/AssignRequestRequest.php` | exact |
| `app/Http/Requests/Housekeeping/UpdateHousekeepingTaskStatusRequest.php` | middleware (validation) | request-response | `app/Http/Requests/Operations/UpdateRequestStatusRequest.php` | exact |
| `app/Http/Requests/Operations/UpdateRequestStatusRequest.php` (widen 3-way enum) | middleware (validation) | request-response | itself (current version) | exact |
| `app/Http/Requests/Operations/UpdateDepartureServiceStatusRequest.php` | middleware (validation) | request-response | `app/Http/Requests/Operations/UpdateRequestStatusRequest.php` (union-of-enums variant) | role-match |
| `app/Http/Resources/Housekeeping/HousekeepingTaskResource.php` | model (resource) | transform | `app/Http/Resources/Operations/OperationsQueueItemResource.php` + `whenLoaded()` idiom (project-wide) | role-match |
| `app/Http/Resources/Operations/OperationsQueueItemResource.php` (widen 3-way) | model (resource) | transform | itself (current version) | exact |
| `app/Http/Resources/Operations/ServiceRequestBoardResource.php` | model (resource) | transform | `app/Http/Resources/Operations/OperationsQueueItemResource.php` (nested guest/reservation shape) | role-match |
| `app/Http/Resources/Operations/DepartureServiceResource.php` | model (resource) | transform | `app/Http/Resources/Operations/OperationsQueueItemResource.php` | role-match |
| `database/factories/HousekeepingTaskFactory.php` | test | CRUD | any existing model factory (e.g. `RoomFactory`) | role-match |
| `app/Console/Commands/ReconcileHousekeepingTasks.php` (`housekeeping:reconcile`) | utility (command) | batch | `app/Console/Commands/ExpireDigitalKeys.php` | exact |
| `database/seeders/RolesAndPermissionsSeeder.php` (widen) | config | CRUD | itself (current version) | exact |
| `database/seeders/GuestServiceCatalogSeeder.php` (widen, 2 new CATALOG entries) | config | CRUD | itself (current version, `concierge`/`maintenance` entries) | exact |
| `app/Enums/Department.php` (`forServiceType` widen) | model (enum) | transform | itself (current version) | exact |
| `app/Providers/AppServiceProvider.php` (`morphMap` widen) | config | transform | itself (current `Relation::morphMap([...])` call) | exact |
| `config/hotel.php` (`turnover_sla_minutes`) | config | transform | itself (existing `check_out_time` key) | exact |
| `tests/Feature/Housekeeping/*Test.php` | test | request-response | `tests/Feature/Operations/OperationsQueueTest.php` | role-match |
| `tests/Feature/Operations/OperationsQueueHousekeepingTest.php` | test | request-response | `tests/Feature/Operations/OperationsQueueTest.php` | exact |
| `tests/Unit/Housekeeping/*Test.php` | test | event-driven | `tests/Concerns/RecordsRowLocks.php` usage in existing lock-order unit tests | role-match |

## Pattern Assignments

### `app/Actions/Housekeeping/UpdateHousekeepingTaskStatusAction.php` (service, request-response)

**Analog:** `app/Actions/Cms/UpdateRoomStatusAction.php` (full file, read above)

**Core pattern — lock, transition-table check, write, history, all in one transaction:**
```php
public function handle(HousekeepingTask $task, HousekeepingTaskStatus $to, ?string $reason, ?User $actor): array
{
    return DB::transaction(function () use ($task, $to, $reason, $actor) {
        $room = Room::whereKey($task->room_id)->lockForUpdate()->firstOrFail(); // room BEFORE task (D-05 lock order)
        $locked = HousekeepingTask::whereKey($task->getKey())->lockForUpdate()->firstOrFail();
        $from = $locked->status;

        if (! $from->canTransitionTo($to)) {
            throw new HousekeepingTaskTransitionException(__('custom.errors.housekeeping_task_transition_invalid'), [
                'from' => $from->value, 'to' => $to->value,
                'allowed' => array_map(fn ($s) => $s->value, $from->allowedTargets()),
            ]);
        }

        $locked->forceFill([
            'status' => $to,
            'started_at' => $to === HousekeepingTaskStatus::IN_PROGRESS ? ($locked->started_at ?? now()) : $locked->started_at,
            'completed_at' => $to === HousekeepingTaskStatus::DONE ? now() : $locked->completed_at,
            'completed_by' => $to === HousekeepingTaskStatus::DONE ? $actor?->getKey() : $locked->completed_by,
        ])->save();

        HousekeepingTaskStatusHistory::create([
            'housekeeping_task_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value,
            'changed_by' => $actor?->getKey(), 'reason' => $reason,
        ]);

        // D-07 room hook: turnover done + room dirty -> UpdateRoomStatusAction inside this same transaction.
        if ($to === HousekeepingTaskStatus::DONE && $locked->type === HousekeepingTaskType::TURNOVER && $room->status === RoomStatus::DIRTY) {
            $this->updateRoomStatus->handle($room, RoomStatus::AVAILABLE, 'turnover', $actor);
        }

        HousekeepingTaskChanged::dispatch($locked); // ShouldDispatchAfterCommit

        return ['data' => $locked->fresh(), 'code' => 200];
    }, 3);
}
```
Apply the identical shape to `UpdateServiceBookingStatusAction` (single model, no room hook, transitions `pending→confirmed|cancelled`, `confirmed→completed|cancelled`).

---

### `app/Actions/Housekeeping/CreateHousekeepingTaskAction.php::ensureOpen` (service, event-driven)

**Analogs:** `app/Actions/Notification/RegisterDeviceTokenAction.php` (unique-violation-inside-transaction idiom, full file above) + RESEARCH.md Pattern 2 (verbatim code already drafted there).

**Key idiom to copy** — the retry parameter of `DB::transaction($cb, 3)` does NOT catch unique-violation races; catch it manually inside the closure:
```php
try {
    $deviceToken = DeviceToken::updateOrCreate(['token' => $token], $attributes);
} catch (QueryException $e) {
    if (! str_contains(strtolower($e->getMessage()), 'unique')) {
        throw $e;
    }
    $deviceToken = DeviceToken::where('token', $token)->firstOrFail();
    $deviceToken->update($attributes);
}
```
For housekeeping, prefer catching `Illuminate\Database\UniqueConstraintViolationException` specifically (verified present in vendor) rather than string-matching the message, then re-read under the lock already held (see RESEARCH.md Pattern 2 for the full `ensureOpen()` body — copy it verbatim, adjusting property names to match the final migration).

---

### `app/Actions/Operations/AssignRequestAction.php` / `UpdateRequestStatusAction.php` (widen, request-response)

**Analog:** the files themselves (current state, read above) + delegation precedent from `CheckOutReservationAction::dirtyAssignedRooms` (delegates to `UpdateRoomStatusAction` rather than inlining a room write).

**Widening pattern — thin dispatcher, SR/Ticket branch untouched, HousekeepingTask branch delegates and skips the sync mirror:**
```php
public function handle(ServiceRequest|Ticket|HousekeepingTask $item, User $user): array
{
    if ($item instanceof HousekeepingTask) {
        return $this->assignHousekeepingTask->handle($item, $user); // no mirrorToFirestore() call here — D-11b
    }

    $item->update(['assigned_user_id' => $user->id]); // UNCHANGED — do not add transaction/lock here (Pitfall B)
    $item->refresh();
    $this->mirrorToFirestore('ops_queue', OperationsQueueMirror::documentId($item), OperationsQueueMirror::payload($item));
    return ['data' => $item, 'code' => 200];
}
```
Same shape for `UpdateRequestStatusAction::handle(ServiceRequest|Ticket|HousekeepingTask $item, string $status, ?User $actor = null, ?string $reason = null)` — new trailing params are optional/additive so `OperationsQueueService` call sites don't break.

---

### `app/Services/Housekeeping/HousekeepingTaskService.php` (service, CRUD)

**Analog:** `app/Services/Operations/OperationsQueueService.php` (full file above)

Copy the `assign()`/`updateStatus()`/`assertCan()` shape (permission-gated wrapper delegating to the single-writer action) and the bounded-fetch `index()` shape for `HousekeepingTaskFilter`-driven listing. `OperationsQueueService::resolve()`/`requiredPermission()` should be widened via `OperationsQueueType` registry lookups (RESEARCH.md Pattern 4) rather than adding a third `match` arm by hand — the registry shape is drafted in RESEARCH.md's "Ops queue registry entry shape" code block.

---

### `app/Http/Controllers/Admin/HousekeepingTaskController.php` (controller, request-response)

**Analog:** `app/Http/Controllers/Admin/OperationsQueueController.php` (full file above)

Copy the constructor-injected service + `respondFromService()`/`paginatedSuccess()` envelope pattern; no manual JSON building, no DB access in the controller.

---

### `app/Filters/HousekeepingTaskFilter.php` / `ServiceRequestFilter.php` (utility, transform)

**Analog:** `app/Filters/ReservationFilter.php` (full file above)

Copy: `protected array $safeParms` for plain-column DSL fields (`status`, `type`), override `applyConditions()` calling `parent::applyConditions($query)` first, then private `apply*()` methods for each custom non-DSL filter (`assignee` uuid|`unassigned`, `room` number|uuid, `due_date`, `date`, `guest`) each validating blank/type before calling `$this->reject()` for a 422. `isBlank()` and `reject()` come from `BaseFilter`.

---

### `app/Listeners/CreateTurnoverTaskOnCheckOut.php` / `CreateRequestTaskOnServiceRequestPlaced.php` / `MirrorHousekeepingTaskToFirestore.php` (event-driven)

**Analogs:** `app/Listeners/RevokeDigitalKeyOnCheckOut.php` (sync, constructor-injected action) and `app/Listeners/MirrorServiceRequestToFirestore.php` (`ShouldQueue`, `MirrorsToFirestore` trait) — both full files above.

```php
class CreateTurnoverTaskOnCheckOut
{
    public function __construct(private readonly CreateHousekeepingTaskAction $createTask) {}
    public function handle(ReservationCheckedOut $event): void
    {
        $actor = $event->actorId ? User::find($event->actorId) : null; // never Auth::user()
        foreach (/* distinct assigned rooms */ as $room) {
            $this->createTask->ensureOpen($room, HousekeepingTaskType::TURNOVER, [...], $actor);
        }
    }
}
```
`MirrorHousekeepingTaskToFirestore implements ShouldQueue`, `use MirrorsToFirestore;`, `handle(HousekeepingTaskChanged $event)` calling `$this->mirrorToFirestore('ops_queue', 'housekeeping_task_' . $event->task->uuid, [...])` — do not reuse `OperationsQueueMirror::payload()` as-is (it's typed `ServiceRequest|Ticket`); either widen it to a 3-way union or add a dedicated static method on it per RESEARCH.md Assumption A2 (Claude's discretion — prefer widening `OperationsQueueMirror` to keep one source of truth).

## Shared Patterns

### Single-writer status transition (lock → transition table → write → history → event)
**Source:** `app/Actions/Cms/UpdateRoomStatusAction.php`
**Apply to:** `UpdateHousekeepingTaskStatusAction`, `AssignHousekeepingTaskAction`, `UpdateServiceBookingStatusAction`

### Domain exception with `error_code` + context
**Source:** `App\Exceptions\RoomStatusTransitionException` (pattern), `App\Exceptions\FolioUnsettledException` (context payload shape, seen in `CheckOutReservationAction`)
**Apply to:** `HousekeepingTaskTransitionException`, `service_booking_transition_invalid`, `departure_service_readonly`, `housekeeping_task_closed` — each takes `(__('custom.errors.X'), ['from'=>..., 'to'=>..., 'allowed'=>...])`

### Firestore mirror single-source-of-truth
**Source:** `app/Support/OperationsQueueMirror.php`, `app/Listeners/MirrorServiceRequestToFirestore.php`
**Apply to:** `MirrorHousekeepingTaskToFirestore` — widen `OperationsQueueMirror` rather than duplicating payload logic (D-11b)

### Filter DSL (`BaseFilter` + custom `apply*()` methods)
**Source:** `app/Filters/ReservationFilter.php`
**Apply to:** `HousekeepingTaskFilter`, `ServiceRequestFilter`

### Sync listener on after-commit / plain event, auto-discovered (no `EventServiceProvider`)
**Source:** `app/Listeners/RevokeDigitalKeyOnCheckOut.php`
**Apply to:** `CreateTurnoverTaskOnCheckOut`, `CreateRequestTaskOnServiceRequestPlaced`

### Additive, optional trailing params on a widened polymorphic action (no behavior change to existing arms)
**Source:** `app/Actions/Operations/{AssignRequestAction,UpdateRequestStatusAction}.php` (current), `app/Actions/Booking/CheckOutReservationAction.php` (delegation-not-inlining precedent)
**Apply to:** both actions' `HousekeepingTask` arm

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Support/OperationsQueueType.php` | config/registry | transform | First registry-over-match-statements pattern in this codebase (RESEARCH.md Pattern 4, D-12) — build from the drafted shape in RESEARCH.md, not from an existing file |
| `app/Support/DepartureServiceProjection.php` | utility | transform | First 3-source PHP-assembled projection (no new table) — follow room-board's ≤7-query discipline (Pitfall 8) referenced in RESEARCH.md, not a literal file copy |

## Metadata

**Analog search scope:** `backend/app/{Actions,Services,Http,Models,Filters,Listeners,Events,Enums,Support,Console/Commands}`, `backend/database/{migrations,seeders,factories}`, `backend/tests/Feature/Operations`
**Files scanned:** ~20 read in full this session (all listed above); remainder inferred from RESEARCH.md's own full-file reads (already verified there, not re-read here to avoid duplicate context cost)
**Pattern extraction date:** 2026-09-27
