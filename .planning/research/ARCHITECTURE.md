# Architecture Research

**Domain:** PMS operational modules on an existing layered Laravel 13 hotel backend
**Researched:** 2026-09-25
**Confidence:** HIGH (based on direct inspection of the existing codebase, not external sources — this is an internal-integration question, not an ecosystem survey)

## Standard Architecture

The target architecture is **not new** — it is the existing layered stack (`Route → Controller → FormRequest → Service/Action → Resource → Model`, documented in `.planning/codebase/ARCHITECTURE.md`) extended with new domain folders. Every module below plugs into that stack unchanged. The only genuinely new mechanism is **event-driven decoupling between domains** (Booking → Housekeeping, Booking → Folio gate), which the codebase already has one working example of (`RoomAssigned` → `SendRoomReadyNotification`).

### System Overview

```
┌───────────────────────────────────────────────────────────────────────────┐
│                    routes/api.php  (new sections per module)               │
│   /front-desk   /housekeeping   /support-tickets   /guests                 │
│   /departure-services   /reports   /folio/*  /night-audit                  │
└───────────────────────────────┬────────────────────────────────────────────┘
                                │
                                ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Controllers (Admin/Api, per new {Domain} folder)                          │
│  - FrontDesk, Housekeeping, SupportTickets, Guests, NightAudit, Reports    │
└───────┬───────────────────────────────────────────────────────┬────────────┘
        │                                                       │
        ▼                                                       ▼
┌───────────────────────────┐                     ┌───────────────────────────┐
│  Services (CRUD, 1/model) │                     │  Actions (verbs, cross-   │
│  RoomBoardService,        │◄────────calls───────┤  domain, transactional)   │
│  HousekeepingTaskService, │                     │  CheckOutReservationAction,│
│  TicketActionService,     │                     │  CreateTurnoverTaskAction, │
│  NightAuditService        │                     │  SettleFolioAction (exist)│
└───────────┬───────────────┘                     └───────────┬───────────────┘
            │                                                  │ dispatches
            ▼                                                  ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Models (new + extended)                                                   │
│  Room(+status), HousekeepingTask*, ReservationNote*, GuestNote*,           │
│  FolioItem(+dispute cols), Ticket(+TicketAction*), EventInquiry(+cols),    │
│  EventInquiryChecklistItem*, TableReservation*, NightAuditRun*             │
│  (* = new table)                                                            │
└───────────────────────────┬─────────────────────────────────────────────────┘
                            │ fires
                            ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Events / Listeners (decoupling seam — app/Events, app/Listeners)          │
│  ReservationCheckedOut → CreateTurnoverHousekeepingTask (sets Room dirty)  │
│  HousekeepingTaskCompleted → (optional) room-ready notification            │
│  TicketEscalated → NotifyDepartmentOfEscalation                            │
└───────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|-------------------------|
| Front Desk domain | Room board (status + today's occupancy), 14-day availability/rate grid, reservation notes, explicit check-in/check-out verbs | `RoomBoardService`, `CheckInReservationAction`, `CheckOutReservationAction` — extends existing `Room`/`Reservation`, no new inventory model |
| Housekeeping domain | Task list/assign/status, links to `Room.status` | `HousekeepingTaskService` + `HousekeepingTask` model (new table), reuses `AssignRequestAction`/`UpdateRequestStatusAction` union pattern |
| Guest directory domain | Profile, notes, preferences, pre-arrival checklist, online check-in | Extends `Guest`/`Reservation`; `GuestNoteService`; small additive columns, no new "guest" concept |
| Folio ledger domain | Line-item post, payment record, disputes (both sides) | Extends existing `Folio`/`FolioItem`/`Payment` — status enum growth, not new tables |
| Support tickets domain | List/create/status/assign/reply/recovery/escalate, queue claim | Extends `Ticket`; new `TicketAction` table for replies/recovery/escalation events; generalizes `OperationsQueueService` |
| Event/venue domain | Inquiry checklist + deposit, table reservations, venue menu download | Extends `EventInquiry`; new `EventInquiryChecklistItem`, `TableReservation` tables |
| Night audit domain | Per-business-date checks/blockers | `NightAuditService` running an array of `NightAuditCheck` evaluator classes (Strategy pattern); persists a `NightAuditRun` record |
| Reports domain | Cross-domain read aggregation | `ReportsService` — pure query layer, zero new tables |

## Recommended Project Structure

```
app/
├── Actions/
│   ├── FrontDesk/
│   │   ├── CheckInReservationAction.php      # explicit verb, was implicit via status
│   │   └── CheckOutReservationAction.php     # gates on Folio::SETTLED, fires ReservationCheckedOut
│   ├── Housekeeping/
│   │   ├── CreateTurnoverTaskAction.php      # listener target, also directly callable
│   │   └── CompleteHousekeepingTaskAction.php# transitions Room.status on completion
│   ├── Guest/
│   │   └── CompleteOnlineCheckInAction.php   # arrival time + digital key + ID scan link
│   ├── Folio/                                 # existing folder, add:
│   │   ├── PostFolioItemAction.php
│   │   ├── RaiseFolioDisputeAction.php
│   │   └── ResolveFolioDisputeAction.php
│   ├── Support/
│   │   ├── ReplyToTicketAction.php           # writes TicketAction
│   │   ├── EscalateTicketAction.php
│   │   └── RecoverTicketAction.php
│   ├── Events/                                # existing folder (event *inquiries*, not app events), add:
│   │   └── SubmitEventChecklistItemAction.php
│   └── NightAudit/
│       └── RunNightAuditAction.php           # runs evaluators, persists NightAuditRun
├── Services/
│   ├── FrontDesk/RoomBoardService.php
│   ├── FrontDesk/AvailabilityGridService.php
│   ├── Housekeeping/HousekeepingTaskService.php
│   ├── Guest/GuestDirectoryService.php
│   ├── Guest/GuestNoteService.php
│   ├── Folio/FolioLedgerService.php          # thin service around FolioItem CRUD
│   ├── Support/TicketActionService.php
│   ├── Events/EventInquiryChecklistService.php
│   ├── Events/TableReservationService.php
│   ├── NightAudit/NightAuditService.php
│   │   └── Checks/                            # evaluator classes, NOT a DB concept
│   │       ├── NightAuditCheck.php            # interface: check(CarbonInterface $businessDate): NightAuditBlocker[]
│   │       ├── UnsettledFolioCheck.php
│   │       ├── OpenHousekeepingTaskCheck.php
│   │       ├── UnresolvedCheckInApprovalCheck.php
│   │       └── OpenTicketCheck.php
│   └── Reports/ReportsService.php
├── Events/
│   ├── ReservationCheckedOut.php             # new — dispatched by CheckOutReservationAction
│   └── HousekeepingTaskCompleted.php          # new
├── Listeners/
│   └── CreateTurnoverHousekeepingTask.php     # ReservationCheckedOut → HousekeepingTask + Room.status=DIRTY
├── Models/
│   ├── HousekeepingTask.php                   # new table
│   ├── ReservationNote.php                    # new table
│   ├── GuestNote.php                          # new table
│   ├── TicketAction.php                       # new table
│   ├── EventInquiryChecklistItem.php          # new table
│   ├── TableReservation.php                   # new table
│   └── NightAuditRun.php                      # new table
```

### Structure Rationale

- New top-level folders (`FrontDesk`, `Housekeeping`, `Support`, `NightAudit`, `Reports`) mirror the existing domain-folder convention (`Booking`, `Cms`, `Folio`) — no role-based subfolders inside Actions/Services, consistent with current codebase rules.
- `NightAudit/Checks/` is the one deliberate deviation: it groups pure-logic evaluator classes under Services because they are stateless strategies invoked by one service, not persisted entities — keeping them under `Actions/` would misrepresent them as transactional writes.
- Controllers follow the existing `Admin/{Domain}` (staff) vs `Api/{Domain}` (guest-facing, e.g. guest folio disputes, guest pre-arrival checklist) split already used for Booking/Folio.

## Architectural Patterns

### Pattern 1: Generalize the polymorphic assign/status union instead of duplicating it

**What:** `AssignRequestAction` and `UpdateRequestStatusAction` currently accept `ServiceRequest|Ticket`. `OperationsQueueService::resolve()`/`requiredPermission()` `match` on a `$type` string (`service-requests`, `tickets`). Add `HousekeepingTask` as a third arm rather than writing `AssignHousekeepingTaskAction`/`HousekeepingTaskStatusAction` from scratch.

**When to use:** Any new work-item type that needs "assign to staff" + "change status" and should appear in the merged operations queue (housekeeping tasks explicitly should, per the requirement "housekeeping tasks... link to room status" and the existing queue precedent).

**Trade-offs:** Keeps one assign/status code path (less drift, one place to add permission checks) but couples the Operations domain to three now four model types via `match`. Acceptable — the codebase already accepted this coupling for two types; the alternative (separate per-type controllers) is proven to duplicate ~40 lines per type with no behavioral difference.

**Example:**
```php
// AssignRequestAction.php
public function handle(ServiceRequest|Ticket|HousekeepingTask $item, User $user): array

// OperationsQueueService::resolve()
'housekeeping-tasks' => HousekeepingTask::where('uuid', $uuid)->firstOrFail(),
```
Do **not** add `EventInquiry`, `TableReservation`, or folio disputes to this union — they don't have a "staff assignment" concept in the requirements; forcing everything through one queue type erodes the pattern's usefulness.

### Pattern 2: Event-driven cross-domain side effects; synchronous guards for cross-domain preconditions

**What:** Two different problems get two different mechanisms, and the requirements ask for both:
1. **Side effect that shouldn't block the triggering action** (check-out should create a housekeeping task) → **event + listener**.
2. **Precondition that must block the triggering action** (check-out must not complete with an unsettled folio) → **synchronous check inside the Action**, same style as `SettleFolioAction`'s `lockForUpdate()` + status guard.

**When to use:** If a failure to run the side effect should not fail the primary operation, use an event. If the primary operation is invalid without the other domain's state, use a direct synchronous check (inject the other domain's Service/Action, or query the model directly under a lock).

**Trade-offs:** Two mechanisms in one Action is more code than "just calling both directly," but conflating them means either (a) checkout fails if the notification listener throws, or (b) checkout silently succeeds with an unsettled folio because nobody checked. The codebase's own `CreateReservationAction` (pessimistic lock, synchronous) vs `RoomAssigned`/`SendRoomReadyNotification` (event, fire-and-forget) is precedent for exactly this split.

**Example:**
```php
// app/Actions/FrontDesk/CheckOutReservationAction.php
public function handle(Reservation $reservation, User $actor): array
{
    return DB::transaction(function () use ($reservation, $actor) {
        $locked = Reservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();

        $folio = $locked->folio()->lockForUpdate()->first();
        if (! $folio || $folio->status !== FolioStatus::SETTLED) {
            throw new FolioNotSettledException(__('custom.errors.folio_not_settled'));
        }

        $locked->update(['status' => ReservationStatus::CHECKED_OUT, 'checked_out_at' => now()]);

        // Fire after commit so the listener never sees an uncommitted checkout row.
        ReservationCheckedOut::dispatch($locked);

        return ['data' => $locked->fresh()->load(['rooms.room', 'folio']), 'code' => 200];
    });
}
```
```php
// app/Events/ReservationCheckedOut.php — implement ShouldDispatchAfterCommit (Laravel 11+)
// app/Listeners/CreateTurnoverHousekeepingTask.php
public function handle(ReservationCheckedOut $event): void
{
    foreach ($event->reservation->rooms as $reservationRoom) {
        HousekeepingTask::create([
            'room_id' => $reservationRoom->room_id,
            'type'    => HousekeepingTaskType::TURNOVER,
            'status'  => HousekeepingTaskStatus::PENDING,
        ]);
        $reservationRoom->room->update(['status' => RoomStatus::DIRTY]);
    }
}
```

### Pattern 3: Night audit as a Strategy/evaluator registry, not a monolithic script

**What:** `NightAuditService::run(CarbonInterface $businessDate)` iterates a config-bound array of classes implementing `NightAuditCheck` (`check(CarbonInterface $businessDate): array<NightAuditBlocker>`), collects blockers, and persists one `NightAuditRun` row per business date with the aggregated result. Each check queries its own domain read-only (`UnsettledFolioCheck` queries `Folio`, `OpenHousekeepingTaskCheck` queries `HousekeepingTask`, etc.) — no cross-check dependencies.

**When to use:** Whenever a "run the day's closing checks" endpoint must span domains that should not import each other (Housekeeping should never `use App\Models\Folio`, and vice versa — the audit service is the only place both are known).

**Trade-offs:** More files (one class per check) than a single `if` cascade, but each check is independently unit-testable and new checks can be added later (e.g., a future "unresolved disputes" check) without touching existing ones. This mirrors the codebase's existing preference for small, single-responsibility classes (Actions, Filters) over large procedural methods.

## Data Flow

### Request Flow (new module, e.g. Housekeeping status update)

```
PATCH /housekeeping/tasks/{uuid}/status
    ↓
HousekeepingTaskController (or reuse OperationsController generalized)
    ↓ FormRequest validates status enum value
UpdateRequestStatusAction::handle(HousekeepingTask $task, string $status)
    ↓ $task->update(['status' => $status]); mirrors to Firestore (existing trait)
    ↓ if $status === COMPLETED → dispatch HousekeepingTaskCompleted
HousekeepingTaskResource → envelope response
```

### Cross-Domain Event Flow (check-out → turnover)

```
Front Desk: CheckOutReservationAction
    ↓ (folio settled gate passes)
    ↓ Reservation.status = CHECKED_OUT
    ↓ dispatch ReservationCheckedOut (after commit)
        ↓
Housekeeping: CreateTurnoverHousekeepingTask listener
    ↓ creates HousekeepingTask (status=PENDING, type=TURNOVER)
    ↓ Room.status = DIRTY
        ↓
Front Desk room board (next read) shows the room as dirty with an open task
```

### Night Audit Read Flow (pure aggregation, no writes to other domains)

```
POST /night-audit/run?business_date=2026-09-25
    ↓
NightAuditService::run()
    ↓ foreach Check in [UnsettledFolioCheck, OpenHousekeepingTaskCheck, ...]
    ↓     $blockers = $check->check($businessDate)   // read-only query into that domain
    ↓ NightAuditRun::create(['business_date' => ..., 'blockers' => $blockers, 'run_by' => $actor->id])
    ↓
Response: { clear: bool, blockers: [...] }
```

## Table-vs-Projection Recommendations

| Concept | Recommendation | Rationale |
|---------|-----------------|-----------|
| Room status (clean/dirty/inspected) | **Extend existing** `rooms.status` column (`RoomStatus` enum, already present) | Column and enum already exist; only need new enum values + a status-change endpoint |
| Room status history | **Projection** — existing `activity_log` via `LogsActivity` trait (already on `Room`) | Every mutation already recorded with old/new values; a bespoke history table would duplicate it |
| Housekeeping tasks | **New table** `housekeeping_tasks` (room_id, type, status, assigned_user_id, notes, uuid) | Decided in PROJECT.md: assignment + timing must persist; not derivable from anything else |
| Departure services list | **Projection** — query over `Reservation` (checking out today) + `ServiceRequest`/folio state | Decided in PROJECT.md: derivable, no new state to own |
| Reservation notes | **New table** `reservation_notes` (reservation_id, author_user_id, body, uuid) | Multi-entry free-text history; a single `notes` column loses authorship/timestamp per entry |
| Guest notes | **New table** `guest_notes` (guest_id, author_user_id, body, uuid) | Same reasoning as reservation notes |
| Guest preferences | **Extend existing** — additive columns on `guests` (or `reservations` if stay-specific, e.g. pillow type, floor preference) | Fixed, small set of known preference fields; a key-value table is only justified if the preference set is open-ended, which nothing in scope suggests |
| Pre-arrival checklist (arrival time, digital key issued) | **Extend existing** — additive nullable columns on `reservations` (`estimated_arrival_at`, `digital_key_issued_at`) | 1:1 with reservation, small fixed field count; promote to a child table only if the checklist grows past ~5 heterogeneous fields |
| Online check-in / ID scan | **Reuse existing** `GuestDocument` upload path (already wired per PROJECT.md) + the same reservation columns above | No new persistence — this is an orchestration action over existing tables |
| Folio line-item disputes | **Extend existing** `folio_items` — add `status` enum (posted/disputed/resolved), `dispute_reason`, `disputed_at`, `resolved_at` | Constraint mandates "status + history over boolean flags"; one active dispute per line item fits a status column, not a new table |
| Support ticket replies / recovery / escalation | **New table** `ticket_actions` (ticket_id, actor_user_id, action_type, body, uuid) — already decided in PROJECT.md | Audit trail of heterogeneous staff actions on a ticket; `Message` mirroring explicitly deferred |
| Ticket escalation state | **Extend existing** — add `ESCALATED` to `TicketStatus` enum (or an `escalated_at` column) + a `ticket_actions` row for the event | Avoids a parallel escalation table for what is fundamentally a status transition |
| Event inquiry checklist | **New table** `event_inquiry_checklist_items` (event_inquiry_id, label, is_done, done_at) | Checklist items are enumerable, orderable, independently completable — matches `FolioItem`'s existing precedent for line-item children, not a JSON blob (no JSON columns used elsewhere in this codebase) |
| Event inquiry deposit | **Extend existing** — add `deposit_amount_usd` (DECIMAL), `deposit_paid_at` columns to `event_inquiries` | Single scalar + timestamp, no history requirement stated |
| Dining table reservations | **New table** `table_reservations` (restaurant_table_id, guest_id or reservation_id, party_size, reserved_for, status, uuid) | `RestaurantTable` already exists as inventory; reservations against it are new stateful entities, not derivable |
| Night audit runs | **New table** `night_audit_runs` (business_date, run_by_user_id, blockers JSON-serialized as child rows or a summary count, completed_at, uuid) | A persisted record of "audit ran, here's what blocked it" is needed for reports/history; the evaluators themselves are stateless code, not data |
| Reports dashboard | **Pure projection** — no new tables; `ReportsService` aggregates over existing + above tables (occupancy, revenue, ticket resolution time, night-audit pass rate) | Reports must reflect current source-of-truth tables live; caching (if needed) is an infra concern, not a schema one |

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|---------------------------|
| Current (single property, staff-scale usage) | Everything above is sufficient as designed — synchronous events, in-request DB queries for reports |
| Growth to multi-property | `HousekeepingTask`, `Room`, `NightAuditRun` all need a `property_id` foreign key added additively; the `OperationsQueueService::MERGE_FETCH_LIMIT` (500) pattern already anticipates the union-query cap needed when a fourth type (housekeeping) is added — revisit the limit, not the pattern |
| High reporting load | Move `ReportsService` aggregation queries behind a cache (Redis, keyed by business date) rather than adding summary tables — the codebase has no precedent for materialized reporting tables, and additive-migration-only rules make schema-based pre-aggregation costly to evolve |

### Scaling Priorities

1. **First bottleneck:** `OperationsQueueService`'s in-memory merge-then-sort-then-paginate (documented as a known concern in PROJECT.md/CONCERNS.md) gets worse with a third/fourth unioned type (housekeeping, tickets). Fix: keep `MERGE_FETCH_LIMIT` per type but do not add more types to the merge than strictly required by "queue items can be claimed" — housekeeping tasks and tickets qualify; folio disputes and event inquiries do not need to appear in this queue.
2. **Second bottleneck:** Night audit evaluators doing full-table scans per business date. Fix: every evaluator query must filter by an indexed date/status column (e.g., `Folio::where('status', '!=', SETTLED)->whereHas('reservation', fn ($q) => $q->whereDate('check_out', $businessDate))`) — never a full unfiltered scan.

## Anti-Patterns

### Anti-Pattern 1: Adding a `housekeeping_status` column to `Room` separate from the existing `status`

**What people do:** Bolt on a second status column because "housekeeping status is different from occupancy status."
**Why it's wrong:** `Room.status` is already typed as `RoomStatus` and used by availability/assignment logic; a second parallel status column creates two sources of truth for "is this room usable" and every read site has to reconcile both.
**Do this instead:** Extend the `RoomStatus` enum with housekeeping-relevant values (`CLEAN`, `DIRTY`, `INSPECTED`, `OUT_OF_ORDER` alongside whatever occupancy values already exist) and let `HousekeepingTask` be the workflow that transitions this single column.

### Anti-Pattern 2: Booking domain directly creating `HousekeepingTask` rows

**What people do:** Inside `CheckOutReservationAction`, call `HousekeepingTask::create([...])` directly because "it's simpler than an event."
**Why it's wrong:** Couples the Booking/FrontDesk domain to Housekeeping's schema; every future housekeeping schema change now risks breaking checkout, and the reverse (Housekeeping needing to know when checkout happens) has no clean seam. It also violates the codebase's existing pattern of `Actions never importing sibling-domain Models directly except through Services/events` (see `RoomAssigned` precedent).
**Do this instead:** Dispatch `ReservationCheckedOut` and let a `Listeners\CreateTurnoverHousekeepingTask` in the Housekeeping domain react. Booking never imports `HousekeepingTask`.

### Anti-Pattern 3: Night audit checks writing data instead of only reading

**What people do:** A `NightAuditCheck` implementation "helpfully" auto-settles a folio or auto-completes a stale housekeeping task while computing whether it's a blocker.
**Why it's wrong:** Night audit's job is to *report* blockers for a human to resolve, not to silently mutate other domains' state during a read operation; this also breaks the "transactions wrap only multi-step writes" convention since a read endpoint would suddenly need write locks.
**Do this instead:** Checks only read and return `NightAuditBlocker` value objects; resolution happens through the normal domain actions (`SettleFolioAction`, `CompleteHousekeepingTaskAction`) invoked separately by staff.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|----------------------|-------|
| Firestore mirror (`MirrorsToFirestore` trait) | Already used by `AssignRequestAction`/`UpdateRequestStatusAction` for the ops queue | Extend the same trait usage when generalizing to `HousekeepingTask`; do not build a second mirroring mechanism |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|----------------|-------|
| FrontDesk ↔ Housekeeping | Laravel event (`ReservationCheckedOut`) → listener | Decouples checkout from turnover task creation; Housekeeping never imports `Reservation` |
| FrontDesk ↔ Folio | Direct synchronous call/query inside `CheckOutReservationAction` (precondition, not side effect) | Must block, not fire-and-forget; matches `SettleFolioAction`'s own lock-and-check style |
| Housekeeping ↔ Operations Queue | Shared union type in `AssignRequestAction`/`UpdateRequestStatusAction` + `match` arm in `OperationsQueueService` | Reuses existing generalized queue rather than a parallel housekeeping-specific queue |
| Support Tickets ↔ Operations Queue | Already integrated (`Ticket` is one of the two existing union members) | `ticket_actions` (reply/recovery/escalate) is additive and does not change the queue integration |
| Night Audit ↔ everything | Read-only queries per evaluator class, never a domain import cycle | Only `NightAuditService`/`Checks/*` are allowed to know about multiple domains at once |
| Reports ↔ everything | Read-only aggregation queries in `ReportsService` | Same isolation rule as Night Audit — reports never write |

## Suggested Build Order

Dependency-driven, not requirement-list order:

1. **Front Desk foundation** — room board, `RoomStatus` enum extension, availability/rate grid, reservation notes, explicit `CheckInReservationAction`/`CheckOutReservationAction` (the latter includes the Folio-settled gate and dispatches `ReservationCheckedOut`). *Everything else depends on this existing.*
2. **Housekeeping** — `HousekeepingTask` model/table, generalize `AssignRequestAction`/`UpdateRequestStatusAction`/`OperationsQueueService` to a third type, wire the `CreateTurnoverHousekeepingTask` listener to step 1's event. *Depends on step 1's event existing; can start schema work in parallel with step 1.*
3. **Folio ledger extensions** — post line items, record payments, disputes (both staff and guest side). *Depends on step 1 only for the checkout gate to have something real to check; otherwise independent and can run in parallel with step 2.*
4. **Guest directory & profiles** — notes, preferences, pre-arrival checklist, online check-in, ID scan wiring. *Depends on step 1's `CheckInReservationAction` for the online-check-in flow; otherwise independent, can run in parallel with steps 2–3.*
5. **Support tickets full lifecycle** — `ticket_actions`, assign/reply/recovery/escalate, queue claim. *Depends on step 2 having already generalized `OperationsQueueService`'s union pattern (reuse, don't re-derive it) — schedule after step 2 lands, or coordinate if run in parallel.*
6. **Event/venue extensions** — inquiry checklist, deposit, table reservations, venue menu download. *No dependency on steps 1–5; lowest risk, can be scheduled as filler at any point, including first if sequencing flexibility is needed.*
7. **Night audit** — evaluator registry over Folio (step 3), Housekeeping (step 2), check-in/out state (step 1), tickets (step 5). *Must be last among the domains it inspects — building it earlier means writing checks against modules that don't exist yet.*
8. **Reports dashboard** — cross-domain aggregation, including night-audit pass history. *Build last; every earlier module adds a metric worth reporting on, and building it first means immediately revisiting it after each subsequent phase.*

## Sources

- Direct inspection: `backend/app/Actions/Operations/*`, `backend/app/Services/Operations/OperationsQueueService.php`, `backend/app/Models/{Room,Ticket,Folio,Reservation,ServiceRequest,Guest,ReservationRoom}.php`, `backend/app/Actions/Booking/CreateReservationAction.php`, `backend/app/Actions/Folio/SettleFolioAction.php`, `backend/app/Events/*`, `backend/app/Listeners/*` — confidence HIGH (primary source, current repo state, 2026-09-25)
- `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/STRUCTURE.md`, `.planning/codebase/CONVENTIONS.md` — confidence HIGH (project-generated codebase maps, same date)
- `.planning/PROJECT.md` — confidence HIGH (project's own decision log, e.g. housekeeping-as-table, departures-as-projection, ticket_actions decision)

---
*Architecture research for: PMS operational module integration into existing Laravel backend*
*Researched: 2026-09-25*
