# Phase 7: Support Tickets & Queue - Research

**Researched:** 2026-09-27
**Domain:** Laravel 13 domain workflow (ticket lifecycle, append-only timeline, row-locked single writers, polymorphic queue claim, Spatie permission queries)
**Confidence:** HIGH (every integration point read in code at commit `a17c293`; no new packages)

## Summary

Phase 7 is pure in-repo work on an established stack: no new packages, three additive migrations, eight new domain exceptions, six ticket single writers, one claim verb spanning the three `OperationsQueueType` registry types, and one read-only staff directory. Every pattern it needs already exists in Phase 5/6 code: the housekeeping single writers (`AssignHousekeepingTaskAction`, `UpdateHousekeepingTaskStatusAction`) are the template for lock → re-read → write row + history → dispatch after-commit event; `HousekeepingTaskChanged` + `MirrorHousekeepingTaskToFirestore` are the template for `TicketChanged` + `MirrorTicketToFirestore`; `CreateHousekeepingTaskAction` shows the `UniqueConstraintViolationException` backstop; `SettleFolioAction` shows action-level `ValidationException::withMessages` and extra result keys (`payment_recorded`) for controller message selection.

The decisions in 07-CONTEXT.md are implementable as written, but code inspection surfaced **five factual corrections the planner must carry**: (1) the permission catalogue is **26 permissions / 11 groups**, not 24 (D-10 text; `SeederTest::test_all_26_permissions_seeded` pins 26); (2) council A3's example "reception holds only `service_requests.view`" is stale — the Phase 6 post-build ruling gave reception `service_requests.update`, so reception stays assignable to service requests but becomes **un-assignable to housekeeping tasks** (it holds `housekeeping.assign`, not `housekeeping.update`); (3) the A4 blast radius misses a *write*: `tickets.respond` gates `POST /cms/conversations/{conversation}/messages`, so reception/concierge gain **the ability to send chat messages to guests**, plus ticket rows in `/operations/queue` and `tickets`/`event_inquiries` blocks in `/dashboard/summary`; (4) D-09 eligibility breaks **~12 existing tests** that assign a bare `User::factory()->create()` — they must be re-pinned in the same plan that lands `AssigneeEligibility`; (5) the D-13 query budgets (list ≤ 6, show ≤ 6) are **not reachable with naive `$with` eager loading** (7 and 10 queries) — a single users preload is required.

**Primary recommendation:** Build tickets bottom-up (schema + enums + exceptions → single writers with unit tests → HTTP surface → queue delegation/claim/staff list → docs), keep the `tickets` row lock a *leaf* lock (never held while acquiring a room/task/request/folio lock), and land `AssigneeEligibility` together with the re-pins of every existing assign test.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Ticket list/show/filters | API / Backend (`TicketService` + `TicketFilter`) | Database (indexes `source`, `(status,priority)`, `created_at`) | Read path, paginated via `paginatedSuccess`; `assignee=me` resolved in controller (filters never see auth) |
| Ticket lifecycle (create/status/assign/escalate/reply/recovery) | API / Backend (6 Actions under `App\Actions\Tickets`) | Database (`ticket_actions`, `ticket_recoveries`, row lock) | Single writers own transitions, locking, timeline, event dispatch |
| Transition table | Enum (`TicketStatus::allowedTransitions()`) | — | Pure data; shared by the ticket route and the queue ticket arm |
| Assignee eligibility | API / Backend (`App\Support\AssigneeEligibility`) | Spatie permission cache | Called inside each assign writer (ticket, SR arm, HK) |
| Claim | API / Backend (`OperationsQueueService::claim` → per-type assign writer + `ClaimGuard`) | Database (row lock per type) | Guard must run inside the writer's lock |
| Staff directory | API / Backend (`OperationsStaffService`) | Spatie roles/permissions | Read-only, unpaginated, capped 200 |
| Live queue signal | Queue worker (`MirrorTicketToFirestore`) | Firestore `ops_queue/ticket_{uuid}` | After-commit event; best effort |
| Folio credit money | Phase 5 folio endpoint (unchanged) | — | Tickets only **link** a credit (`folio_item_uuid`); never post |

<user_constraints>
## User Constraints (from CONTEXT.md)

> **Source of truth:** `.planning/phases/07-support-tickets-queue/07-CONTEXT.md` (46 KB). The locked decisions are too long to duplicate usefully; below is a faithful index so the planner can trace every task to a decision ID. **On any wording difference, CONTEXT.md wins.** Council amendments A1–A10 override the consultant text wherever they touch it.

### Locked Decisions (index — read CONTEXT.md for full text)

- **Gate (A10):** research/planning only after Phase 6 is committed with SUMMARY and the full suite green. *Status: MET. Phase 6 is committed (`d8ac795` feature, `613a980` 405 fix, `a17c293` close), and the suite is green at 1709/1709 (run this session).*
- **D-01** `ticket_actions` append-only timeline: `id, uuid, ticket_id (cascade), user_id (nullable, restrictOnDelete — A6), type string(20) TicketActionType{CREATED,STATUS_CHANGE,ASSIGNMENT,ESCALATION,REPLY,RECOVERY}, body text, from_status/to_status string(20), target_user_id (nullable, restrictOnDelete — A6), message_id (FK messages nullable nullOnDelete, never written/exposed), meta json (per-type whitelisted keys — A8), created_at only`. Indexes `(ticket_id, created_at)`, `user_id`, `target_user_id`, `type`. No LogsActivity. Timeline canonical over ticket columns for history (A8).
- **D-02** `ticket_recoveries`: `id, uuid, ticket_action_id FK unique cascade, type string(30), amount_usd DECIMAL(10,2) nullable, description string(1000), folio_item_id FK nullable unique nullOnDelete, created_at`. No ticket_id/recorded_by (3NF). `Ticket::recoveries()` hasManyThrough, `TicketAction::recovery()` hasOne. `amount_usd` documented as a snapshot (A8).
- **D-03** additive `tickets` columns: `description text, reservation_id FK nullOnDelete idx, room_id FK nullOnDelete idx, created_by FK users nullOnDelete idx, resolved_at, closed_at, escalation_level unsignedTinyInteger default 0`; indexes `source`, `(status, priority)`, `created_at`. `Ticket` keeps LogsActivity with `logExcept(['description'])` (A6). No escalated_at/escalated_to/escalation_reason columns.
- **D-04** `TicketSource::STAFF` only; create forces `source=staff`; DB default `chatbot` unchanged.
- **D-05** Create links: optional `guest_uuid`, `reservation_uuid`, `room_uuid`; guest derived from reservation; mismatch → 422 `validation_failed` on `guest_uuid`; room independent; `conversation_id` never set by staff, exposed as `conversation_uuid`. Queue ticket `room_number` = `ticket.room?.number`, `room` eager-loaded.
- **D-06** `TicketStatus` += `IN_PROGRESS`, `WAITING_GUEST`; `active()` = open, assigned, in_progress, waiting_guest. Transition table in `allowedTransitions()`; `assigned` system-managed (status PATCH → 422 `ticket_transition_invalid`); reason required for → closed from non-resolved and for reopen resolved → in_progress (422 `validation_failed`, raised in action); → in_progress on unassigned self-assigns; → resolved stamps `resolved_at`; reopen clears it; → closed stamps `closed_at`.
- **D-07** Single writers `CreateTicketAction, UpdateTicketStatusAction, AssignTicketAction, EscalateTicketAction, ReplyToTicketAction, RecordTicketRecoveryAction`: `DB::transaction(fn, 3)`, `Ticket::whereKey()->lockForUpdate()`, re-read, write row + action. Queue ticket arms of `UpdateRequestStatusAction`/`AssignRequestAction` **delegate**. **A2:** ticket arm requires non-null `$actor`, else `LogicException`.
- **D-08** Assign `{user_uuid}`: open → assigned + assignment action; assigned/in_progress/waiting_guest swap assignee (action, status unchanged); resolved/closed → 422 `ticket_closed {status}`; same assignee → 200 no-op, no row. No unassign.
- **D-09** `AssigneeEligibility::assert(User $target, string $workPermission)`: `is_active`, `type ∈ {staff, super_admin}`, `can($workPermission)`; else 422 `assignee_not_eligible {user_uuid, required_permission}`. Work permission = registry status permission. Applied in AssignTicketAction, EscalateTicketAction, SR arm of AssignRequestAction, AssignHousekeepingTaskAction. **A3:** handoff lists un-assignable role/queue-type pairs concretely.
- **D-10** No new permission strings. Gates: view → `tickets.view`; create/status/reply/recovery/escalate → `tickets.respond`; assign → `tickets.assign`; claim → type work permission. Presets: reception += `tickets.view, tickets.respond`; concierge += `tickets.view, tickets.assign, tickets.respond`; events, kitchen, housekeeping unchanged. **A4:** document full blast radius (conversations, event-inquiries), pin in `RolePresetsTest`, PROJECT.md debt entry.
- **D-11** Department = body ?? `category->department()` ?? CONCIERGE via shared pure static helper.
- **D-12** Priority labels `low|normal|high` ↔ 1/2/3 (add `ServiceRequestPriority::toTicketScale()`); resources expose label only; no `critical`.
- **D-13** Routes `GET|POST /support-tickets`, `GET /support-tickets/{ticket}`; `TicketFilter` (status, department, source, category eq/in; priority label eq/in; assignee uuid|unassigned|me; guest uuid; reservation uuid; escalated bool; created_at gte/lte; sort created_at, updated_at, priority, status; default created_at desc, id desc; includes resolved/closed). Create body `{subject 3-150, description? ≤5000, category, priority?, department?, guest_uuid?, reservation_uuid?, room_uuid?}` → 201 + `created` action. `TicketResource` / `TicketActionResource` field lists; **A7:** `folio_credit_total_usd` + `recorded_value_usd` (string 2dp). Budgets list ≤ 6, show ≤ 6 via `expectsDatabaseQueryCount`.
- **D-14** `TicketRecoveryType`: folio_credit, rate_discount, courtesy_amenity, room_upgrade, late_checkout, apology, other.
- **D-15** Recovery record-only. `folio_credit` requires `folio_item_uuid`, amount copied from `abs(folio_item.amount_usd)`, body amount must not differ; item must be `source_type=credit`, on the ticket's reservation folio (or guest's folio when no reservation), not linked. 422 `ticket_recovery_folio_invalid {folio_item_uuid, reason: not_credit|other_stay|already_linked|no_stay (A7)}`; unique index backstop mapped. Other types: optional amount, `folio_item_uuid` prohibited. Any status except closed. No delete route.
- **D-16** `POST …/reply {body 1-5000}` → `reply` action, internal only, no status change, any status except closed, 201 refreshed ticket with actions.
- **D-17** No reply routing to chat; expose `conversation_uuid`.
- **D-18** `POST …/escalate {user_uuid, reason 3-1000}`: eligibility with `tickets.respond`; assignee := target, `escalation_level++`, open → assigned, one escalation action (`target_user_id`, body=reason, meta `{level, previous_assignee_uuid}`); resolved/closed → `ticket_closed`; body `level` ignored.
- **D-19** Loop guards: self → `ticket_escalation_invalid {reason: self}`; same assignee → `{reason: same_assignee}`; cap `config('hotel.ticket_max_escalation_level', 3)` → `ticket_escalation_limit {level, max}`. No scheduled escalation.
- **D-20** No staff notifications.
- **D-21** `TicketChanged` (`ShouldDispatchAfterCommit`) from create/status/assign/claim/escalate; queued `MirrorTicketToFirestore` writes `ops_queue/ticket_{uuid}` via `OperationsQueueMirror`; reply/recovery do not dispatch; queue ticket arm stops inline mirroring.
- **D-22** `PATCH /operations/queue/{type}/{uuid}/claim` (no body), all three types; permission = type work permission (403 in-service); under the writer's lock: unassigned → assign to actor via the type's assign writer (ticket open → assigned + `meta.claim=true`; HK pending → assigned + history; SR status unchanged); self → 200 no-op, message `queue_item_already_yours`; other → 409 `queue_item_already_claimed {assigned_user_uuid}`; terminal → **type's own code (A1)**. Writers gain `bool $claim = false`; SR arm gains `lockForUpdate()`. **A1:** no `queue_item_closed`; SR gets `service_request_closed` if no existing SR closed exception (verified: none exists → add it). **A8:** per-type adapters / `ClaimGuard`. **A9:** SQLite lock caveat documented.
- **D-23** `GET /operations/staff`: gate = queue index gate; `type` (segment) / `permission` (**A5:** only the 3 work permissions, else 422) / `department` (Department enum) / `search` (name like); rows `is_active` + type staff|super_admin; super admins match permission/type filters, excluded by department unless holding the role; department = role name; sales/maintenance empty; response `data.items` (cap 200, name asc) + `meta {count, truncated}`; row `{uuid, name, type, departments[]}` (**A5:** no `roles[]`); ≤ 4 queries; no alias route.
- **D-24** Every queue row gains `queue_type` (URL segment) from the registry; `type` unchanged; ticket `allowed_statuses` = `allowedTransitions()` minus `assigned`; no `{id}` aliases; queue ticket eager load adds `room`.
- **D-25** New codes: `ticket_transition_invalid` 422 `{from,to,allowed}`, `ticket_closed` 422 `{status}`, `ticket_escalation_invalid` 422 `{reason}`, `ticket_escalation_limit` 422 `{level,max}`, `ticket_recovery_folio_invalid` 422 `{folio_item_uuid,reason}`, `assignee_not_eligible` 422 `{user_uuid,required_permission}`, `queue_item_already_claimed` 409 `{assigned_user_uuid}`; `queue_item_closed` **removed (A1)**; plus `service_request_closed` 422 (A1). Message keys for created/status/assigned/replied/recovery/escalated/claimed/already-yours.
- **D-26** Docs: dashboard guide Support Tickets module + Operations Queue edits (claim per-type codes A1, `queue_type`, transitions, eligibility + A3 table, `/operations/staff` A5 shape, summary statuses, A9 caveat), permission catalogue (A4 exposure), mobile guide/changelog untouched, Postman folder, tree flips, dashboard handoff renames, SUMMARY deploy notes.
- **D-27** Tests: listed files under `tests/Feature/Tickets/` and `tests/Feature/Operations/` incl. `DeactivatedTokenClaimTest`; unit tests for `TicketStatus`, `UpdateTicketStatusAction`, `AssigneeEligibility`; re-pins of `OperationsQueueTest`, `RolePresetsTest` (A4 pins), `SeederTest`, `PermissionGuideAccuracyTest`; `TicketChanged` mirror fake assertion; happy/401/403/422 per route; suite green.

### Claude's Discretion (verbatim from CONTEXT.md)

- Class, file and method names (`App\Actions\Tickets\*`, `App\Services\Tickets\TicketService`, `TicketFilter`, `Admin\SupportTicketController`, `Admin\OperationsStaffController`, requests under `Http/Requests/Tickets/`), factories (`TicketActionFactory`, `TicketRecoveryFactory`), and resource key order.
- Where the `$claim` guard lives: a parameter on each writer, or a small `ClaimGuard` the writers call (council A8 requires the adapter shape conceptually; exact class layout is discretionary). It must run inside the writer's lock either way.
- The name of the category→department helper. Whether `created`/`status_change` rows store the reason in `body` or `meta`: pick one and document it.
- Cap on `actions[]` in show (all, or the last 200 with a `meta.actions_truncated` flag).
- Lang wording, Postman layout, and the `DemoShowcaseSeeder` additions (staff tickets, a sample timeline).

### Deferred Ideas (OUT OF SCOPE — verbatim from CONTEXT.md)

- TICKET-08 guest-visible replies (`message_id` mirroring). `guest_app` ticket source and guest ticket creation.
- Staff notifications (in-app or FCM) on escalation or assignment. SLA/due-at and auto-escalation jobs. `critical` priority.
- An unassign verb. Assign-at-create. A `users.department` column. Workload counts (`open_items_count`) on the staff list.
- Recovery that posts money directly (a single call with an Idempotency-Key). Recovery totals in reports (Phase 9 may read `ticket_recoveries`).
- Reopening `closed` tickets. Ticket merge/duplicate linking. Attachments.
- RT-01 websocket queue. SQL UNION queue.
- Per-request `EnsureUserIsActive` middleware (council Skeptic dissent, rejected — deactivation already revokes tokens; covered by the D-27 regression test instead).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| TICKET-01 | List/view tickets with filters | `TicketFilter` modelled on `HousekeepingTaskFilter` (custom `applyAssignee`, priority label cast, sort override); users preload to meet ≤ 6 queries (Pitfall 3); show loads actions + recoveries in bounded queries |
| TICKET-02 | Create ticket (source `staff`) | `CreateTicketAction` (no lock needed, new row) + department helper extracted from `RouteRequestAction:17-19`; guest/reservation mismatch raised as `ValidationException::withMessages` (precedent `SettleFolioAction:64`); 201 via `success()` not `respondFromService()` (Pitfall 4) |
| TICKET-03 | Status with valid transition + recorded action | `TicketStatus::allowedTransitions()`; redefine `allowedTargets()` (the registry calls it for `allowed_statuses`); `UpdateTicketStatusAction` mirrors `UpdateHousekeepingTaskStatusAction` shape |
| TICKET-04 | Assign ticket | `AssignTicketAction` mirrors `AssignHousekeepingTaskAction`; `AssigneeEligibility` with `tickets.respond` |
| TICKET-05 | Reply stored as ticket action | `ReplyToTicketAction`; `message_id` never written; no `TicketChanged` |
| TICKET-06 | Service-recovery action | `RecordTicketRecoveryAction`; folio link validation against `folios.reservation_id` (unique, one folio per reservation) and `FolioItemSource::CREDIT`; credits stored negative (`PostFolioItemAction:95` `bcsub('0', …)`) → store `abs` via `FolioLedger`; `UniqueConstraintViolationException` backstop (precedent `CreateHousekeepingTaskAction:81`) |
| TICKET-07 | Escalate with reason, timestamped | `EscalateTicketAction`; timestamp = action `created_at`; cap from `config/hotel.php` |
| OPS-01 | Claim a queue item | `OperationsQueueService::claim()` + `bool $claim` on three assign writers + `ClaimGuard`; SR arm gains lock + `ServiceRequestClosedException` |
| OPS-02 | Assignable staff list | Spatie `scopePermission` / `scopeRole` (v8.3.0) with super-admin `orWhere`; `scopeRole` throws for non-existent roles (Pitfall 6) |
| OPS-03 | `queue_type` on queue rows | `OperationsQueueItemResource` adds `'queue_type' => $type->segment`; ticket `roomNumber()` arm + `room` eager load in `baseQuery()` |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- PHP 8.3 language level (local CLI is 8.4.1 — do not use 8.4-only syntax/APIs such as property hooks or new bcmath object API); Laravel 13 (13.19.0 installed); PHPUnit 12; SQLite in-memory tests; MySQL prod. No new frameworks.
- TupCode guide §1–§17 is a hard gate (`backend/.claude/skills/tupcode-laravel-backend/`); load the skill before writing backend code; run the §17 PR checklist.
- Base* classes; services/actions return `['data','code']` (extra keys allowed, precedent `payment_recorded`); domain exceptions extending `App\Exceptions\DomainException` with `errorCode()`/`statusCode()`; never return error arrays.
- Controllers never query the DB; services never read `request()`; resources never query; filters never apply business logic; request object never passed to services/actions.
- Every user-facing string `__('custom.key')` in all locales present in `lang/` (**five: ar, en, es, fr, tr**).
- UUID public ids, `/api/v1` versioning, routes grouped by role/permission middleware; role checks in middleware, ownership in `authorize()`.
- `error_code` values and field names are contracts: additive only; breaking changes need sign-off + client note.
- Additive migrations only; 3NF; DECIMAL money (USD); FKs indexed with explicit ON DELETE; status + history over booleans.
- Every new route: happy / 401 / 403 / 422 tests; non-trivial actions unit-tested; full suite green before commit.
- Multi-step writes in `DB::transaction()` inside service/action; `lockForUpdate()` for contended rows; eager loads declared in `$with`, resources use `whenLoaded()`.
- Git: commit after the phase, never push. (This research does not run git beyond read-only log.)

## Standard Stack

### Core (all already installed — no installs this phase)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | 13.19.0 `[VERIFIED: php artisan --version]` | Routing, Eloquent, transactions, events | Project framework |
| spatie/laravel-permission | 8.3.0 `[VERIFIED: composer.lock]` | `can()`, `scopePermission`, `scopeRole`, role presets | Existing RBAC |
| spatie/laravel-activitylog | v5 (`Spatie\Activitylog\Support\LogOptions`) `[VERIFIED: app/Traits/LogsActivity.php]` | `logExcept(['description'])` on Ticket | Existing audit trail; precedent `Guest::getActivitylogOptions()` |
| phpunit/phpunit | 12.x `[CITED: CLAUDE.md]` | Tests | Project test runner |

### Supporting (in-repo)
| Asset | Location | Use |
|-------|----------|-----|
| `OperationsQueueType` registry | `app/Support/OperationsQueueType.php` | `segment`, `statusPermission` (= work permission), `openStatuses()`, `allowedStatuses()`, `roomNumber()`, `baseQuery()` |
| `OperationsQueueMirror` | `app/Support/OperationsQueueMirror.php` | Ticket mirror doc id/payload (shape unchanged) |
| `MirrorsToFirestore` trait | `app/Traits/` | Best-effort Firestore write |
| `FolioLedger` | `app/Support/FolioLedger.php` | `normalize()`, `fromNumeric()`, `sum()` for `abs` and 2dp strings (no floats) |
| `RecordsRowLocks` | `tests/Concerns/RecordsRowLocks.php` | `assertLocksRow('tickets', fn)` — proves `for update` SQL only |
| `FakeFirebaseService` | `tests/Support/FakeFirebaseService.php` | `$fake->mirrors` assertions |
| `BaseFilter` | `app/Base/BaseFilter.php` | `$safeParms`, `$sortable`, `applyConditions`, `cast`, `castBool`, `reject` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `lockForUpdate` + check (locked) | Conditional `UPDATE … WHERE assigned_user_id IS NULL` | Rejected by D-22: the check must share the writer's lock and side effects |
| Spatie `permission()` scope | Manual joins on `model_has_permissions`/`role_has_permissions` | Scope already handles direct + role permissions; manual joins duplicate Spatie internals |

**Installation:** none.

## Package Legitimacy Audit

No external packages are installed by this phase. **Packages removed:** none. **Packages flagged:** none.

## Architecture Patterns

### System Architecture Diagram

```
Dashboard (React)
   │
   ├─ /support-tickets[...] ──► SupportTicketController ──► FormRequest (validate)
   │                                   │
   │                                   ├─ reads ─► TicketService (index/show, TicketFilter, users preload)
   │                                   └─ writes ► Tickets\*Action ──┐
   │                                                                  │  DB::transaction(fn,3)
   ├─ /operations/queue/{type}/{uuid}/status|assign ──┐               │  lock tickets row (leaf lock)
   │                                                  ▼               │  re-read status → rule check
   │                                 OperationsQueueService           │  write tickets + ticket_actions
   │                                   (assertCan per registry)       │  (+ ticket_recoveries)
   │                                          │                       │  TicketChanged::dispatch (after commit)
   │                                          ▼                       ▼
   │                          AssignRequestAction / UpdateRequestStatusAction
   │                            ├─ ticket arm ──(actor required, A2)──► AssignTicketAction / UpdateTicketStatusAction
   │                            ├─ HK arm ─────► AssignHousekeepingTaskAction (room → task lock)
   │                            └─ SR arm ─────► lock SR row → ClaimGuard/closed check → update → afterCommit mirror
   │
   ├─ /operations/queue/{type}/{uuid}/claim ──► OperationsQueueService::claim
   │        assertCan(type.statusPermission) → resolve → AssignRequestAction(item, actor, actor, claim:true)
   │        ClaimGuard inside the writer lock: closed → type code; self → no-op 200; other → 409
   │
   └─ /operations/staff ──► OperationsStaffService (active staff|super_admin, permission/role scopes, cap 200)

TicketChanged ──(queue worker)──► MirrorTicketToFirestore ──► Firestore ops_queue/ticket_{uuid}
Folio credit: Dashboard → POST /cms/folios/{folio}/line-items (Phase 5, folios.post, Idempotency-Key)
              then → POST /support-tickets/{ticket}/recovery-actions {type: folio_credit, folio_item_uuid}  (link only)
```

### Recommended Project Structure
```
app/
├── Actions/Tickets/            # CreateTicketAction, UpdateTicketStatusAction, AssignTicketAction,
│                               # EscalateTicketAction, ReplyToTicketAction, RecordTicketRecoveryAction
├── Enums/                      # TicketStatus (+2 cases, allowedTransitions), TicketSource (+STAFF),
│                               # TicketActionType, TicketRecoveryType, ServiceRequestPriority (+toTicketScale)
├── Events/TicketChanged.php
├── Listeners/MirrorTicketToFirestore.php
├── Exceptions/                 # 8 new (see Error Codes)
├── Filters/TicketFilter.php
├── Http/Controllers/Admin/     # SupportTicketController, OperationsStaffController; OperationsQueueController::claim
├── Http/Requests/Tickets/      # Index?, CreateTicketRequest, UpdateTicketStatusRequest, AssignTicketRequest,
│                               # ReplyToTicketRequest, RecordTicketRecoveryRequest, EscalateTicketRequest
├── Http/Requests/Operations/   # IndexOperationsStaffRequest
├── Http/Resources/Tickets/     # TicketResource, TicketActionResource
├── Http/Resources/Operations/  # OperationsStaffResource; OperationsQueueItemResource (+queue_type)
├── Models/                     # Ticket (extended), TicketAction, TicketRecovery
├── Services/Tickets/TicketService.php
├── Services/Operations/        # OperationsQueueService::claim, OperationsStaffService
└── Support/                    # AssigneeEligibility, ClaimGuard
database/migrations/            # 3 additive (below)
database/factories/             # TicketActionFactory, TicketRecoveryFactory; TicketFactory states
tests/Feature/Tickets/, tests/Feature/Operations/, tests/Unit/Tickets/, tests/Unit/Support/
```

### Pattern 1: Ticket single writer (lock → re-read → rule → write + timeline → event)
**What:** every ticket mutation. **When:** all six actions.
```php
// Source: shape of app/Actions/Housekeeping/UpdateHousekeepingTaskStatusAction.php (Phase 6)
public function handle(Ticket $ticket, TicketStatus $to, ?string $reason, User $actor): array
{
    return DB::transaction(function () use ($ticket, $to, $reason, $actor) {
        $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
        $from   = $locked->status;

        if ($to === TicketStatus::ASSIGNED || ! in_array($to, $from->allowedTransitions(), true)) {
            throw new TicketTransitionException(__('custom.errors.ticket_transition_invalid'), [
                'from' => $from->value, 'to' => $to->value,
                'allowed' => array_map(fn (TicketStatus $s) => $s->value, $from->allowedTargets()),
            ]);
        }
        if ($this->reasonRequired($from, $to) && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => [__('custom.validation.required', ['attribute' => 'reason'])],
            ]);
        }
        // side effects: self-assign on → in_progress; stamps resolved_at/closed_at; reopen clears resolved_at
        $locked->save();
        TicketAction::create([... 'type' => TicketActionType::STATUS_CHANGE, 'body' => $reason,
            'from_status' => $from->value, 'to_status' => $to->value, 'user_id' => $actor->id]);
        TicketChanged::dispatch($locked);

        return ['data' => $locked, 'code' => 200];
    }, 3);
}
```
Recommended discretion choice: reasons live in `body` for `status_change`/`escalation` (D-01 already says so); `created` rows have `body = null`; a self-assign on → in_progress writes **one** `status_change` row with `target_user_id = actor` (no second `assignment` row; HK parity — Phase 6 writes one history row).

### Pattern 2: Claim guard inside each writer's lock (A8)
```php
// App\Support\ClaimGuard — pure; the writer calls it after taking its lock and after its own closed check.
final class ClaimGuard
{
    /** @return bool true when the item is already the actor's (200 no-op, no timeline/history row) */
    public static function check(?int $assignedUserId, ?string $assignedUserUuid, User $actor): bool
    {
        if ($assignedUserId === null) return false;
        if ($assignedUserId === $actor->getKey()) return true;
        throw new QueueItemAlreadyClaimedException(__('custom.errors.queue_item_already_claimed'),
            ['assigned_user_uuid' => $assignedUserUuid]);
    }
}
```
**Order under the lock (recommend, document in guide):** (1) type's closed check → `ticket_closed` / `housekeeping_task_closed` / `service_request_closed`; (2) `ClaimGuard` → no-op or 409; (3) normal assign. So a closed item claimed by its own assignee returns the closed 422, not a no-op. Writers return an extra key (`'claimed' => false` on no-op) so the controller picks `queue_item_already_yours` vs `queue_item_claimed` (precedent: `SettleFolioAction` returns `payment_recorded`). Skip `AssigneeEligibility` when `$claim` (the actor passed the work-permission check; D-22).

### Pattern 3: After-commit mirror event (D-21)
Copy `HousekeepingTaskChanged` (`ShouldDispatchAfterCommit`, `SerializesModels`) and `MirrorHousekeepingTaskToFirestore` (`ShouldQueue`, `loadMissing(['assignedUser','guest'])`). Listeners are auto-discovered. `QUEUE_CONNECTION=sync` in phpunit.xml, and Phase 6 tests prove after-commit events fire under `RefreshDatabase`.

### Pattern 4: Staff directory query (D-23)
```php
$q = User::query()->where('is_active', true)->whereIn('type', ['staff', 'super_admin'])->with('roles:id,name');
if ($permission) $q->where(fn ($w) => $w->permission($permission)->orWhere('type', 'super_admin'));
if ($department) {
    $roleExists = Role::where('guard_name', 'users')->where('name', $department)->exists(); // or use the cached registrar
    $roleExists ? $q->role($department, 'users') : $q->whereRaw('1 = 0');   // sales/maintenance → empty
}
if ($search) $q->where('name', 'like', '%'.$escaped.'%');
$rows = $q->orderBy('name')->limit(201)->get(); // truncated = count > 200
```
`departments[]` = `$user->roles->pluck('name')->intersect(Department::values())->values()` — always an array.

### Anti-Patterns to Avoid
- **Inline Firestore mirror in the ticket arm** after D-21: produces two writes per change and can publish rolled-back state. Remove it from both queue actions' ticket arms.
- **`respondFromService()` for 201 creates:** swaps the message to generic `custom.messages.created` (`BaseController:79`). Use `success(..., 'custom.messages.ticket_created', 201)` like `HousekeepingTaskController::store`.
- **Reading `request()->user()` in `TicketFilter`** for `assignee=me`: resolve `me` → actor uuid in the controller before building params.
- **Calling `Role::findByName`/`scopeRole` with `sales`/`maintenance`:** throws `RoleDoesNotExist` → 500.
- **Locking the folio or folio item from the recovery action:** unnecessary (item is already committed and append-only) and would add a ticket → folio lock edge; the unique index is the guard.
- **Holding the ticket lock while calling any room/task/SR/folio writer:** breaks the "tickets is a leaf lock" invariant (see Lock Ordering).

## Integration Points with Phase 6 Code (exhaustive)

| # | File | Change | Risk |
|---|------|--------|------|
| 1 | `app/Support/OperationsQueueType.php` | `baseQuery()` ticket arm → `Ticket::query()->with(['assignedUser','room'])` (room `withTrashed`); `roomNumber()` gains `$item instanceof Ticket => relationLoaded('room') ? $item->room?->number : null`; docblock "tickets have no transition table" updated; `openStatuses()` unchanged (reads `TicketStatus::active()`, now 4 values) | Queue now shows `in_progress`/`waiting_guest` tickets |
| 2 | `app/Enums/TicketStatus.php` | +2 cases; `allowedTransitions()`; **`allowedTargets()` redefined** as transitions minus `ASSIGNED` — the registry's `allowedStatuses()` calls `allowedTargets()`, so this single change fixes queue `allowed_statuses` (D-24) | Existing advisory "every other value" contract changes for ticket rows (additive tightening; list in SUMMARY) |
| 3 | `app/Http/Resources/Operations/OperationsQueueItemResource.php` | add `'queue_type' => $type->segment` | Additive field |
| 4 | `app/Actions/Operations/UpdateRequestStatusAction.php` | ticket arm: `if ($actor === null) throw new LogicException(...)` (A2); delegate to `UpdateTicketStatusAction::handle($item, TicketStatus::from($status), $reason, $actor)`; delete inline mirror for tickets; `mirror()` type hint narrows to `ServiceRequest` | `UpdateHousekeepingTaskStatusAction::afterDone` calls this with an SR only — unaffected by A2 |
| 5 | `app/Actions/Operations/AssignRequestAction.php` | signature `handle($item, User $user, ?User $actor = null, bool $claim = false)`; ticket arm → `AssignTicketAction` (actor required); HK arm passes `$claim`; SR arm becomes `DB::transaction(fn, 3)` + `ServiceRequest::whereKey()->lockForUpdate()` + closed check + `ClaimGuard` (if claim) + `AssigneeEligibility` (if not claim) + `DB::afterCommit` mirror | SR assign currently mirrors synchronously outside any transaction; move to afterCommit (Phase 6 QA-minor-1 precedent) |
| 6 | `app/Actions/Housekeeping/AssignHousekeepingTaskAction.php` | add `bool $claim = false`; after room → task lock and the existing `isOpen()` check: `ClaimGuard` when claiming; `AssigneeEligibility::assert($assignee, 'housekeeping.update')` when not | Also reached by `PATCH /housekeeping/tasks/{task}/assign` via `HousekeepingTaskService::assign` → eligibility applies there too |
| 7 | `app/Services/Operations/OperationsQueueService.php` | `claim(string $type, string $uuid, User $actor)`: `assertCan($actor, statusPermission)` → `resolve` → `assignAction->handle($item, $actor, $actor, claim: true)` → `reload`. `assign()` passes `$actor` (already does) | Same 403-before-404 order as assign/status |
| 8 | `app/Http/Controllers/Admin/OperationsQueueController.php` | `claim()` method; message by `claimed` flag | — |
| 9 | `routes/api.php:761-764` | add `Route::patch('/claim', …)` inside the `operations/queue/{type}/{uuid}` group; new `GET /operations/staff` with `permission:service_requests.view|tickets.view|housekeeping.view`; new `support-tickets` group | `/operations/staff` cannot collide with `operations/queue/{type}/{uuid}` (different prefix) |
| 10 | `database/seeders/RolesAndPermissionsSeeder.php` | reception += `tickets.view, tickets.respond`; concierge += `tickets.view, tickets.assign, tickets.respond` | Blast radius below |
| 11 | `app/Actions/Operations/RouteRequestAction.php:17-19` | call the new category → department helper | Behaviour identical |
| 12 | `app/Support/OperationsQueueMirror.php` | no shape change (ticket payload keeps `category`, no `room_number`) | Keep mirror contract frozen |

## Lock Ordering (claim path vs existing writer paths)

| Path | Locks, in order | Notes |
|------|-----------------|-------|
| HK task status (Phase 6) | room → task → (SR via `afterDone` → `UpdateRequestStatusAction`) | Existing |
| SR status close (Phase 6 QA fix) | room(s) → task(s) → SR | Existing |
| HK task assign / **HK claim** | room → task | Unchanged; claim adds only an in-lock check |
| **SR assign / SR claim (new lock)** | SR only | SR is the *last* lock in every existing chain and the claim path takes nothing after it → no cycle |
| **Ticket writers (all six) / ticket claim / queue ticket arms** | tickets only | Leaf lock: never held while acquiring rooms/tasks/SR/folios |
| **Recovery link** | tickets → (plain read of folio_items/folios; InnoDB FK check takes an S-lock on the referenced `folio_items` row at insert) | Phase 5 writers lock `folios` then insert/lock `folio_items`, and never lock `tickets` → no cycle |
| Phase 5 `PostFolioItemAction` | folios → folio_items | Unchanged; the credit is posted in a separate request before linking |

**Invariant to write into the action docblocks:** the ticket row lock is a leaf; any future ticket-triggered housekeeping dispatch must happen after commit, not under the ticket lock. `[VERIFIED: codebase read of the four writers above]`

**A9 caveat:** SQLite compiles `lockForUpdate()` to nothing; `RecordsRowLocks` swaps the grammar so tests can assert `for update` appears in the SQL. It does not prove serialization. `[VERIFIED: tests/Concerns/RecordsRowLocks.php]`

## Migrations (additive only)

Timestamps must sort after `2026_09_27_100200_add_check_out_mode_to_reservations_table.php`.

| # | File (suggested) | Content |
|---|------------------|---------|
| 1 | `2026_09_28_100000_add_support_columns_to_tickets_table.php` | `description text null`; `foreignId('reservation_id')->nullable()->constrained()->nullOnDelete()`; `foreignId('room_id')->nullable()->constrained()->nullOnDelete()`; `foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()`; `timestamp('resolved_at')->nullable()`, `timestamp('closed_at')->nullable()`; `unsignedTinyInteger('escalation_level')->default(0)`; indexes `reservation_id`, `room_id`, `created_by`, `source`, `(status, priority)`, `created_at`. `down()` drops indexes then `dropConstrainedForeignId` then columns (precedent: `2026_09_26_130000_add_ledger_columns_to_folio_items_table.php`, which already adds constrained FKs on SQLite successfully) |
| 2 | `2026_09_28_100100_create_ticket_actions_table.php` | per D-01; `user_id`/`target_user_id` `->constrained('users')->restrictOnDelete()`; `message_id` `->constrained('messages')->nullOnDelete()`; `timestamp('created_at')->useCurrent()` (precedent: `housekeeping_task_status_history`) |
| 3 | `2026_09_28_100200_create_ticket_recoveries_table.php` | per D-02; `ticket_action_id` `->unique()->constrained()->cascadeOnDelete()`; `folio_item_id` `->nullable()->unique()->constrained('folio_items')->nullOnDelete()`; `decimal('amount_usd', 10, 2)->nullable()`; `string('description', 1000)`; `created_at` only |

`restrictOnDelete` on users is safe: no code path hard-deletes a `User` (`StaffService::deactivate` only flips `is_active` and deletes tokens; no test deletes users). `[VERIFIED: grep of app/ and tests/]` `foreign_key_constraints` is `true` for SQLite (`config/database.php:40`), so FK behaviour is exercised in tests.

Config: `config/hotel.php` gains `'ticket_max_escalation_level' => (int) env('HOTEL_TICKET_MAX_ESCALATION_LEVEL', 3)`. `.env.example` edits were permission-blocked for agents in Phase 6 — plan a fallback (SUMMARY deploy note) if the write is refused.

## Permission Deltas (D-10) and Blast Radius (A3/A4)

Catalogue stays **26 permissions / 11 groups** (not 24 — D-10 slip; `SeederTest::test_all_26_permissions_seeded`). `[VERIFIED: RolesAndPermissionsSeeder.php + SeederTest.php:14,41]`

| Preset | Before | After |
|--------|--------|-------|
| reception | …, service_requests.view/.update, housekeeping.view/.assign (14) | + tickets.view, tickets.respond (16) |
| concierge | service_requests.view/.assign/.update, guests.view/.edit (5) | + tickets.view, tickets.assign, tickets.respond (8) |
| events, kitchen, housekeeping, content_editor, content_manager | — | unchanged |

**A4 blast radius (routes newly reachable) — verified against `routes/api.php`:**

| Route | Gate | reception | concierge |
|-------|------|-----------|-----------|
| `GET /cms/conversations`, `GET /cms/conversations/{c}/messages` (guest chat PII) | tickets.view | gains | gains |
| **`POST /cms/conversations/{c}/messages` (send chat to a guest)** — *not in A4's list* | tickets.respond | **gains** | **gains** |
| `GET /cms/event-inquiries`, `/{inquiry}` (RFP PII, budgets) | tickets.view | gains | gains |
| `PATCH /cms/event-inquiries/{inquiry}/status|assign` | tickets.assign | — | gains |
| `GET /operations/queue` ticket rows; `PATCH /operations/queue/tickets/{uuid}/status` | tickets.view / tickets.respond | gains | gains |
| `PATCH /operations/queue/tickets/{uuid}/assign` | tickets.assign | — | gains |
| `/dashboard/summary` `tickets` + `event_inquiries` blocks | tickets.view (in-service) | gains | gains |
| All new `/support-tickets*` routes | per D-10 | all except assign | all |

**A3 un-assignable table (after D-09 + D-10), by preset:** `[VERIFIED: seeder presets]`

| Queue type | Work permission | Assignable presets | Newly un-assignable presets |
|-----------|-----------------|--------------------|-----------------------------|
| service-requests | service_requests.update | reception, kitchen, housekeeping, concierge | events, content_editor, content_manager |
| tickets | tickets.respond | events, reception, concierge | kitchen, housekeeping, content_editor, content_manager |
| housekeeping-tasks | housekeeping.update | housekeeping | **reception** (holds `.assign` not `.update`), kitchen, concierge, events, content_editor, content_manager |

Super admins are assignable everywhere (Gate::before). Deactivated users and `type` outside `{staff, super_admin}` are never assignable. **Correction to A3's example:** reception is *not* newly un-assignable to service requests (it holds `service_requests.update` since the Phase 6 ruling).

## Error Codes (D-25 as amended by A1)

| Exception class | error_code | HTTP | Context |
|-----------------|-----------|------|---------|
| `TicketTransitionException` | ticket_transition_invalid | 422 | `{from, to, allowed}` |
| `TicketClosedException` | ticket_closed | 422 | `{status}` |
| `TicketEscalationInvalidException` | ticket_escalation_invalid | 422 | `{reason: self|same_assignee}` |
| `TicketEscalationLimitException` | ticket_escalation_limit | 422 | `{level, max}` |
| `TicketRecoveryFolioInvalidException` | ticket_recovery_folio_invalid | 422 | `{folio_item_uuid, reason: not_credit|other_stay|already_linked|no_stay}` |
| `AssigneeNotEligibleException` | assignee_not_eligible | 422 | `{user_uuid, required_permission}` |
| `QueueItemAlreadyClaimedException` | queue_item_already_claimed | 409 | `{assigned_user_uuid}` |
| `ServiceRequestClosedException` | service_request_closed | 422 | `{status}` |

`service_request_closed` is required: no SR closed/transition exception exists (`ServiceBookingTransitionException` is for service *bookings*). `[VERIFIED: ls app/Exceptions]` Existing reused: `housekeeping_task_closed`, `validation_failed`, `not_found`, `forbidden`, `unauthorized`. **No `queue_item_closed`.** All handled by the generic `DomainException` branch of `bootstrap/app.php` (no handler change needed). Lang: 8 error keys + ~9 message keys (`ticket_created`, `ticket_status_updated`, `ticket_assigned`, `ticket_replied`, `ticket_recovery_recorded`, `ticket_escalated`, `queue_item_claimed`, `queue_item_already_yours`, staff list uses `success`) × 5 locales.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Decimal abs/sum/2dp | float math, `number_format` on floats | `FolioLedger::normalize/fromNumeric/sum` + `ltrim($v, '-')` on the normalized string | bcmath strings; SQLite `SUM` returns float |
| "Has permission via role or direct" query | manual pivot joins | Spatie `->permission($p)` scope (+ `orWhere('type','super_admin')`) | Handles both pivots; Gate::before parity added explicitly |
| Row-lock proof in tests | custom SQL spies | `Tests\Concerns\RecordsRowLocks::assertLocksRow('tickets', …)` | Established Phase 5/6 helper |
| Firestore fake | ad-hoc mocks | `Tests\Support\FakeFirebaseService` bound to `FirebaseServiceInterface` | Established |
| Race on double-link | app-level mutex | unique index on `ticket_recoveries.folio_item_id` + catch `UniqueConstraintViolationException` | Precedent `CreateHousekeepingTaskAction:81` |
| Action-level 422 on a field | custom exception | `ValidationException::withMessages([...])` | Precedent `SettleFolioAction:64`; renders `validation_failed` |
| Query budget assertion | DB::listen counters | `$this->expectsDatabaseQueryCount(n)` on the service call | Precedent `GuestDirectoryTest:315` (counts service, not HTTP/auth) |

## Common Pitfalls

### Pitfall 1: D-09 breaks existing green tests
**What goes wrong:** ~12 existing tests assign a bare `User::factory()->create()` (no permission) and will get 422 `assignee_not_eligible`.
**Where:** `OperationsQueueTest::test_assigning_a_service_request_updates_and_mirrors`, `::test_assigning_a_ticket_with_tickets_assign_permission_succeeds`; `ServiceRequestBoardTest::test_board_rows_are_progressed_through_the_queue`; `FirestoreMirrorResilienceTest::test_a_failed_firestore_mirror_does_not_fail_assigning_a_request`; `OperationsQueueHousekeepingTest` (assign at ~L238 and ~L336); `Feature/Housekeeping/AssignTest` (all happy paths); `Unit/Housekeeping/AssignHousekeepingTaskActionTest` (all). `[VERIFIED: grep tests/]`
**How to avoid:** land `AssigneeEligibility` and the re-pins in one plan; add a `TestCase` helper or `UserFactory` state (`->withPermissions([...])` via `afterCreating`) so assignees are eligible. Run the full suite at that plan's end.

### Pitfall 2: `allowed_statuses` silently wrong for tickets
**What goes wrong:** the registry calls `$item->status->allowedTargets()`; if only `allowedTransitions()` is added, queue rows keep advertising "every other value" including `assigned`.
**How to avoid:** redefine `TicketStatus::allowedTargets()` = `allowedTransitions()` minus `ASSIGNED`; test the queue row and `TicketResource` both.

### Pitfall 3: Query budgets unreachable with naive eager loads
**What goes wrong:** list = count + page + guest + reservation + room + assignedUser + createdBy = **7** (> 6). Show = guest, reservation, room, assignedUser, createdBy, actions, actions.user, actions.targetUser, actions.recovery, recovery.folioItem = **10** (> 6).
**How to avoid:** one users preload — collect `assigned_user_id`, `created_by` (and on show, every action's `user_id`/`target_user_id`) → `User::whereKey($ids)->get(['id','uuid','name'])` → `setRelation(...)` on each model (1 query). Load `folio_item_uuid` on recoveries via a subselect `addSelect(['folio_item_uuid' => FolioItem::select('uuid')->whereColumn('folio_items.id','ticket_recoveries.folio_item_id')])`. List totals via `withSum` subselects (no extra query); show totals computed in PHP from loaded recoveries. Target: list = count, page, guest, reservation, room, users = 6; show = guest, reservation, room, actions, recoveries, users = 6. Confirm `withSum` on the `hasManyThrough` relation in a test `[ASSUMED: HasManyThrough supports withAggregate in L13]`.

### Pitfall 4: 201 message swapped
`respondFromService()` replaces any 201 message with `custom.messages.created`. Create (201) and reply (201) must call `success()` directly.

### Pitfall 5: Resource triggers queries
`TicketResource` must use `whenLoaded`/pre-set relations; `latest escalation`, totals and `allowed_statuses` computed from loaded data only. `Ticket::room()` should be `->withTrashed()` (rooms soft-delete; `Room` uses `SoftDeletes`) or a trashed room's number disappears.

### Pitfall 6: Spatie scopes throw on unknown names
`scopeRole('sales')` → `RoleDoesNotExist`; `scopePermission('x')` → `PermissionDoesNotExist`. Short-circuit `sales`/`maintenance` (and any Department without a role) to an empty result; A5's permission whitelist (`Rule::in([...3 work perms])`) prevents the second. `[VERIFIED: vendor/spatie/laravel-permission/src/Traits/HasRoles.php:84-106]`

### Pitfall 7: `assignee=me` in a filter
Filters never see auth. Controller rewrites `assignee=me` to the actor uuid before `indexParams()` reach the service.

### Pitfall 8: Priority label filter against an int column
`priority[eq]=high` must cast via `ServiceRequestPriority::from($v)->toTicketScale()` in `TicketFilter::cast()`; an unknown label → 422 via `reject()` (or match nothing — pick one; HK filter precedent: unknown enum value matches nothing).

### Pitfall 9: Negative credit amounts
Credits are stored negative (`PostFolioItemAction:95`). `amount_usd` on the recovery = absolute value; body `amount_usd`, when present, must equal that absolute value (compare normalized strings, not floats). Test asserts `-25.00` credit → recovery `25.00`.

### Pitfall 10: A2 LogicException reachable from HTTP?
Every queue route is `auth:users`, so the actor is never null in HTTP. The only null-actor callers today are system SR paths (`UpdateHousekeepingTaskStatusAction::afterDone` passes `$actor`, possibly null, with an SR). Keep the guard strictly on the ticket arm.

### Pitfall 11: Mirror count assertions
Moving the SR assign mirror to `DB::afterCommit` and tickets to a queued listener changes *when* mirrors happen but not *how many*; `OperationsQueueTest` asserts `assertCount(1, $fake->mirrors)` for SR assign — still 1. A ticket assign now mirrors through `MirrorTicketToFirestore` (sync queue in tests).

## Code Examples

### TicketStatus transition table
```php
// Source: D-06 table; shape of HousekeepingTaskStatus::canTransitionTo/allowedTargets (Phase 6)
public function allowedTransitions(): array
{
    return match ($this) {
        self::OPEN          => [self::IN_PROGRESS, self::RESOLVED, self::CLOSED],
        self::ASSIGNED      => [self::IN_PROGRESS, self::WAITING_GUEST, self::RESOLVED, self::CLOSED],
        self::IN_PROGRESS   => [self::WAITING_GUEST, self::RESOLVED, self::CLOSED],
        self::WAITING_GUEST => [self::IN_PROGRESS, self::RESOLVED, self::CLOSED],
        self::RESOLVED      => [self::CLOSED, self::IN_PROGRESS],
        self::CLOSED        => [],
    };
}
public function allowedTargets(): array   // consumed by OperationsQueueType::allowedStatuses()
{
    return array_values(array_filter($this->allowedTransitions(), fn (self $s) => $s !== self::ASSIGNED));
}
```

### AssigneeEligibility
```php
final class AssigneeEligibility
{
    public static function assert(User $target, string $workPermission): void
    {
        if ($target->is_active && in_array($target->type, ['staff', 'super_admin'], true) && $target->can($workPermission)) {
            return;
        }
        throw new AssigneeNotEligibleException(__('custom.errors.assignee_not_eligible'),
            ['user_uuid' => $target->uuid, 'required_permission' => $workPermission]);
    }
}
```

### Recovery folio check (inside the ticket lock)
```php
$item = FolioItem::with('folio.reservation:id,guest_id')->where('uuid', $folioItemUuid)->firstOrFail(); // 404 unknown
if ($ticket->reservation_id === null && $ticket->guest_id === null) $reason = 'no_stay';
elseif ($item->source_type !== FolioItemSource::CREDIT->value)      $reason = 'not_credit';
elseif ($ticket->reservation_id !== null ? $item->folio->reservation_id !== $ticket->reservation_id
                                         : $item->folio->reservation?->guest_id !== $ticket->guest_id) $reason = 'other_stay';
elseif (TicketRecovery::where('folio_item_id', $item->id)->exists()) $reason = 'already_linked';
// …create action, then recovery in try/catch UniqueConstraintViolationException → already_linked
```
Recommend `no_stay` evaluated first (it is the earliest-failing case per A7) and an unknown `folio_item_uuid` validated as `exists:folio_items,uuid` in the FormRequest (422) rather than 404.

## State of the Art

| Old Approach (today) | Current Approach (Phase 7) | Impact |
|--------------|------------------|--------|
| Queue ticket status accepts any `TicketStatus` | Transition table + reason rules via `UpdateTicketStatusAction` | New 422s on an existing route (additive tightening; SUMMARY) |
| Assign = bare `update()`, any user | Locked writers + `AssigneeEligibility` | New 422 on all assign verbs; Phase 6 QA minor 3 closed |
| Ticket mirror inline, synchronous | `TicketChanged` after commit + queued listener | Needs queue worker in prod |
| Queue rows expose `type` only | `type` + `queue_type` | Dashboard builds `{queue_type}/{uuid}` |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `withSum`/`withAggregate` works on the `Ticket::recoveries()` HasManyThrough relation in Laravel 13 | Pitfall 3 | Fallback: `addSelect` subquery joining `ticket_actions`; budget unchanged |
| A2 | Spatie `scopePermission` resolves permission→roles from the registrar cache without an extra query per call | Pattern 4 / D-23 budget ≤ 4 | Budget test fails → measure and adjust (still bounded) |
| A3 | Eager-load `limit()` per parent (for a 200-row `actions` cap) behaves on SQLite and MySQL 8 | Discretion: actions cap | Use a separate ordered query with `limit(201)` instead |

## Open Questions

1. **Does the SR `service_request_closed` check apply to plain `PATCH …/service-requests/{uuid}/assign`, or only to claim?**
   - Known: A1 says the SR arm "gains lockForUpdate() and a closed check … used by both the SR claim path and, going forward, any other SR terminal-status guard". Ticket (D-08) and HK assign already reject closed items.
   - Recommendation: apply it to both (consistency with ticket/HK assign; assigning a completed request is a latent bug) and list it in the SUMMARY as an additive tightening on an existing route. If the consultant prefers minimal change, gate it on `$claim` only.
2. **"Latest escalation exposed in the resource" (D-18) vs. the D-13 field list (no such field).**
   - Recommendation: show-only `latest_escalation {level, target_user{uuid,name}, created_at}|null` derived from the loaded `actions` (no query); list omits it (filter `escalated` + `escalation_level` cover the list).
3. **`actions[]` cap on show (discretion).** Recommendation: newest 200 returned ascending, `actions_truncated` bool on the show payload.
4. **Permission count text.** D-10 says 24/11; code says 26/11. Recommendation: plans and docs pin 26/11 (the CONTEXT wording is a slip, not a decision).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP CLI | tests, artisan | ✓ | 8.4.1 (code must stay 8.3-compatible) | — |
| Laravel | framework | ✓ | 13.19.0 | — |
| SQLite (in-memory) | test DB | ✓ | bundled | — |
| MySQL | lock serialization proof (A9) | not used in tests | — | `RecordsRowLocks` SQL assertion only |
| Queue worker | `MirrorTicketToFirestore` in prod | n/a locally (`QUEUE_CONNECTION=sync` in tests) | — | SUMMARY `[BLOCKING]` deploy note |
| ParaTest | `--parallel` | ✗ | — | Serial run (~4 min) |

**Gate A10 check: MET.** Phase 6 is committed (`d8ac795`, `613a980`, `a17c293`), and `php artisan test` on HEAD `a17c293` ran on 2026-09-27: **1709 passed / 1709, 11209 assertions, 210 s** `[VERIFIED: full suite run this session]` (the Phase 6 SUMMARY says 1708; the extra test is presumably the 405 repair commit's).

**Runtime/deploy state (not a rename phase, but prod state changes):** role presets live in the prod DB → `[BLOCKING] php artisan db:seed --class=RolesAndPermissionsSeeder` + `permission:cache-reset` implied by the seeder's `forgetCachedPermissions()`; `[BLOCKING] php artisan migrate` (3 migrations); queue worker for the ticket mirror; `HOTEL_TICKET_MAX_ESCALATION_LEVEL` optional (default 3). Existing Firestore `ops_queue/ticket_*` docs keep their shape.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12 via `php artisan test` (Laravel 13.19) |
| Config file | `backend/phpunit.xml` (SQLite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`) |
| Quick run command | `cd backend && php artisan test --filter=Ticket` (or a single file path) |
| Full suite command | `cd backend && php artisan test` (serial, ~3.5 min, 1709 tests green at `a17c293`) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TICKET-01 | list filters (status, department, source, category, priority label, assignee uuid/unassigned/me, guest, reservation, escalated, created_at), sort, includes closed, 401/403, ≤ 6 queries | feature | `php artisan test tests/Feature/Tickets/TicketIndexTest.php` | ❌ Wave 0 |
| TICKET-01 | show with ordered `actions[]`, totals, `conversation_uuid`, no `message_id`, ≤ 6 queries, 404 | feature | `php artisan test tests/Feature/Tickets/TicketShowTest.php` | ❌ |
| TICKET-02 | create: source forced, department fallback, guest derived, mismatch 422, `created` action, `created_by`, 201 message, 401/403/422 | feature | `php artisan test tests/Feature/Tickets/TicketCreateTest.php` | ❌ |
| TICKET-03 | full transition matrix, `assigned` rejected, reason rules, stamps, reopen clears, self-assign, timeline row, `TicketChanged` mirror | feature + unit | `php artisan test tests/Feature/Tickets/TicketStatusTest.php tests/Unit/Tickets/TicketStatusTest.php tests/Unit/Tickets/UpdateTicketStatusActionTest.php` | ❌ |
| TICKET-04 | open → assigned, swap, closed 422, no-op self, eligibility (inactive, wrong type, no perm, super admin OK), 403 without tickets.assign | feature + unit | `php artisan test tests/Feature/Tickets/TicketAssignTest.php tests/Unit/Support/AssigneeEligibilityTest.php` | ❌ |
| TICKET-05 | reply row, no status change, closed 422, `message_id` null, 201, no mirror | feature | `php artisan test tests/Feature/Tickets/TicketReplyTest.php` | ❌ |
| TICKET-06 | folio_credit link + `abs()`, `not_credit|other_stay|already_linked|no_stay`, amount mismatch, unique-index backstop (competing insert via `TicketRecovery::creating` hook), non-money types, `folio_item_uuid` prohibited, closed 422, no DELETE route (405), totals on resource | feature | `php artisan test tests/Feature/Tickets/TicketRecoveryTest.php` | ❌ |
| TICKET-07 | self, same_assignee, cap (config override), open → assigned, level++, meta whitelist, closed 422, eligibility | feature | `php artisan test tests/Feature/Tickets/TicketEscalateTest.php` | ❌ |
| OPS-01 | claim × 3 types, 409 other, 200 no-op self (message), per-type closed codes, 403 with only assign / no perm, `assertLocksRow` for tickets/service_requests/rooms+housekeeping_tasks, HK room → task order | feature | `php artisan test tests/Feature/Operations/QueueClaimTest.php` | ❌ |
| OPS-01 | deactivation revokes tokens; old token → 401 on claim | feature | `php artisan test tests/Feature/Operations/DeactivatedTokenClaimTest.php` | ❌ |
| OPS-02 | type/permission/department/search, permission whitelist 422, `departments[]` array (0/1/n), no `roles`/email, super admin rules, inactive excluded, sales → empty, cap/truncated, ≤ 4 queries, 401/403 | feature | `php artisan test tests/Feature/Operations/OperationsStaffTest.php` | ❌ |
| OPS-03 | `queue_type` on all three row types, ticket `room_number`, ticket `allowed_statuses`, queue status delegates + timeline, A2 LogicException | feature | `php artisan test tests/Feature/Operations/OperationsQueueTicketArmTest.php` | ❌ |
| XCUT | presets + blast radius pins (conversations read **and reply**, event inquiries, kitchen/housekeeping unchanged), 26 perms | feature | `php artisan test tests/Feature/Staff/RolePresetsTest.php tests/Feature/SeederTest.php tests/Feature/Docs/PermissionGuideAccuracyTest.php` | ✅ re-pin |
| Regression | assign re-pins after D-09 | feature/unit | `php artisan test tests/Feature/Operations tests/Feature/Housekeeping tests/Unit/Housekeeping tests/Feature/Notification/FirestoreMirrorResilienceTest.php` | ✅ re-pin |

### Sampling Rate
- **Per task commit:** the touched test files (`php artisan test <paths>`).
- **Per wave merge:** `php artisan test tests/Feature/Tickets tests/Feature/Operations tests/Feature/Housekeeping tests/Unit tests/Feature/Staff tests/Feature/SeederTest.php`.
- **Phase gate:** full `php artisan test` green before `/gsd-verify-work` and before the commit.

### Wave 0 Gaps
- [ ] `database/factories/TicketActionFactory.php`, `TicketRecoveryFactory.php`; `TicketFactory` states (`staff()`, `assignedTo($user)`, `withReservation()`)
- [ ] Eligible-assignee helper (`UserFactory` state or `TestCase` helper) — needed by the D-09 re-pins
- [ ] `tests/Feature/Tickets/` directory (new) and `tests/Unit/Tickets/`
- [ ] No framework install needed

## Security Domain

### Applicable ASVS Categories (Level 1)

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes (indirect) | Sanctum `auth:users`; deactivation revokes tokens (`StaffService::deactivate`) — regression test |
| V3 Session Management | yes (indirect) | token revocation on deactivate; no new session logic |
| V4 Access Control | yes | route `permission:` middleware for ticket routes; in-service `assertCan` per registry type for claim; assignee eligibility (prevents handing work to ineligible/deactivated users) |
| V5 Input Validation | yes | FormRequests: `exists:*,uuid`, `Rule::enum`, `Rule::in` (A5 whitelist), `decimal:0,2`, `between:0,99999.99`, length limits; `source` never accepted from body; `prohibited` for `folio_item_uuid` on non-credit types |
| V6 Cryptography | no | — |
| V7 Error Handling & Logging | yes | domain exceptions → envelope; `logExcept(['description'])`; `ticket_actions` is the audit trail |
| V8 Data Protection | yes | staff list omits email/permissions; resources expose names/uuids only; Firestore payload has no names (unchanged) |
| V11 Business Logic | yes | row locks, transition table, escalation cap, unique folio link |

### Known Threat Patterns

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Linking another guest's folio credit to inflate recovery totals (IDOR) | Tampering | `other_stay` check against ticket reservation/guest; unique `folio_item_id` |
| Double claim race | Tampering | claim check inside the writer's `lockForUpdate` (MySQL); 409 |
| Assigning work to a deactivated/unauthorized account | Elevation | `AssigneeEligibility` |
| Escalation loop/spam | DoS | cap + self/same guards; no scheduler |
| Complaint text in activity_log diffs | Info disclosure | `logExcept(['description'])` |
| Staff enumeration via directory | Info disclosure | gate = queue view permissions; no email; cap 200 |
| Wider chat access (reception/concierge can read **and send** guest chat) | Elevation (by design) | documented blast radius, pinned in `RolePresetsTest`, PROJECT.md debt |
| Mass-assigning `source`/`created_by`/`status` on create | Tampering | server-set; not in FormRequest rules; `validated()` only |

## Sources

### Primary (HIGH confidence — codebase, read this session)
- `backend/app/Support/OperationsQueueType.php`, `OperationsQueueMirror.php`, `FolioLedger.php`
- `backend/app/Actions/Operations/{AssignRequestAction,UpdateRequestStatusAction,RouteRequestAction}.php`
- `backend/app/Actions/Housekeeping/{AssignHousekeepingTaskAction,UpdateHousekeepingTaskStatusAction,CreateHousekeepingTaskAction}.php`
- `backend/app/Actions/Folio/{PostFolioItemAction,SettleFolioAction}.php`
- `backend/app/Services/Operations/OperationsQueueService.php`, `app/Services/StaffService.php`, `app/Services/Auth/AuthStaffService.php`
- `backend/app/Models/{Ticket,User,FolioItem,Folio}.php`, `app/Enums/{TicketStatus,TicketSource,TicketCategory,ServiceRequestStatus,ServiceRequestPriority,Department,FolioItemSource}.php`
- `backend/routes/api.php` (L515-529 event inquiries, L627-639 staff, L740-766 conversations/queue/summary)
- `backend/database/seeders/RolesAndPermissionsSeeder.php`, migrations for tickets/folio_items/housekeeping history
- `backend/bootstrap/app.php` (DomainException envelope, 405), `app/Base/{BaseController,BaseFilter,BaseService}.php`
- `backend/tests/Concerns/RecordsRowLocks.php`, `tests/Feature/{SeederTest,Staff/RolePresetsTest,Operations/*}.php`
- `vendor/spatie/laravel-permission/src/Traits/{HasRoles,HasPermissions}.php` (v8.3.0)
- `.planning/phases/06-housekeeping-guest-services/SUMMARY.md`, `.planning/research/PITFALLS.md` (#5, #7, #9, #10)

### Secondary / Tertiary
- None needed; no external research performed (no new libraries).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies; versions read from lockfile/CLI.
- Architecture / integration points: HIGH — every touched file read.
- Pitfalls: HIGH for test blast radius and budgets (counted); MEDIUM for the three `[ASSUMED]` framework behaviours.

**Research date:** 2026-09-27
**Valid until:** 2026-10-27 (stable; re-check if Phase 6 code changes before planning)
