# Phase 6: Housekeeping & Guest Services - Research

**Researched:** 2026-09-26
**Domain:** Laravel 13 / PHP 8.3 backend — new `housekeeping_tasks` domain wired into an existing polymorphic operations queue, plus two read-only projections (service-request board, departure services) over existing tables
**Confidence:** HIGH (every claim below was checked against the current file on disk in this repo, not training memory; the phase's own `06-CONTEXT.md` is itself an unusually deep, council-reviewed spec — this document's job is to verify it against the real code and flag the handful of places where it doesn't yet match)

## Summary

Phase 6 is a "wire together three read/write surfaces over mostly-existing data" phase, not a greenfield-data-model phase. One new table (`housekeeping_tasks` + its history table) gets a full CRUD-ish lifecycle with its own single-writer actions, copying the exact pattern `UpdateRoomStatusAction` already established in Phase 2 (row lock → transition-table check → write → history row, all in one transaction). The other two surfaces (`GET /cms/service-requests`, `GET /departure-services`) are pure projections: no new tables, just filters and resource shaping over `ServiceRequest`, `ServiceBooking`, `Transfer` and `Reservation`.

The riskiest part of this phase is not the new table — it's **widening two existing, already-shipped, already-tested classes** (`AssignRequestAction`, `UpdateRequestStatusAction`) from a `ServiceRequest|Ticket` union to a three-way union that includes `HousekeepingTask`, while those two classes today have **no transaction, no row lock, and no actor/reason parameters at all**. `06-CONTEXT.md` (D-13) already anticipates this and is correct about the shape needed; this research confirms the current code has none of the safety machinery the housekeeping arm requires, so the widening must add it, not just add a third `match` arm. The safe way to do this (confirmed below) is to make the "housekeeping" arm of both actions **delegate to the dedicated housekeeping single-writers** rather than touch `HousekeepingTask` rows directly — that keeps the transition table, the lock order and the room hook in exactly one place.

**Primary recommendation:** Build the housekeeping task lifecycle as a direct structural copy of `UpdateRoomStatusAction` (lock → transition table → write → history, one transaction), give `AssignRequestAction`/`UpdateRequestStatusAction` a `HousekeepingTask` arm that calls into the new single writers instead of inlining `$item->update()`, and treat `OperationsQueueService`/`OperationsQueueMirror`/`OperationsQueueItemResource`/`UpdateRequestStatusRequest` as one registry-driven surface (`OperationsQueueType`, per D-12) rather than hand-adding a third `match` branch to each of four files independently.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| HK-01 | List housekeeping tasks with filters (status, room, assignee, type, due date) | `GET /housekeeping/tasks` — new `HousekeepingTaskFilter extends BaseFilter`, same DSL as `ReservationFilter`/`GuestFilter`. See Architecture Patterns, Code Examples. |
| HK-02 | Assign a housekeeping task | `AssignHousekeepingTaskAction` single writer; reused by `AssignRequestAction`'s third arm for HK-05. See D-05, D-13. |
| HK-03 | Task lifecycle with history; turnover completion moves room `dirty→available` | `UpdateHousekeepingTaskStatusAction` = `UpdateRoomStatusAction` structural copy + D-07 room hook. See Code Examples. |
| HK-04 | Auto-create turnover task on check-out; SR→HOUSEKEEPING creates request task; room-board close closes turnover | `ReservationCheckedOut` (verified `ShouldDispatchAfterCommit`, ctor `(Reservation, CheckOutMode, ?int $actorId)`) and `ServiceRequestPlaced` (verified, sync `event()` call) listeners; `ensureOpen()` dedupe. See Architecture Patterns. |
| HK-05 | Housekeeping tasks in merged ops queue as third type | `OperationsQueueService`/`Controller`/`Mirror`/`ItemResource`/`UpdateRequestStatusRequest` all verified below; widening plan under Architecture Patterns. |
| SVC-01 | Service-request board | `Admin\ServiceRequestBoardController` on `BaseController` + `paginatedSuccess`, new `ServiceRequestFilter`. No write routes (D-17). |
| SVC-02 | Departure services list | `DepartureServiceProjection` — PHP-assembled union of `ServiceBooking` (transfer), `ServiceRequest` (late_checkout/luggage), `Reservation` (express_checkout), no new table. `HotelClock`-based UTC BETWEEN window verified. |
| SVC-03 | Departure service status patch | Delegates to `UpdateRequestStatusAction` (request) or new `UpdateServiceBookingStatusAction` (booking); express_checkout read-only. |
| SVC-04 | Quick-request chips (docs only) | `GuestServiceCatalogSeeder` structure verified; `is_default` item pattern already exists (concierge/transport/maintenance); two new DIRECT categories needed. |
| DOCS-01 | Docs/tree/Postman/changelog gate | `docs/carlton-tree.html` node line numbers verified; `API_GUIDE_DASHBOARD.md` module pattern verified; Postman folder-numbering convention verified. |
| XCUT-01 | Permission seeding contract | `RolesAndPermissionsSeeder` current state verified (21 permissions / 10 groups / 7 presets); exact re-pin risk flagged in Assumptions Log. |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Housekeeping task lifecycle (create/assign/status) | API / Backend (Action layer) | Database (history table) | Single-writer action pattern already established by `UpdateRoomStatusAction`; no client-side state machine. |
| Room `dirty→available` on turnover completion | API / Backend | — | Delegates to existing `UpdateRoomStatusAction` inside the task's own transaction — never a second writer of `rooms.status`. |
| Ops queue merge (3rd type) | API / Backend (Service layer) | — | In-memory merge already exists (`OperationsQueueService`); this phase adds a registry entry, not a new merge strategy. |
| Firestore mirror | API / Backend (queued listener) | External (Firestore, best-effort) | `MirrorsToFirestore` trait already isolates failures from the request; housekeeping gets its own event+listener per D-11b rather than reusing the SR/Ticket sync-trait call. |
| Service-request board / departure-services projection | API / Backend (Service + a plain PHP projection class) | — | Read-only; no new persistence. `DepartureServiceProjection` assembles PHP arrays from three sources, same discipline as the room board (Pitfall 8). |
| Quick-request chip → catalogue mapping | Mobile client (chip rendering) | API / Backend (catalogue data) | Backend only supplies data (`is_default` items of `direct` categories); no new route (D-23). |
| Permission gating | API / Backend (route middleware + service `assertCan`) | — | `permission:` middleware for simple routes; `OperationsQueueType`-driven `requiredPermission()` for the polymorphic verbs, matching the existing `service-requests`/`tickets` pattern. |

## Standard Stack

No new external packages. This phase is entirely internal Laravel/PHP code reusing the project's existing stack (Laravel 13.8, PHP 8.3, Spatie `laravel-permission`, Spatie `laravel-activitylog`, Sanctum). `composer.json` requires `"laravel/framework": "^13.8"`, `"php": "^8.3"` — [VERIFIED: backend/composer.json].

### Core (reused, not new)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | ^13.8 | `DB::transaction($cb, $attempts)`, `lockForUpdate()`, `ShouldDispatchAfterCommit`, event auto-discovery | Already the project's only framework; `Illuminate\Database\UniqueConstraintViolationException` confirmed present in vendor — [VERIFIED: `backend/vendor/laravel/framework/src/Illuminate/Database/UniqueConstraintViolationException.php` exists on disk] |
| `spatie/laravel-permission` | (existing) | `Permission::firstOrCreate`, `Role::syncPermissions`, `->givePermissionTo()` | Already backs every `{domain}.{action}` permission in the seeder |
| `spatie/laravel-activitylog` | (existing) | `LogsActivity` trait on `HousekeepingTask`, `activity()->log()` for room-left-in-maintenance property | Already used on every model in this codebase |

### Package Legitimacy Audit

**Not applicable — this phase installs no new packages.** No `composer require` is expected; skip the legitimacy gate.

## Architecture Patterns

### System Architecture Diagram

```
                         ┌─────────────────────────────┐
                         │   ReservationCheckedOut       │  (Phase 3, after-commit)
                         │   ServiceRequestPlaced         │  (existing, sync event())
                         └───────────┬─────────────────┘
                                     │  new sync listeners (auto-discovered)
                    ┌────────────────┴─────────────────┐
                    │                                    │
        CreateTurnoverTaskOnCheckOut        CreateRequestTaskOnServiceRequestPlaced
                    │                                    │
                    └───────────────┬────────────────────┘
                                     ▼
                   CreateHousekeepingTaskAction::ensureOpen()
              (DB::transaction(fn,3) → lockForUpdate(room) → find-open-or-insert
               → catch ONE UniqueConstraintViolationException → re-read locked → rethrow)
                                     │
                                     ▼
                         housekeeping_tasks (+ history row, same txn)
                                     │
        ┌────────────────────────────┼─────────────────────────────┐
        ▼                            ▼                             ▼
GET /housekeeping/tasks   PATCH …/assign, …/status      /operations/queue/housekeeping-tasks/{uuid}
(HousekeepingTaskFilter)  (AssignHousekeepingTaskAction, (OperationsQueueService::resolve() via
                           UpdateHousekeepingTaskStatusAction   OperationsQueueType registry) ──►
                           — room lock BEFORE task lock,        AssignRequestAction /
                           room hook on turnover `done`)        UpdateRequestStatusAction
                                     │                           (3rd arm DELEGATES to the
                                     ▼                           two actions above — does not
                        HousekeepingTaskChanged (after-commit)   inline $item->update())
                                     │
                                     ▼
                MirrorHousekeepingTaskToFirestore (queued) ──► ops_queue/housekeeping_task_{uuid}


GET /cms/service-requests ──► ServiceRequestBoardController ──► ServiceRequestFilter ──► ServiceRequestBoardResource
                                                                  (assignee, room via reservation.rooms.room, date, guest)

GET /departure-services ──► DepartureServiceProjection ──► three set-based queries
   (transfer: ServiceBooking bookable_type=transfer;         (never one query per row — Pitfall 8)
    late_checkout/luggage: ServiceRequest.type;
    express_checkout: Reservation.check_out_mode)
        │
        ▼
PATCH /departure-services/{uuid}/status ──► probe booking→request→reservation (or source_type hint)
        │                                          │
        ▼                                          ▼
UpdateServiceBookingStatusAction (new,      UpdateRequestStatusAction (existing, request arm)
 lockForUpdate, pending→confirmed|cancelled,
 confirmed→completed|cancelled)
```

### Recommended Project Structure
```
app/
├── Actions/Housekeeping/
│   ├── CreateHousekeepingTaskAction.php      # ensureOpen(); D-02
│   ├── AssignHousekeepingTaskAction.php      # D-05
│   └── UpdateHousekeepingTaskStatusAction.php# D-05, D-07 room hook
├── Actions/Service/
│   └── UpdateServiceBookingStatusAction.php  # D-22, new
├── Services/Housekeeping/
│   └── HousekeepingTaskService.php           # index() + HousekeepingTaskFilter wiring
├── Services/Operations/
│   ├── ServiceRequestBoardService.php        # SVC-01
│   └── DepartureServiceService.php           # SVC-02/03, wraps DepartureServiceProjection
├── Support/
│   ├── DepartureServiceProjection.php        # pure PHP projection, no model
│   └── OperationsQueueType.php               # D-12 registry: model, permission map, status enum, mirror prefix, open-status list
├── Models/
│   ├── HousekeepingTask.php                  # saving() hook sets dedupe_key (D-02)
│   └── HousekeepingTaskStatusHistory.php     # RoomStatusHistory structural twin
├── Listeners/
│   ├── CreateTurnoverTaskOnCheckOut.php      # sync, on ReservationCheckedOut
│   ├── CreateRequestTaskOnServiceRequestPlaced.php # sync, on ServiceRequestPlaced
│   └── MirrorHousekeepingTaskToFirestore.php # ShouldQueue, on HousekeepingTaskChanged
├── Events/
│   └── HousekeepingTaskChanged.php           # ShouldDispatchAfterCommit
├── Filters/
│   ├── HousekeepingTaskFilter.php
│   └── ServiceRequestFilter.php
├── Http/Controllers/Admin/
│   ├── HousekeepingTaskController.php
│   ├── ServiceRequestBoardController.php
│   └── DepartureServiceController.php
└── Http/Resources/Housekeeping/
    └── HousekeepingTaskResource.php
```

### Pattern 1: Single-writer status transition (copy `UpdateRoomStatusAction`)

**What:** Lock the row, look up the transition table off an enum method, write status + denormalized timestamp + history row, all inside one `DB::transaction`.
**When to use:** `UpdateHousekeepingTaskStatusAction` and `UpdateServiceBookingStatusAction` both follow this exactly.
**Example (verified current source, Phase 2):**
```php
// Source: backend/app/Actions/Cms/UpdateRoomStatusAction.php (read in full this session)
public function handle(Room $room, RoomStatus $to, ?string $reason, ?User $actor): array
{
    return DB::transaction(function () use ($room, $to, $reason, $actor) {
        $locked = Room::whereKey($room->getKey())->lockForUpdate()->firstOrFail();
        $from   = $locked->status;

        if (! $from->canTransitionTo($to)) {
            throw new RoomStatusTransitionException(__('custom.errors.room_status_transition_invalid'), [
                'from' => $from->value, 'to' => $to->value,
                'allowed' => array_map(fn (RoomStatus $s) => $s->value, $from->allowedTargets()),
            ]);
        }

        $locked->forceFill(['status' => $to, 'status_changed_at' => now(), 'status_changed_by' => $actor?->getKey()])->save();
        RoomStatusHistory::create(['room_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value, 'changed_by' => $actor?->getKey(), 'reason' => $reason]);

        return ['data' => $locked->fresh(), 'code' => 200];
    });
}
```
`HousekeepingTaskStatus` needs the same `allowedTargets()`/`canTransitionTo()` pair (D-05's transition table: `pending→assigned|in_progress|cancelled`, `assigned→in_progress|cancelled`, `in_progress→done|cancelled`).

### Pattern 2: Dedupe-on-insert with a manual catch inside the transaction (D-02)

**What matters (verified, not obvious from D-02's prose alone):** `DB::transaction($callback, 3)`'s third argument only auto-retries on **deadlock/serialization-failure** exceptions — [VERIFIED: `backend/vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php` `transaction(Closure $callback, $attempts = 1)`]. It does **not** catch `UniqueConstraintViolationException` for you. The "catch one `UniqueConstraintViolationException` then re-read with `lockForUpdate()`, rethrow if still nothing" logic in D-02 must be a manual `try/catch` **inside** the closure passed to `DB::transaction`, not something the `3` retry count provides:
```php
public function ensureOpen(Room $room, HousekeepingTaskType $type, array $attrs, ?User $actor): array
{
    return DB::transaction(function () use ($room, $type, $attrs, $actor) {
        $locked = Room::whereKey($room->getKey())->lockForUpdate()->firstOrFail();

        $existing = HousekeepingTask::where('room_id', $locked->id)
            ->where('type', $type)
            ->whereIn('status', HousekeepingTaskStatus::open())
            ->first();
        if ($existing) {
            return ['data' => $existing, 'code' => 200];
        }

        try {
            $task = HousekeepingTask::create([...$attrs, 'room_id' => $locked->id, 'type' => $type, 'created_by' => $actor?->getKey()]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Lost the race on dedupe_key — re-read under the lock we already hold.
            $task = HousekeepingTask::where('room_id', $locked->id)->where('type', $type)
                ->whereIn('status', HousekeepingTaskStatus::open())->first()
                ?? throw new \RuntimeException('dedupe race: no open task found after unique violation');
        }

        HousekeepingTaskStatusHistory::create(['housekeeping_task_id' => $task->id, 'from_status' => null, 'to_status' => 'pending', 'changed_by' => $actor?->getKey()]);
        return ['data' => $task, 'code' => 201];
    }, 3); // the `3` guards lockForUpdate() deadlock contention, not the unique-key race above
}
```

### Pattern 3: Widening a polymorphic action without breaking two live call sites

**Verified current state (both files read in full):**
```php
// backend/app/Actions/Operations/AssignRequestAction.php — CURRENT, no transaction, no lock, no actor param
public function handle(ServiceRequest|Ticket $item, User $user): array {
    $item->update(['assigned_user_id' => $user->id]);
    $item->refresh();
    $this->mirrorToFirestore('ops_queue', OperationsQueueMirror::documentId($item), OperationsQueueMirror::payload($item));
    return ['data' => $item, 'code' => 200];
}

// backend/app/Actions/Operations/UpdateRequestStatusAction.php — CURRENT, same shape
public function handle(ServiceRequest|Ticket $item, string $status): array {
    $item->update(['status' => $status]);
    $item->refresh();
    $this->mirrorToFirestore(...);
    return ['data' => $item, 'code' => 200];
}
```
D-13 requires `handle(ServiceRequest|Ticket|HousekeepingTask $item, User $user)` and `handle(..., string $status, ?User $actor = null, ?string $reason = null)`. Because `$actor`/`$reason` are new **optional trailing parameters with defaults**, this is additive from the caller's point of view — `OperationsQueueService::assign()`/`updateStatus()` (the only call sites) can pass them through without breaking anything, and `OperationsQueueTest.php`'s 15 existing tests (verified: read in full) exercise these two actions only through HTTP and assert on response shape, not on the method signature, so they stay green as long as the SR/Ticket arms' *behavior* (still no transaction, still mirrors via the sync trait) is preserved byte-for-byte. **Do not add a transaction or lock to the SR/Ticket arms as a side effect of this refactor** — that would be an undocumented behavior change to code Pitfall 10 explicitly calls out as contract-frozen.

The `HousekeepingTask` arm must **not** call `$item->update(...)` inline (it has no transition-table check, no lock, no history row, no room hook). It must delegate:
```php
public function handle(ServiceRequest|Ticket|HousekeepingTask $item, User $user): array
{
    if ($item instanceof HousekeepingTask) {
        return $this->assignHousekeepingTask->handle($item, $user); // AssignHousekeepingTaskAction
    }
    $item->update(['assigned_user_id' => $user->id]); // unchanged SR/Ticket path
    // ...
}
```
Per D-11b, the housekeeping single writers already dispatch `HousekeepingTaskChanged` → queued Firestore mirror, so **the `HousekeepingTask` branch must skip the `mirrorToFirestore()` call** the SR/Ticket branch makes — mirroring twice would double-write (harmless to Firestore idempotently, but wastes a queued job and contradicts D-11b's "the queue actions' task arm does not mirror separately").

### Pattern 4: Registry over four hand-widened `match` statements (D-12)

Verified touch points that all currently have a two-way `match`/`if` on type: `OperationsQueueService::resolve()` (throws `NotFoundException` on unknown `$type`), `OperationsQueueService::requiredPermission()`, `UpdateRequestStatusRequest::rules()` (picks `TicketStatus::class` vs `ServiceRequestStatus::class` off `$this->route('type')`), `OperationsQueueMirror::documentId()`/`payload()`, `OperationsQueueItemResource::toArray()` (checks `$item instanceof ServiceRequest`). D-12's `OperationsQueueType` registry class (segment string → model class, permission map, status enum, mirror id prefix, open-status list) is the right call: it turns "add a third arm" into "add one array entry read by five places" instead of five independent edits that can drift. Confirmed nothing like this registry exists yet — it is new code for this phase, not a rename of something existing.

### Pattern 5: Sync listener on an after-commit event (already proven, Phase 4)

```php
// Source: backend/app/Listeners/RevokeDigitalKeyOnCheckOut.php (verified in full)
class RevokeDigitalKeyOnCheckOut
{
    public function __construct(private readonly RevokeDigitalKeyAction $revokeKey) {}
    public function handle(ReservationCheckedOut $event): void {
        $this->revokeKey->handle($event->reservation, DigitalKeyRevocationReason::CHECKED_OUT->value);
    }
}
```
No `EventServiceProvider` exists in this codebase — [VERIFIED: `find backend/app/Providers -iname "*Event*"` returns nothing] — listener auto-discovery (Laravel's default `shouldDiscoverEvents()`) is what wires `handle(ReservationCheckedOut $event)` up. `CreateTurnoverTaskOnCheckOut` and `CreateRequestTaskOnServiceRequestPlaced` need no manual registration, same as this one. `ReservationCheckedOut`'s real constructor is `(Reservation $reservation, CheckOutMode $mode, ?int $actorId)` — [VERIFIED, full source read] — so the turnover listener resolves the acting user (if any) via `User::find($event->actorId)`, not by re-reading `Auth::user()`.

### Anti-Patterns to Avoid
- **Reusing `RoomStatus::allowedTargets()` machinery by inheritance for `HousekeepingTaskStatus`:** they are unrelated enums with different case sets; copy the *shape* (a `match` returning a `list<self>`), don't try to share code between them.
- **Mirroring a housekeeping task via the SR/Ticket sync trait call inside `AssignRequestAction`/`UpdateRequestStatusAction`:** D-11b's queued-listener-on-event path is the only mirror writer for tasks; a second write path duplicates effort and risks payload drift between the two callers.
- **Building the departure-services list as three separate paginated queries the client merges:** D-21 requires one unpaginated `data.items` (≤500) assembled in PHP by `DepartureServiceProjection`, mirroring the room-board discipline in Pitfall 8 (set-based queries, no per-row query).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Status transition validation | A bespoke `if/elseif` chain per action | `enum HousekeepingTaskStatus { ... } public function allowedTargets(): array` mirroring `RoomStatus` | Already the codebase's proven pattern (Phase 2); a plan-checker will flag divergence |
| Dedupe-on-insert race | A `SELECT ... then INSERT if not found` without a unique index | DB-level `UNIQUE (source_type, source_id)` / a computed `dedupe_key` column + catch `UniqueConstraintViolationException` | PITFALLS.md Pitfall 4 documents this exact bug class as the reason housekeeping tasks are a real table with dedupe at the DB level, not an app-level check |
| Housekeeping ↔ ops-queue merge | A fourth in-memory concat pushing `MERGE_FETCH_LIMIT` past its documented ceiling silently | Same bounded-fetch-per-table pattern already in `OperationsQueueService::index()` (`MERGE_FETCH_LIMIT = 500`), and document the 3×500 hand-union explicitly (D-12) | PITFALLS.md Pitfall 4/Integration Gotchas flags a third hand-merged source as compounding an already-known bottleneck; SQL UNION is explicitly deferred (out of scope) |
| Departure-services grid assembly | Per-row queries for guest/room/reservation | `DepartureServiceProjection` built from ≤7 set-based queries (D-21) | Same discipline that fixed the room board (Pitfall 8) |
| Firestore payload shape agreement between writers | Re-deriving the mirror payload inline in each action | `OperationsQueueMirror::payload()` / a new `payloadForHousekeepingTask()` as the single source of truth | Already the stated purpose of this class's docblock: "used by ... the assign/status Actions alike, so every writer of a given document agrees on its fields" |

**Key insight:** Every "don't hand-roll" item above already has a working reference implementation in this exact codebase from an earlier phase. The correct move in each case is "copy the shape, don't invent a new one" — the plan-checker should treat any divergence from the cited precedent as something to justify explicitly, not silently do differently.

## Common Pitfalls

### Pitfall A: Trusting `DB::transaction($cb, 3)` to catch the unique-key race
**What goes wrong:** A plan writes `DB::transaction($cb, 3)` and assumes the retry count handles the dedupe race from D-02, so the `try/catch UniqueConstraintViolationException` gets skipped.
**Why it happens:** The `3` *looks* like generic retry protection, and the framework's own retry logic does auto-catch deadlocks, so it's easy to conflate the two failure modes.
**How to avoid:** The manual `try { create() } catch (UniqueConstraintViolationException) { re-read }` block must be written explicitly inside the closure (see Pattern 2). Verified: [VERIFIED, `backend/vendor/laravel/framework/.../ManagesTransactions.php`] — the built-in retry only fires on deadlock/serialization exceptions.
**Warning signs:** A `CreateHousekeepingTaskAction` test that races two `ensureOpen()` calls and gets a 500 instead of one 201 + one 200.

### Pitfall B: Widening `AssignRequestAction`/`UpdateRequestStatusAction` by adding a transaction to the whole method
**What goes wrong:** Adding `DB::transaction()` around the *entire* widened `handle()` — including the existing SR/Ticket branches — silently changes behavior for two already-shipped, already-tested code paths (e.g., changes lock timing, or double-wraps a transaction if the SR/Ticket branch's future code ever needs one).
**Why it happens:** "Add safety when touching a file" is a natural instinct, but Pitfall 10 (existing contract) applies here: the SR/Ticket arms are frozen behavior.
**How to avoid:** Keep the transaction/lock entirely inside the delegated `HousekeepingTask` single-writer actions; the widened `handle()` method itself stays a thin dispatcher.
**Warning signs:** `OperationsQueueTest.php`'s existing 15 tests (verified line-by-line) start asserting different query counts or timing.

### Pitfall C: Permission-count arithmetic drift from Phase 5
**What goes wrong:** D-06 states "24 permissions / 11 groups" after adding `housekeeping.view|assign|update`. Verified current state (pre-Phase-5): **21 permissions, 10 groups, 7 role presets** — [VERIFIED, `backend/tests/Feature/SeederTest.php::test_all_21_permissions_seeded`, `RolesAndPermissionsSeeder.php` read in full]. Phase 5 (concurrently in flight; do not rely on its files) is expected to add `folios.post`, `folios.dispute` to the existing `folios` group (no new group) per this task's own briefing, landing at **23 permissions / 10 groups**. Adding 3 new `housekeeping.*` permissions in 1 new group from there gives **26 permissions / 11 groups**, not 24.
**Why it happens:** D-06 was likely drafted before Phase 5's exact permission additions were finalized, or assumed a different Phase 5 delta.
**How to avoid:** The planner must **not** hardcode "24" into a re-pinned `SeederTest`/`PermissionsGroupedTest`/`RolePresetsTest`/`PermissionGuideAccuracyTest` assertion. Instead, plan a task step that: (1) reads the actual seeded permission count once Phase 5 has landed (`git log`/`RolesAndPermissionsSeeder.php` on disk at execution time), (2) adds the 3 housekeeping permissions on top of that real number, (3) updates the test assertions to match the *computed* total, not a number copied from CONTEXT.md.
**Warning signs:** A test hardcoding `assertCount(24, ...)` that fails immediately once Phase 5's actual delta becomes visible.

### Pitfall D: `Department::forServiceType()` fallback swallowing new codes
**What goes wrong:** `Department::forServiceType()` (verified, full source read) has a `default => self::CONCIERGE` fallback with no `late_checkout`/`luggage` cases. If those two cases are forgotten, `late_checkout` requests silently route to `concierge` instead of `reception` (D-19 requires `late_checkout` → reception, `luggage` → concierge).
**How to avoid:** Add both `'late_checkout' => self::RECEPTION` and `'luggage' => self::CONCIERGE` explicitly to the `match` — `luggage`'s fallback would accidentally be correct, `late_checkout`'s would not, which makes this an easy bug to miss in review since one of the two "just works" without the fix.
**Warning signs:** A departure-services test where a `late_checkout` request's `department` reads `concierge` instead of `reception`.

### Pitfall E: `morphMap` alias missing for `service_request` breaks `source_type`
**What goes wrong:** `housekeeping_tasks.source_type` needs the `service_request` alias (D-01) for its morph relation to `ServiceRequest`. Verified current `Relation::morphMap()` call (`backend/app/Providers/AppServiceProvider.php`) registers `spa_service`, `restaurant_table`, `pool_cabana`, `transfer` only — no `service_request` key exists yet.
**How to avoid:** Add `'service_request' => \App\Models\ServiceRequest::class` to the existing `Relation::morphMap([...])` array (one array entry, same call site) — do not create a second `morphMap()` call, which would silently overwrite the first in some Laravel versions or just be confusing to find later.
**Warning signs:** A `HousekeepingTask::source` relation returning null or the FQCN instead of the aliased type when read back.

### Pitfall F: `ServiceBookingFactory` default `bookable_type` is `spa_service`, not `transfer`
**What goes wrong:** Verified `ServiceBookingFactory::definition()` defaults to `BookableType::SPA_SERVICE` + a `SpaService::factory()`. A departure-services (transfer) test that calls `ServiceBooking::factory()->create()` without overriding `bookable`/`bookable_type` will not appear in the `transfer` kind at all.
**How to avoid:** Tests must explicitly do `ServiceBooking::factory()->for(Transfer::factory(), 'bookable')->create([...])` (Laravel's `for()` on a `MorphTo` factory relation calls `associate()`, which correctly resolves to the `transfer` morph-map alias since it's already registered) rather than relying on the factory default.
**Warning signs:** `DepartureServicesTest`'s transfer-kind assertions passing with 0 rows instead of failing loudly, if the projection query itself is also wrong in the same way.

## Code Examples

### Housekeeping status transition enum (mirrors `RoomStatus`, verified pattern)
```php
// Pattern verified from backend/app/Enums/RoomStatus.php
enum HousekeepingTaskStatus: string
{
    use HasValues;
    case PENDING = 'pending';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    public function allowedTargets(): array
    {
        return match ($this) {
            self::PENDING => [self::ASSIGNED, self::IN_PROGRESS, self::CANCELLED],
            self::ASSIGNED => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::DONE, self::CANCELLED],
            self::DONE, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $to): bool { return in_array($to, $this->allowedTargets(), true); }

    /** Statuses that count as "open" for dedupe and for the ops-queue open-status list (D-01, D-12). */
    public static function open(): array { return [self::PENDING, self::ASSIGNED, self::IN_PROGRESS]; }
}
```

### Ops queue registry entry shape (D-12, new — no existing precedent to copy verbatim, but the shape below satisfies every touch point verified above)
```php
final class OperationsQueueType
{
    public function __construct(
        public readonly string $segment,       // 'housekeeping-tasks'
        public readonly string $modelClass,     // HousekeepingTask::class
        public readonly string $assignPermission,
        public readonly string $statusPermission,
        public readonly string $statusEnumClass,// HousekeepingTaskStatus::class
        public readonly array $openStatuses,    // HousekeepingTaskStatus::open() values
        public readonly string $mirrorPrefix,   // 'housekeeping_task_'
    ) {}
}
```

### Enum values verified this session (exact case names/values — use these, do not guess)
- `RoomStatus`: `AVAILABLE='available'`, `DIRTY='dirty'`, `MAINTENANCE='maintenance'`; `allowedTargets()`: available→[dirty,maintenance], dirty→[available,maintenance], maintenance→[dirty].
- `ServiceRequestStatus`: `NEW='new'`, `IN_PROGRESS='in_progress'`, `COMPLETED='completed'`, `CANCELLED='cancelled'`; `active()` = `[NEW, IN_PROGRESS]`.
- `ServiceBookingStatus`: `PENDING`, `CONFIRMED`, `CANCELLED`, `COMPLETED`; `blockingSeating()` = `[PENDING, CONFIRMED]` (not directly used by SVC-02/03 but shows the enum's own "active" convention to mirror for `UpdateServiceBookingStatusAction`'s transition table: D-22 says `pending→confirmed|cancelled`, `confirmed→completed|cancelled`).
- `Department`: `KITCHEN, HOUSEKEEPING, CONCIERGE, RECEPTION, EVENTS, SALES, MAINTENANCE`. `forServiceType()` current cases: `room_service→KITCHEN`, `housekeeping→HOUSEKEEPING`, `laundry→HOUSEKEEPING`, `maintenance→MAINTENANCE`, default→`CONCIERGE`. **Add** `late_checkout→RECEPTION`, `luggage→CONCIERGE` (Pitfall D).
- `CheckOutMode`: `NONE='none'`, `STAFF_FORCE='staff_force'`, `GUEST_EXPRESS='guest_express'`.
- `ServiceCategoryKind`: `CATALOG`, `DIRECT`, `LINK`, `TOGGLE`. The two new SVC-02 categories (`late_checkout`, `luggage`) are `DIRECT`, matching `concierge`/`transport`/`maintenance`'s existing shape (one `is_default: true` item, `price_usd: null`).

### `GuestServiceCatalogSeeder` — exact append shape needed (verified full file read)
The seeder is a `private const CATALOG` array of associative arrays iterated once; each entry does `ServiceCategory::updateOrCreate(['code' => ...], [...])` then loops `items` doing `ServiceItem::updateOrCreate(['service_category_id' => ..., 'name->en' => ...], [...])`. Two new top-level array entries following the exact `concierge`/`maintenance` shape (kind `DIRECT`, one item with `'default' => true`, `'price' => null`) are additive and idempotent by construction — no new seeder logic needed, just two more `CATALOG` entries with `code: 'late_checkout'` / `code: 'luggage'`.

### `HotelClock`-based UTC-window query (D-18, verified `HotelClock::today()`/`checkOutAt()` source)
```php
// HotelClock::today() returns CarbonImmutable::now(tz)->startOfDay() — hotel-local midnight, in the hotel tz.
// checkOutAt() converts a date + config('hotel.check_out_time') in hotel tz, returned in UTC.
$dayStart = HotelClock::today()->addDays($offset);      // hotel-local midnight for date D, as a UTC instant once cast
$dayEnd   = $dayStart->addDay();
Reservation::query()
    ->whereIn('status', [ReservationStatus::CHECKED_IN, ReservationStatus::CHECKED_OUT])
    ->where(function ($q) use ($dayStart, $dayEnd, $D) {
        $q->whereDate('check_out', $D->toDateString())              // scheduled departures
          ->orWhereBetween('checked_out_at', [$dayStart, $dayEnd]); // actual departures within the hotel-local day
    });
```
This is a direct application of the pattern PITFALLS.md Pitfall 3 and the `whereDate()`/UTC-BETWEEN discipline already proven in `AvailabilityService` — never a raw `date(checked_out_at) = ?` string comparison, which drifts between SQLite and MySQL.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| `AssignRequestAction`/`UpdateRequestStatusAction` mirror synchronously via `MirrorsToFirestore` trait, called directly inside `handle()` | `HousekeepingTaskChanged` event (after-commit) → queued `MirrorHousekeepingTaskToFirestore` listener | This phase (D-11b, council amendment) | First domain in this codebase where the ops-queue mirror is queued rather than synchronous; the SR/Ticket arms keep the old synchronous pattern unchanged — **two different mirror mechanisms coexist deliberately**, not a full migration |
| Two-way `match`/`instanceof` scattered across `OperationsQueueService`, `OperationsQueueMirror`, `OperationsQueueItemResource`, `UpdateRequestStatusRequest` | `OperationsQueueType` registry (D-12) | This phase | First appearance of a registry pattern in this area; sets precedent for Phase 7's ticket-queue work reusing the same generalized queue |
| `rooms.status` transition table is the only precedent for "enum with `allowedTargets()`" in the codebase | `HousekeepingTaskStatus`/`ServiceBookingStatus` (new) gain the same shape | This phase | Confirms the pattern is now a house style, not a one-off |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Phase 5 lands with exactly `folios.post` + `folios.dispute` added to the existing `folios` group, giving 23 permissions / 10 groups before Phase 6 starts | Pitfall C, Standard Stack | If Phase 5 adds a different permission set (e.g., a new group), the "26 permissions / 11 groups" arithmetic in Pitfall C is wrong too — the planner must compute the real number from the landed seeder, not from this document or from D-06 |
| A2 | `HousekeepingTaskChanged`'s queued listener writes via `OperationsQueueMirror` extended with a `HousekeepingTask` case, rather than a wholly separate mirror-payload class | Architecture Patterns Pattern 3/4 | If the planner instead creates a parallel `HousekeepingTaskMirror` class, D-11b's "single source of truth" intent (already the documented purpose of `OperationsQueueMirror`) is undermined — low risk, purely a code-organization choice within Claude's Discretion per CONTEXT.md |
| A3 | `housekeeping:reconcile` (D-02) needs no `Schedule::command()` entry in `routes/console.php` — CONTEXT.md doesn't request a cadence and the Deferred Ideas list explicitly excludes "daily stayover scheduling command" | Runtime/Architecture | If ops actually needs this run automatically, a missing schedule entry means stale `dedupe_key`s only get cleaned up when someone runs the artisan command by hand — low risk, explicitly deferred |
| A4 | `DB::transaction($callback, 3)`'s retry semantics (deadlock-only, not unique-violation) apply identically on the SQLite `:memory:` test driver used by this project's test suite as on MySQL production | Pitfall A | SQLite has no real deadlock detection the same way MySQL does; a concurrency test for the dedupe race must simulate the unique-violation path directly (two sequential `ensureOpen()` calls within one request, not real parallel MySQL connections) rather than relying on true concurrent deadlock retry to prove itself — this matches how `RecordsRowLocks` already tests lock *clauses* rather than real blocking on SQLite |

**If this table is empty:** N/A — see rows above.

## Open Questions

1. **Exact permission/group count after Phase 5 + Phase 6 (Pitfall C)**
   - What we know: current verified state is 21 permissions / 10 groups / 7 presets; this task's own briefing states Phase 5 is expected to land at 23/10; D-06 in `06-CONTEXT.md` says the post-Phase-6 total is "24 permissions / 11 groups."
   - What's unclear: the arithmetic between "23 + 3 new housekeeping permissions" and "24" doesn't reconcile under any grouping assumption found in the current seeder.
   - Recommendation: the planner adds an explicit early task step that reads the actual seeded permission list from `RolesAndPermissionsSeeder.php` on disk (post-Phase-5) and computes the real target count before writing any re-pinned test assertion — never copy "24" forward uncritically.

2. **Whether `AssignRequestRequest` (existing, `user_uuid` required|string|exists:users,uuid) is reused as-is for `PATCH /housekeeping/tasks/{task}/assign`, or whether HK-02's dedicated route gets its own FormRequest**
   - What we know: `06-CONTEXT.md`'s Claude's Discretion section leaves "class/file names" open; the validation rule itself (`user_uuid` exists check) is identical to the existing `AssignRequestRequest`.
   - What's unclear: whether reusing the same class across `Operations` and `Housekeeping` namespaces is acceptable convention (the codebase's naming rule is domain-based request folders, `Http/Requests/{Domain}/...`), or whether a `Http/Requests/Housekeeping/AssignHousekeepingTaskRequest` duplicate is expected.
   - Recommendation: create a `Housekeeping`-domain duplicate with identical rules — matches the codebase's stated domain-based FormRequest convention (CONVENTIONS.md) over cross-domain reuse of `Operations\AssignRequestRequest`.

3. **Whether `housekeeping_task_status_history`'s `reason` column reuses the exact 255-char length RoomStatusHistory's `reason` uses**
   - What we know: `RoomStatusHistory.reason` is `string('reason', 255)->nullable()`.
   - What's unclear: D-03 doesn't specify a length for the housekeeping history's `reason` column.
   - Recommendation: match `RoomStatusHistory`'s `255` for consistency; no functional reason to diverge.

## Environment Availability

No new external dependencies. Firebase mirror (`FirebaseServiceInterface` / `NullFirebaseService` in non-production, `FakeFirebaseService` in tests) is already wired and used identically by the new `MirrorHousekeepingTaskToFirestore` listener — [VERIFIED: `backend/app/Services/Firebase/{FirebaseService,NullFirebaseService}.php`, `backend/tests/Support/FakeFirebaseService.php` all exist and are used by `OperationsQueueTest.php` today]. Queue driver for `ShouldQueue` listeners is whatever the project already runs tests against (synchronous in testing per `TESTING.md`'s "queue is synchronous in testing anyway" note) — no new queue infrastructure needed.

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| `Illuminate\Database\UniqueConstraintViolationException` | D-02 dedupe catch | Yes | Laravel 13.8 (vendor file confirmed present) | — |
| `DB::transaction($cb, $attempts)` retry param | D-02, D-05 lock order | Yes | Laravel 13.8 (`ManagesTransactions::transaction()` confirmed) | — |
| Event auto-discovery (no `EventServiceProvider`) | New sync listeners | Yes (confirmed: no `EventServiceProvider` file exists, matching `RevokeDigitalKeyOnCheckOut`'s documented reliance on it) | — | — |
| `FakeFirebaseService` test double | Firestore mirror tests | Yes | — | — |

**Missing dependencies with no fallback:** None.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12.5.12 — [VERIFIED: `backend/composer.json` `"phpunit/phpunit": "^12.5.12"`] |
| Config file | `backend/phpunit.xml` |
| Quick run command | `php artisan test --filter=Housekeeping` (or `--filter=OperationsQueueHousekeeping`, `--filter=ServiceRequestBoard`, `--filter=DepartureServices` per new class) |
| Full suite command | `php artisan test` (must stay green — Pitfall 10 / CONVENTIONS.md contract gate) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| HK-01 | List + filter tasks | Feature | `php artisan test --filter=test_.*list.*housekeeping` in `tests/Feature/Housekeeping/IndexTest.php` | ❌ Wave 0 |
| HK-02 | Assign task | Feature | `tests/Feature/Housekeeping/AssignTest.php` | ❌ Wave 0 |
| HK-03 | Status lifecycle + room hook | Feature + Unit | `tests/Feature/Housekeeping/StatusTest.php`, `tests/Unit/Housekeeping/UpdateHousekeepingTaskStatusActionTest.php` (room hook incl. maintenance, lock-order assertion via `RecordsRowLocks`) | ❌ Wave 0 |
| HK-04 | Turnover-on-checkout dedupe; SR→task link; room-board closes turnover | Feature + Unit | `tests/Feature/Housekeeping/{TurnoverOnCheckOut,RequestTaskOnServiceRequest,RoomBoardClosesTurnover}Test.php`, `tests/Unit/Housekeeping/CreateHousekeepingTaskActionTest.php` (double-dispatch dedupe, stale key) | ❌ Wave 0 |
| HK-05 | Queue 3rd type, `room_number`, `allowed_statuses` | Feature | `tests/Feature/Operations/OperationsQueueHousekeepingTest.php` — reuse `FakeFirebaseService` pattern from `OperationsQueueTest.php` | ❌ Wave 0 |
| SVC-01 | Board list + filters | Feature | `tests/Feature/Operations/ServiceRequestBoardTest.php`, query-count assertion (≤7) via `$this->expectsDatabaseQueryCount(7, ...)` (native Laravel test helper — no custom trait needed) | ❌ Wave 0 |
| SVC-02/03 | Departure list + status patch | Feature | `tests/Feature/Operations/DepartureServicesTest.php` — timezone boundary at hotel midnight, arrival-transfer exclusion, `truncated` at 501 rows, folio unchanged for a new `late_checkout` request | ❌ Wave 0 |
| SVC-04 | Chip→catalogue mapping | Docs only | No automated test — verify via `GET /public/service-catalog` fixture already covered by existing `ServiceCatalog` tests once the two new categories are seeded | ✅ (existing coverage extends) |

### Sampling Rate
- **Per task commit:** the relevant `--filter=` scoped run above.
- **Per wave merge:** `php artisan test` (full suite).
- **Phase gate:** full suite green before `/gsd-verify-work`, per the milestone-wide contract gate in `ROADMAP.md`.

### Wave 0 Gaps
- [ ] `tests/Concerns/RecordsRowLocks.php` — already exists (verified), reuse for the room→task lock-order assertion; no new trait needed.
- [ ] `database/factories/HousekeepingTaskFactory.php` — does not exist yet, must be created.
- [ ] `database/factories/{ServiceBooking,ServiceRequest,Transfer}Factory.php` — all three already exist (verified full read); `ServiceBookingFactory` needs an explicit `->for(Transfer::factory(), 'bookable')` override per test (see Pitfall F), not a new factory file.
- [ ] Framework install: none — PHPUnit/Mockery/`RefreshDatabase` already configured project-wide.

## Security Domain

### Applicable ASVS Categories (level 1, per `.planning/config.json` `security_asvs_level: 1`)

| ASVS Category | Applies | Standard Control |
|---------------|---------|-------------------|
| V2 Authentication | No (new) | Reuses existing Sanctum `auth:users` guard — no new auth surface |
| V3 Session Management | No (new) | No change to token issuance |
| V4 Access Control | Yes | `permission:housekeeping.view|assign|update` route middleware + `OperationsQueueType`-driven `requiredPermission()` in-service check for the polymorphic verbs (matches existing `service_requests.*`/`tickets.*` pattern) — never a role check inline in a controller (project convention) |
| V5 Input Validation | Yes | `HousekeepingTaskFilter extends BaseFilter` (whitelisted operators only, `422 validation_failed` on unknown values — same DSL as `ReservationFilter`); `UpdateRequestStatusRequest`'s per-type enum validation extended with a third `HousekeepingTaskStatus` branch |
| V6 Cryptography | No (new) | Nothing new to encrypt in this phase — housekeeping tasks carry no PII/credential fields |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|----------------------|
| Duplicate housekeeping task creation via retried check-out event or concurrent manual `POST /housekeeping/tasks` | Repudiation / DoS-by-noise (not a security exploit per se, but a data-integrity threat) | DB-level unique `dedupe_key` / `(source_type, source_id)` + `lockForUpdate()` inside `DB::transaction`, per Pattern 2 above |
| Second staff member races an assign/status write on the same task | Tampering (lost-update) | Room-then-task lock order inside every housekeeping single writer, same discipline as the folio settle-race fix already in this codebase (`PITFALLS.md` Pitfall 2 precedent) |
| A caller without `housekeeping.view` reads task data via the merged ops-queue endpoint by holding only `service_requests.view` | Elevation of privilege via a shared endpoint | `OperationsQueueService::index()` already gates each concatenated source behind its own `.view` permission (verified: `if ($user->can('service_requests.view'))` per source) — the housekeeping arm must follow the identical per-source gate, not a single combined check |
| Unauthorized status transition bypassing the transition table via a direct queue-verb call (`/operations/queue/housekeeping-tasks/{uuid}/status`) rather than the dedicated `/housekeeping/tasks/{task}/status` route | Tampering | D-13's delegation pattern (Pattern 3) ensures both write surfaces funnel through the same `UpdateHousekeepingTaskStatusAction`, so the transition table cannot be bypassed by using one route instead of the other |

## Sources

### Primary (HIGH confidence — read in full this session, current repo state)
- `backend/app/Services/Operations/OperationsQueueService.php`, `OperationsQueueController.php`, `UpdateRequestStatusRequest.php`, `OperationsQueueMirror.php`, `MirrorServiceRequestToFirestore.php`, `OperationsQueueItemResource.php`
- `backend/app/Actions/Operations/{AssignRequestAction,UpdateRequestStatusAction,RouteRequestAction}.php`
- `backend/app/Actions/Cms/UpdateRoomStatusAction.php`, `backend/app/Actions/Booking/CheckOutReservationAction.php`, `backend/app/Events/{ReservationCheckedOut,ServiceRequestPlaced}.php`, `backend/app/Listeners/RevokeDigitalKeyOnCheckOut.php`, `backend/app/Support/HotelClock.php`, `backend/config/hotel.php`, `backend/.env.example`
- `backend/app/Models/{ServiceRequest,ServiceBooking,Transfer,Room,Reservation,Ticket,ServiceCategory}.php`, `backend/app/Enums/{RoomStatus,ServiceRequestStatus,ServiceRequestPriority,ServiceBookingStatus,Department,CheckOutMode,ServiceCategoryKind,TicketStatus}.php`
- `backend/database/migrations/{2026_07_12_100005_create_service_requests_table,2026_07_12_100004_create_service_bookings_table,2026_07_12_100003_create_transfers_table,2026_07_09_100002_create_rooms_table,2026_09_26_100100_create_room_status_history_table}.php`
- `backend/database/seeders/{RolesAndPermissionsSeeder,GuestServiceCatalogSeeder}.php`
- `backend/tests/Feature/Operations/OperationsQueueTest.php` (all 15 tests), `backend/tests/Feature/SeederTest.php`, `backend/tests/Feature/Staff/{PermissionsGroupedTest,RolePresetsTest}.php`
- `backend/tests/Concerns/RecordsRowLocks.php`, `backend/tests/Support/FakeFirebaseService.php`
- `backend/app/Base/{BaseFilter,BaseController}.php`, `backend/app/Filters/{ReservationFilter,GuestFilter,TransferFilter}.php`
- `backend/routes/api.php` (lines 570–766), `backend/routes/console.php`, `backend/app/Console/Commands/ExpireDigitalKeys.php`
- `backend/app/Providers/AppServiceProvider.php` (`Relation::morphMap` call site)
- `backend/vendor/laravel/framework/src/Illuminate/Database/{UniqueConstraintViolationException.php,Concerns/ManagesTransactions.php}` (existence/signature verified directly)
- `backend/database/factories/{RoomFactory,ServiceRequestFactory,ServiceBookingFactory,TransferFactory}.php`
- `docs/carlton-tree.html` (lines 245–345, exact node text for `housekeeping`, `departures`, `staff request board`, `quick requests`, `room board · mark clean`)
- `backend/docs/API_GUIDE_DASHBOARD.md` (`## Module: Operations Queue & Dashboard (P10)` section, full module-header list), `backend/docs/postman/carlton-api.postman_collection.json` (top-level folder list), `backend/docs/CHANGELOG_MOBILE_API.md` (header format)
- `.planning/phases/06-housekeeping-guest-services/06-CONTEXT.md` (full, D-01..D-26 + Claude's Discretion + Deferred Ideas)
- `.planning/{REQUIREMENTS.md,ROADMAP.md,STATE.md,config.json}`, `.planning/research/PITFALLS.md`, `.planning/codebase/{TESTING.md,CONVENTIONS.md}`
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md`

### Secondary (MEDIUM confidence)
- None — no web/docs lookups were needed for this phase; every claim was verifiable directly against the codebase.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; every reused class read in full.
- Architecture: HIGH — every pattern cited has a working precedent read in full this session; the one genuinely new pattern (`OperationsQueueType` registry) is clearly flagged as new, not verified-against-precedent.
- Pitfalls: HIGH for A/B/D/E/F (each backed by a specific file read this session); MEDIUM for the permission-count arithmetic (Pitfall C) since it depends on Phase 5's not-yet-landed final state, which this research was explicitly told not to rely on.

**Research date:** 2026-09-26
**Valid until:** Effectively until Phase 5 lands (the permission-count and folio-billing-of-bookings assumptions are time-bound to that event, not a calendar date) — re-verify `RolesAndPermissionsSeeder.php`'s exact state before planning locks in any permission-count assertion.
