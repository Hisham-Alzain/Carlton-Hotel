# Phase 7: Support Tickets & Queue - Pattern Map

**Mapped:** 2026-09-27
**Files analyzed:** ~48 new/modified files
**Analogs found:** 46 / 48 (2 are widenings of code that only exists in Phase 6, no prior analog needed)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/*_add_support_columns_to_tickets_table.php` | migration | CRUD | Phase 6 `*_add_check_out_mode_to_reservations_table.php` (additive columns+FKs) | exact |
| `database/migrations/*_create_ticket_actions_table.php` | migration | event-driven | Phase 6 `*_create_housekeeping_task_status_history_table.php` | exact |
| `database/migrations/*_create_ticket_recoveries_table.php` | migration | CRUD | `2026_09_26_130000_add_ledger_columns_to_folio_items_table.php` (unique FK, DECIMAL money) | role-match |
| `app/Enums/TicketActionType.php`, `TicketRecoveryType.php` | model (enum) | transform | Phase 6 `app/Enums/HousekeepingTaskType.php` | exact |
| `app/Enums/TicketStatus.php` (+transitions, allowedTargets) | model (enum) | transform | Phase 6 `app/Enums/HousekeepingTaskStatus.php` (`canTransitionTo`/`allowedTargets`) | exact |
| `app/Models/TicketAction.php` | model | event-driven | Phase 6 `app/Models/HousekeepingTaskStatusHistory.php` | exact |
| `app/Models/TicketRecovery.php` | model | CRUD | `app/Models/FolioItem.php` (DECIMAL money field, `hasOne` through action) | role-match |
| `app/Models/Ticket.php` (widen, `logExcept`) | model | CRUD | `app/Models/Guest.php` (`getActivitylogOptions logExcept`) + Phase 6 `HousekeepingTask.php` | role-match |
| `app/Actions/Tickets/UpdateTicketStatusAction.php` | service (action) | request-response | Phase 6 `app/Actions/Housekeeping/UpdateHousekeepingTaskStatusAction.php` | exact |
| `app/Actions/Tickets/AssignTicketAction.php` | service (action) | request-response | Phase 6 `app/Actions/Housekeeping/AssignHousekeepingTaskAction.php` | exact |
| `app/Actions/Tickets/CreateTicketAction.php` | service (action) | CRUD | `app/Actions/Booking/RouteRequestAction.php` (dept fallback) + `SettleFolioAction::64` (`ValidationException::withMessages`) | role-match |
| `app/Actions/Tickets/EscalateTicketAction.php` | service (action) | request-response | Phase 6 `UpdateHousekeepingTaskStatusAction.php` (lock→rule→write→history→event shape) | role-match |
| `app/Actions/Tickets/ReplyToTicketAction.php` | service (action) | event-driven | Phase 6 `AssignHousekeepingTaskAction.php` (lock→write+history, no event dispatch) | role-match |
| `app/Actions/Tickets/RecordTicketRecoveryAction.php` | service (action) | CRUD | Phase 6 `CreateHousekeepingTaskAction::ensureOpen` (`UniqueConstraintViolationException` backstop) | role-match |
| `app/Support/AssigneeEligibility.php` | utility | transform | none direct — new cross-cutting helper (RESEARCH.md verbatim code) | no analog |
| `app/Support/ClaimGuard.php` | utility | transform | none direct — new cross-cutting helper (RESEARCH.md verbatim code) | no analog |
| `app/Actions/Operations/AssignRequestAction.php` (widen, `$claim`, SR lock) | service (action) | request-response | itself (current, widened in Phase 6) | exact |
| `app/Actions/Operations/UpdateRequestStatusAction.php` (widen, A2 LogicException) | service (action) | request-response | itself (current, widened in Phase 6) | exact |
| `app/Actions/Housekeeping/AssignHousekeepingTaskAction.php` (add `$claim`, eligibility) | service (action) | request-response | itself (Phase 6) | exact |
| `app/Services/Tickets/TicketService.php` | service | CRUD | Phase 6 `app/Services/Housekeeping/HousekeepingTaskService.php` | exact |
| `app/Services/Operations/OperationsStaffService.php` | service | CRUD (read) | Phase 6 `app/Services/Operations/DepartureServiceService.php` (read-only projection over Spatie scopes) | role-match |
| `app/Services/Operations/OperationsQueueService.php` (widen `claim()`) | service | CRUD | itself (current) | exact |
| `app/Filters/TicketFilter.php` | utility (filter) | transform | Phase 6 `app/Filters/HousekeepingTaskFilter.php` / `ServiceRequestFilter.php` | exact |
| `app/Http/Controllers/Admin/SupportTicketController.php` | controller | request-response | Phase 6 `app/Http/Controllers/Admin/HousekeepingTaskController.php` | exact |
| `app/Http/Controllers/Admin/OperationsStaffController.php` | controller | request-response | Phase 6 `app/Http/Controllers/Admin/ServiceRequestBoardController.php` (read-only slice) | role-match |
| `app/Http/Controllers/Admin/OperationsQueueController.php` (`claim()`) | controller | request-response | itself (current) | exact |
| `app/Http/Requests/Tickets/CreateTicketRequest.php` | middleware (validation) | request-response | Phase 6 `app/Http/Requests/Housekeeping/CreateHousekeepingTaskRequest.php` | exact |
| `app/Http/Requests/Tickets/UpdateTicketStatusRequest.php`, `AssignTicketRequest.php` | middleware (validation) | request-response | Phase 6 `UpdateHousekeepingTaskStatusRequest.php` / `AssignHousekeepingTaskRequest.php` | exact |
| `app/Http/Requests/Tickets/ReplyToTicketRequest.php`, `RecordTicketRecoveryRequest.php`, `EscalateTicketRequest.php` | middleware (validation) | request-response | `app/Http/Requests/Operations/UpdateRequestStatusRequest.php` (BaseRequest + Rule::enum/in) | role-match |
| `app/Http/Requests/Operations/IndexOperationsStaffRequest.php` | middleware (validation) | request-response | `app/Http/Requests/Operations/UpdateDepartureServiceStatusRequest.php` (union/whitelist rule) | role-match |
| `app/Http/Resources/Tickets/TicketResource.php`, `TicketActionResource.php` | model (resource) | transform | Phase 6 `app/Http/Resources/Housekeeping/HousekeepingTaskResource.php` (`whenLoaded`, pre-set relations) | exact |
| `app/Http/Resources/Operations/OperationsStaffResource.php` | model (resource) | transform | Phase 6 `app/Http/Resources/Operations/DepartureServiceResource.php` | role-match |
| `app/Http/Resources/Operations/OperationsQueueItemResource.php` (add `queue_type`) | model (resource) | transform | itself (current) | exact |
| `app/Events/TicketChanged.php` | event | event-driven | Phase 6 `app/Events/HousekeepingTaskChanged.php` | exact |
| `app/Listeners/MirrorTicketToFirestore.php` | event-driven listener | event-driven | Phase 6 `app/Listeners/MirrorHousekeepingTaskToFirestore.php` | exact |
| `app/Exceptions/Ticket*Exception.php` (8 classes) | model (exception) | transform | Phase 6 `app/Exceptions/HousekeepingTaskTransitionException.php` | exact |
| `database/factories/TicketActionFactory.php`, `TicketRecoveryFactory.php` | test | CRUD | Phase 6 `database/factories/HousekeepingTaskFactory.php` | role-match |
| `database/seeders/RolesAndPermissionsSeeder.php` (widen presets) | config | CRUD | itself (current, Phase 6 widened it) | exact |
| `config/hotel.php` (`ticket_max_escalation_level`) | config | transform | itself (`turnover_sla_minutes` from Phase 6) | exact |
| `tests/Feature/Tickets/*Test.php` | test | request-response | Phase 6 `tests/Feature/Housekeeping/*Test.php` | exact |
| `tests/Feature/Operations/QueueClaimTest.php`, `OperationsStaffTest.php`, `OperationsQueueTicketArmTest.php` | test | request-response | Phase 6 `tests/Feature/Operations/OperationsQueueHousekeepingTest.php` | exact |
| `tests/Unit/Tickets/*Test.php`, `tests/Unit/Support/AssigneeEligibilityTest.php` | test | event-driven | Phase 6 `tests/Unit/Housekeeping/*Test.php` (`RecordsRowLocks` usage) | role-match |

## Pattern Assignments

### `app/Actions/Tickets/UpdateTicketStatusAction.php` (service, request-response)
**Analog:** Phase 6 `app/Actions/Housekeeping/UpdateHousekeepingTaskStatusAction.php` (full shape in `06-PATTERNS.md` lines 65-107).
Copy verbatim: `DB::transaction(fn,3)` → lock (tickets is a leaf lock, no room lock) → re-read `$from` → transition check via `allowedTransitions()`/`allowedTargets()` throwing a `Ticket*Exception` with `{from,to,allowed}` → reason-required check via `ValidationException::withMessages` (precedent `SettleFolioAction:64`) → side-effect stamps (`resolved_at`/`closed_at`, self-assign on `→in_progress`) → `TicketAction::create([...type=>STATUS_CHANGE, from_status, to_status, user_id=>actor])` → `TicketChanged::dispatch($locked)` (after-commit). Same shape for `AssignTicketAction` (Phase 6 `AssignHousekeepingTaskAction.php` + `AssigneeEligibility::assert` inserted before the write) and `EscalateTicketAction`.

### `app/Support/AssigneeEligibility.php` / `ClaimGuard.php` (utility, transform)
**No codebase analog** — copy the verbatim code blocks already drafted in `07-RESEARCH.md` ("AssigneeEligibility" and "Pattern 2: Claim guard"). Both are pure static-check classes throwing a domain exception with context; call inside each writer's lock, after the closed check, before the eligibility/no-op check per the documented order.

### `app/Actions/Tickets/RecordTicketRecoveryAction.php` (service, CRUD)
**Analog:** Phase 6 `CreateHousekeepingTaskAction::ensureOpen` unique-violation idiom (`06-PATTERNS.md` lines 112-128) + folio check code in `07-RESEARCH.md` ("Recovery folio check"). Catch `UniqueConstraintViolationException` on the `ticket_recoveries.folio_item_id` unique insert and map to `already_linked`; use `FolioLedger::normalize`/`abs` for amount comparison, never floats.

### `app/Http/Controllers/Admin/SupportTicketController.php` (controller, request-response)
**Analog:** Phase 6 `app/Http/Controllers/Admin/HousekeepingTaskController.php`. Copy constructor-injected service + `success()`/`paginatedSuccess()` envelope; use `success(...,'custom.messages.ticket_created',201)` directly for create/reply (never `respondFromService()` — Pitfall 4, message-swap bug).

### `app/Filters/TicketFilter.php` (utility, transform)
**Analog:** Phase 6 `app/Filters/HousekeepingTaskFilter.php`/`ServiceRequestFilter.php`. Copy `$safeParms` + `applyConditions()` override + private `apply*()` methods for `assignee` (uuid|`unassigned`|`me`, resolved in controller not filter), `escalated` (bool), `priority` (label→scale via `cast()`), `created_at` gte/lte.

### `app/Listeners/MirrorTicketToFirestore.php` / `app/Events/TicketChanged.php`
**Analog:** Phase 6 `MirrorHousekeepingTaskToFirestore.php` / `HousekeepingTaskChanged.php` (`06-PATTERNS.md` lines 178-195). `ShouldDispatchAfterCommit` event; `ShouldQueue` listener using `MirrorsToFirestore` trait; widen `OperationsQueueMirror` for the ticket payload rather than a parallel helper.

### `app/Services/Operations/OperationsStaffService.php` (service, CRUD read)
**Analog:** Phase 6 `DepartureServiceService.php` (read-only projection, `assertCan` + bounded query). Query shape is the verbatim block in `07-RESEARCH.md` ("Staff directory query", Pattern 4) — guard `Role::where('name',$department)->exists()` before `->role()` to avoid `RoleDoesNotExist` (Pitfall 6).

## Shared Patterns

### Single-writer status transition (lock → transition table → write → history → after-commit event)
**Source:** Phase 6 `app/Actions/Cms/UpdateRoomStatusAction.php` / `UpdateHousekeepingTaskStatusAction.php`
**Apply to:** all six `App\Actions\Tickets\*` writers; ticket lock is always a leaf (never held with room/task/SR/folio locks).

### Domain exception with error_code + context
**Source:** Phase 6 `HousekeepingTaskTransitionException`
**Apply to:** the 8 new `Ticket*`/`ServiceRequestClosedException` classes — `(__('custom.errors.X'), [...context])`.

### Additive optional trailing params on widened polymorphic actions
**Source:** `AssignRequestAction`/`UpdateRequestStatusAction` (current, Phase 6-widened)
**Apply to:** the ticket arm delegating to `AssignTicketAction`/`UpdateTicketStatusAction`; SR arm gaining `lockForUpdate()` + `$claim`.

### Filter DSL (BaseFilter + custom apply*())
**Source:** Phase 6 `ReservationFilter.php`/`HousekeepingTaskFilter.php`
**Apply to:** `TicketFilter`.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Support/AssigneeEligibility.php` | utility | transform | First shared eligibility gate across 3 queue types — build from RESEARCH.md verbatim code |
| `app/Support/ClaimGuard.php` | utility | transform | First claim-semantics helper — build from RESEARCH.md verbatim code |

## Metadata

**Analog search scope:** Phase 6 `06-PATTERNS.md` (primary), `backend/app/{Actions,Services,Http,Models,Filters,Listeners,Events,Enums,Support,Exceptions}`, `backend/database/{migrations,seeders,factories}`
**Files scanned:** Phase 6 PATTERNS.md fully; 07-CONTEXT.md and 07-RESEARCH.md code blocks (already verified/read there, not re-read here)
**Pattern extraction date:** 2026-09-27
