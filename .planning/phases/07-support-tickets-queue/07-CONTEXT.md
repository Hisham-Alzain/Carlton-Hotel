# Phase 7: Support Tickets & Queue - Context

**Gathered:** 2026-09-27
**Status:** Locked decisions captured. Gate MET (Phase 6 committed d8ac795/613a980/a17c293, suite 1709 green). Ready for planning. **Post-research consultant decisions (PR-1..PR-8, end of file) override earlier text where they differ.**
**Decided by:** Fable 5.1 consultant (owner delegated all decisions, instruction 2026-09-26: route decisions to the consultant/council, never ask the owner). Eight decisions were flagged `convene: true` and reviewed by one ai-council (Architect conf 78, Skeptic/Red Team conf 72, Risk & Security conf 78; council confidence 76, no member voted to reject). Ten binding amendments (A1–A10) are folded into the decisions below and marked `(council A#)`. Full text: `07-DISCUSSION-LOG.md`.

## Gate (council A10)

Research and planning for Phase 7 **start only after**:
1. Phase 6 (Housekeeping & Guest Services) is committed, with its SUMMARY written, and
2. the full PHPUnit suite is green on that commit.

This CONTEXT.md and the DISCUSSION-LOG may be written ahead of that gate (as done here) so the decision record is ready, but no `/gsd-plan-phase 7` or execution work should begin until the gate condition is met. Phase 7 extends the `OperationsQueueType` registry, the widened `AssignRequestAction`/`UpdateRequestStatusAction`, and the housekeeping single writers that Phase 6 introduces — it does not fork them, so a red or uncommitted Phase 6 invalidates the reuse assumptions throughout this file.

<domain>
## Phase Boundary

Staff run the full support-ticket lifecycle (list, view, create, status, assign, reply, recovery actions, escalate) and route operations-queue work to the right colleague via a generalized claim verb and an assignable-staff directory. Routes: `GET|POST /support-tickets`, `GET /support-tickets/{ticket}`, `PATCH /support-tickets/{ticket}/status|assign`, `POST /support-tickets/{ticket}/reply|recovery-actions|escalate`; `PATCH /operations/queue/{type}/{uuid}/claim` (all three registry types); `GET /operations/staff`. New tables `ticket_actions` (timeline) and `ticket_recoveries` (money links). Additive `tickets` columns (`description`, `reservation_id`, `room_id`, `created_by`, `resolved_at`, `closed_at`, `escalation_level`). No new permission strings — `tickets.*` presets widen on `reception`/`concierge`. Every queue row gains `queue_type`. Out of scope: guest-visible replies (TICKET-08), staff notifications, SLA/auto-escalation, an unassign verb, a `users.department` column, direct money posting through tickets, reopening closed tickets, ticket merge, attachments, the RT-01 websocket queue.

</domain>

<decisions>
## Implementation Decisions

### Ground truth found in code (consultant read-only inspection)

- `tickets` columns today: `uuid, guest_id, chatbot_session_id (plain), conversation_id, subject, category, status (open|assigned|resolved|closed), priority tinyint 1-3, department, source (default chatbot), assigned_user_id, timestamps`. No description, reservation, room, creator or per-state timestamps.
- `TicketSource` has only `chatbot`. **Nothing creates a ticket today** — `RouteRequestAction` says "nothing creates a Ticket until P11". Tickets exist only through `TicketFactory` and `DemoShowcaseSeeder`.
- `AssignRequestAction`/`UpdateRequestStatusAction` do a bare `update()`: no lock, no transition check, no assignee eligibility. `PATCH …/tickets/{uuid}/status` accepts any `TicketStatus`, including `assigned` with no assignee. Assigning a ticket leaves its status unchanged. Firestore mirroring is synchronous inside the actions.
- Queue rows have `type = service_request|ticket` (+ `housekeeping_task` in Phase 6). The URL segment is `service-requests|tickets|housekeeping-tasks`, so `type` cannot build the path today — this is OPS-03's real gap.
- `users` has no department column. Role presets `reception, kitchen, housekeeping, concierge, events` share names with `Department` values; `sales` and `maintenance` have no role.
- `GET /staff` is gated by `StaffPolicy::viewAny` → `staff.manage` and returns full permission dumps, so it is unusable as an assignee picker.
- Only the `events` preset holds `tickets.*`. `tickets.view|respond` also gate the staff chat inbox (`/cms/conversations`).
- No staff notification channel exists (only `GuestNotification`).
- Dashboard mocks: `ticketService` sends `{owner}` to assign, `{target_owner, level}` to escalate, `{action_type, amount, currency, detail}` to recovery. Status vocabulary `open|in_progress|waiting_guest|resolved|closed`. Staff list mocked at `/operations/queue/staff`; claim at `/operations/queue/{id}/claim` (moves open → in_progress in the mock — the real contract does not, see D-22).

### Fact resolved by the council Chair
Skeptic claimed a deactivated user keeps a live token, questioning D-22's premise. Chair verified in code: `app/Services/StaffService.php:54` deletes all tokens on deactivation, and `AuthStaffService.php:21` checks `is_active` at login. **D-22's premise holds** — no per-request `EnsureUserIsActive` middleware is needed. A regression test is required instead: deactivation revokes tokens, and a deactivated user's old bearer token gets 401 on the claim route (folded into D-27).

## 1. Schema

**D-01: Timeline table `ticket_actions` (one append-only table, typed columns + small meta).** `convene: true` · stakes: high
Columns:
- `id`, `uuid` unique
- `ticket_id` FK cascadeOnDelete
- `user_id` FK users nullable, **`restrictOnDelete()` (council A6 — overrides the consultant's original `nullOnDelete`)** (the actor; null = system/guest)
- `type` string(20): `TicketActionType { CREATED, STATUS_CHANGE, ASSIGNMENT, ESCALATION, REPLY, RECOVERY }`
- `body` text nullable (reply text, or the reason for status/escalation)
- `from_status` / `to_status` string(20) nullable
- `target_user_id` FK users nullable, **`restrictOnDelete()` (council A6, same override)** (the assignee or escalation target)
- `message_id` FK messages nullable nullOnDelete. Reserved for TICKET-08: **never written, never exposed**, covered by a model comment
- `meta` json nullable. Non-queried extras only: `{claim: true}`, `{level: 2, previous_assignee_uuid}`. Never money. **`meta` keys are whitelisted per `TicketActionType` (council A8)** — each action type declares the only meta keys it may write; unknown keys are rejected, not silently stored.
- `created_at` only (`const UPDATED_AT = null`)

Indexes: `(ticket_id, created_at)`, `user_id`, `target_user_id`, `type`.
Model `TicketAction` has no `LogsActivity`, because it is the audit — confirmed by council A6. No update or delete path exists. **The `ticket_actions` timeline is canonical over the `tickets` columns for history reads (council A8)** — e.g. escalation history and status timestamps are read from the timeline, not reconstructed from denormalized columns.

Rationale: one ordered timeline serves the ticket detail and the "who/when" criterion. Typed status/target columns keep filterable data out of JSON. A table per action kind would force a UNION for every detail read. `restrictOnDelete()` on actor FKs (A6) means a user row cannot be deleted while it has ticket-action history — consistent with staff being soft-deactivated (`is_active`), never hard-deleted.

**D-02: Money lives in a child table `ticket_recoveries`, not in `meta`.** `convene: true` · stakes: high
Columns:
- `id`, `uuid` unique
- `ticket_action_id` FK **unique** cascadeOnDelete
- `type` string(30) (`TicketRecoveryType`, D-14)
- `amount_usd` DECIMAL(10,2) nullable
- `description` string(1000)
- `folio_item_id` FK folio_items nullable **unique** nullOnDelete
- `created_at`

There is no `ticket_id` and no `recorded_by`: both are transitively determined by the action row (3NF). Relations are `Ticket::recoveries()` hasManyThrough and `TicketAction::recovery()` hasOne. **`amount_usd` is documented on the model as a snapshot of a frozen folio item at link time (council A8)** — it does not update if the folio item is later adjusted (folio items are not adjusted post-post in this system, but the comment makes the invariant explicit for future readers).

Rationale: the DECIMAL-for-money rule forbids amounts in JSON. The unique `folio_item_id` stops one credit being claimed by two recoveries. Phase 9 can sum compensation per ticket or period with an indexed join.

**D-03: Additive `tickets` columns.** stakes: medium
- `description` text nullable
- `reservation_id` FK nullable nullOnDelete, indexed
- `room_id` FK nullable nullOnDelete, indexed
- `created_by` FK users nullable nullOnDelete, indexed (null = chatbot/guest/system)
- `resolved_at` and `closed_at` timestamps nullable
- `escalation_level` unsignedTinyInteger default 0
- New indexes: `source`, `(status, priority)`, `created_at`

`Ticket` uses `logExcept(['description'])` **(council A6)** so guest complaint text stays out of `activity_log` diffs — the model keeps `LogsActivity` for everything else (status, assignee, etc.), only `description` is excluded.

**Not added:** `escalated_at`, `escalated_to_user_id`, `escalation_reason`. Those are history, read from the latest `escalation` action (D-01 is canonical for history per A8). `escalation_level` is kept because it is current state: it enforces the cap (D-19) and supports filtering.

**D-04: `TicketSource` gains `STAFF = 'staff'` only.** stakes: low
`POST /support-tickets` forces `source = staff` server-side and does not accept it from the body. `guest_app` is deferred because this phase has no guest create route, and there should be no dead enum values. The DB default of `chatbot` stays as it is.

**D-05: Linking.** stakes: medium
The create body takes optional `guest_uuid`, `reservation_uuid` and `room_uuid`, all nullable (internal tickets have none).
- When `reservation_uuid` is given and `guest_uuid` is absent, the guest is derived from the reservation.
- When both are given and they disagree, the request fails with 422 `validation_failed` on `guest_uuid`.
- `room_uuid` is independent (not checked against the reservation).
- `conversation_id` is never set by staff create. It stays the chatbot link and is exposed as `conversation_uuid`.

Phase 6's queue row `room_number` for tickets becomes `ticket.room?.number` (it was always null). The `room` relation is eager-loaded in the queue index.

## 2. Lifecycle, single writers, permissions

**D-06: `TicketStatus` gains `IN_PROGRESS` and `WAITING_GUEST`.** stakes: medium
`active()` becomes `[OPEN, ASSIGNED, IN_PROGRESS, WAITING_GUEST]`, which feeds the queue, the summary and Phase 9's "open tickets" check.

Transition table, owned by the enum as `TicketStatus::allowedTransitions()`:

| From | To |
|---|---|
| `open` | `in_progress`, `resolved`, `closed` |
| `assigned` | `in_progress`, `waiting_guest`, `resolved`, `closed` |
| `in_progress` | `waiting_guest`, `resolved`, `closed` |
| `waiting_guest` | `in_progress`, `resolved`, `closed` |
| `resolved` | `closed`, `in_progress` (reopen) |
| `closed` | terminal |

Rules:
- **`assigned` is system-managed.** It is reachable only through assign, claim or escalate (from `open`). `PATCH …/status {status: assigned}` returns 422 `ticket_transition_invalid`.
- `reason` is **required** for any move to `closed` from a status other than `resolved`, and for the reopen `resolved → in_progress`. A missing reason returns 422 `validation_failed`, raised in the action because it depends on the from-state.
- Side effects:
  - `→ in_progress` on an unassigned ticket self-assigns the actor (Phase 6 D-05 parity).
  - `→ resolved` stamps `resolved_at`.
  - Reopen clears `resolved_at`.
  - `→ closed` stamps `closed_at`.

Rationale: this matches the dashboard vocabulary exactly (the mock's `critical`/`stage` stay client-side) and has no dead ends apart from `closed`.

**D-07: Single writers. The queue ticket arm delegates to them.** `convene: true` · stakes: high
New actions: `CreateTicketAction`, `UpdateTicketStatusAction`, `AssignTicketAction`, `EscalateTicketAction`, `ReplyToTicketAction`, `RecordTicketRecoveryAction`.

Each one:
- runs `DB::transaction(fn, 3)`
- does `Ticket::whereKey()->lockForUpdate()`
- re-reads the status under the lock
- writes the ticket row and its `ticket_actions` row in the same transaction.

The ticket arms of Phase 6's widened `UpdateRequestStatusAction` and `AssignRequestAction` **delegate** to `UpdateTicketStatusAction`/`AssignTicketAction`, passing the actor and reason through — same pattern as Phase 6's D-13 housekeeping arm. As a result `/operations/queue/tickets/{uuid}/status` and `/support-tickets/{ticket}/status` share one transition table and write the same timeline.

**Council A2:** the ticket arm of `UpdateRequestStatusAction`/`AssignRequestAction` **requires a non-null `$actor`**; if absent it throws a `LogicException` (a programming error, not a domain/HTTP error — every real caller has an authenticated actor). Legacy null-actor callers (e.g. system-dispatched service-request or housekeeping paths) remain valid **only for the non-ticket arms**.

This is a deliberate tightening of the queue ticket arm: it previously accepted any status. Record it in the SUMMARY as an additive contract change (new 422 codes on an existing route).

**D-08: Assign semantics.** stakes: medium
Body is `{user_uuid}`.
- `open → assigned`, which writes an `assignment` action (`target_user_id`, `from/to` status).
- `assigned|in_progress|waiting_guest` swaps the assignee (an `assignment` action, status unchanged).
- `resolved|closed` returns 422 `ticket_closed {status}`.
- Assigning to the current assignee is a no-op 200 with no action row.

There is no unassign verb (deferred, as in Phase 6).

**D-09: Assignee eligibility is shared across every queue type.** `convene: true` · stakes: medium
New helper `App\Support\AssigneeEligibility::assert(User $target, string $workPermission)`. The target must meet all of these:
- `is_active`
- `type ∈ {staff, super_admin}`
- `$target->can($workPermission)`

Otherwise the request fails with 422 `assignee_not_eligible {user_uuid, required_permission}`.

The work permission comes from the Phase 6 `OperationsQueueType` registry's **status** permission:

| Queue type | Work permission |
|---|---|
| `service-requests` | `service_requests.update` |
| `tickets` | `tickets.respond` |
| `housekeeping-tasks` | `housekeeping.update` |

It is applied in `AssignTicketAction`, `EscalateTicketAction`, the SR arm of `AssignRequestAction`, and `AssignHousekeepingTaskAction` (Phase 6).

**Council A3:** the handoff must **list, per queue type, which existing roles become un-assignable** as a concrete table (e.g. `reception` holds only `service_requests.view` → not assignable to service requests; `kitchen`/`housekeeping` hold no `tickets.respond` → not assignable to tickets unless the preset widening in D-10 changes that). This is not documented as a generic "additive 422" — the handoff must name the affected role/queue-type pairs so the dashboard/QA team can predict who will start failing assignment.

Rationale: it is a bug today to assign work to a deactivated user, or to someone who cannot act on it. The change is additive (a new 422) and is listed in the handoff.

**D-10: Permissions: no new permission strings. Presets widened.** `convene: true` · stakes: high

Route gates:

| Route or action | Gate |
|---|---|
| `GET /support-tickets`, `GET /support-tickets/{ticket}` | `tickets.view` |
| `POST /support-tickets` (create) | `tickets.respond` |
| `PATCH …/status` | `tickets.respond` (same as the existing queue ticket status) |
| `POST …/reply` | `tickets.respond` |
| `POST …/recovery-actions` | `tickets.respond` |
| `POST …/escalate` | `tickets.respond` (line staff escalate *up*; they need not hold assign) |
| `PATCH …/assign` | `tickets.assign` |
| Claim (D-22) | the type's work permission |

Preset changes:
- `reception` += `tickets.view`, `tickets.respond`
- `concierge` += `tickets.view`, `tickets.assign`, `tickets.respond`
- `events` unchanged (already holds all three)
- `kitchen` and `housekeeping` unchanged. Maintenance-category tickets are owned by guest relations, who dispatch a Phase 6 housekeeping task. Granting housekeeping `tickets.view` would also open the chat inbox.

Permission count stays at Phase 6's 24 / 11 groups. Re-pin `RolePresetsTest` and `PermissionGuideAccuracyTest`, and assert that the kitchen/housekeeping presets are unchanged.

**Council A4 (`tickets.*` stays the guest-relations permission set — this is the PROJECT.md debt item):** Document the **full blast radius** of widening `reception`/`concierge` with `tickets.*`:
- `reception`/`concierge` gain **read of `/cms/conversations`** (guest chat PII) via `tickets.view|respond`.
- `reception`/`concierge` gain **read of `/cms/event-inquiries`** (RFP leads, contact PII, budgets) — per the council's review, this route is also gated on `tickets.*`. **VERIFIED by the Chair (2026-09-27)**: `backend/routes/api.php:518-526` gates index/show on `permission:tickets.view` and status/assign on `permission:tickets.assign`.
- `concierge` holding `tickets.assign` can therefore re-status/assign event inquiries too (verified, same routes).
- Pin all of this in `RolePresetsTest`.
- **PROJECT.md debt entry added** (see below): split into `support_tickets.*` if the owner objects to this coupling once it's visible in review.

Rationale: this follows PITFALLS #9 (coarse, role-shaped permissions). Money does not move through tickets (D-15), so a `tickets.recover` permission would guard nothing. This matches the dashboard personas, which already show "chat" for both reception and concierge.

**D-11: Department on create.** stakes: low
`department` = body `department` (a `Department` enum value) ?? `category->department()` ?? `CONCIERGE`, computed before insert. This is the same rule as `RouteRequestAction`. Extract the category→department fallback into a pure static helper so both call it (discretion on the name).

**D-12: Priority vocabulary.** stakes: low
The body and filters take labels `low|normal|high`, mapped to 1/2/3 on write (add the inverse `ServiceRequestPriority::toTicketScale()`). Resources expose the label only, matching the queue row. There is no `critical` value: the dashboard maps its `critical` to `high`.

## 3. Create / list / show

**D-13: Routes and shapes.** stakes: medium
Routes, under `auth:users`, prefix `support-tickets`, `{ticket}` bound by uuid:
- `GET /support-tickets`: paginated through `paginatedSuccess`, with `TicketFilter`:
  - `status`, `department`, `source`, `category` eq/in
  - `priority` label eq/in
  - `assignee` = uuid | `unassigned` | `me`
  - `guest` uuid, `reservation` uuid
  - `escalated` bool (`escalation_level > 0`)
  - `created_at` gte/lte
  - Sortable by `created_at, updated_at, priority, status`. Default sort is `created_at desc, id desc`.
  - Includes resolved/closed tickets, unlike the queue.
- `GET /support-tickets/{ticket}`: the ticket plus `actions[]`, ordered `created_at asc, id asc`.
- `POST /support-tickets` → 201. Body: `{subject (3-150), description? (≤5000), category, priority?, department?, guest_uuid?, reservation_uuid?, room_uuid?}`. It writes a `created` action (`null → open`) and `created_by = actor`.

`TicketResource` fields:
- `uuid, subject, description, category, status, priority (label), department, source, escalation_level, allowed_statuses[]`
- `guest{uuid,name}|null`, `reservation{uuid,booking_code}|null`, `room{uuid,number}|null`, `conversation_uuid|null`
- `assigned_user{uuid,name}|null`, `created_by{uuid,name}|null`
- **`folio_credit_total_usd` (string 2dp, ledger-backed sum of `abs()` on linked `folio_credit` recoveries) and `recorded_value_usd` (string 2dp, sum of all recovery `amount_usd` regardless of type) — (council A7, replaces the consultant's single `recovery_total_usd` field)**
- `resolved_at, closed_at, created_at, updated_at`
- `actions[]` on show only

`TicketActionResource` fields: `uuid, type, body, from_status, to_status, actor{uuid,name}|null, target_user{uuid,name}|null, recovery{uuid,type,amount_usd,description,folio_item_uuid}|null, meta, created_at`. `message_id` is not exposed.

Query budgets: list ≤ 6 queries per page, show ≤ 6. Assert them with `expectsDatabaseQueryCount`.

## 4. Recovery actions (TICKET-06)

**D-14: `TicketRecoveryType`.** stakes: low
Values: `folio_credit, rate_discount, courtesy_amenity, room_upgrade, late_checkout, apology, other`. `folio_credit` and `courtesy_amenity` keep the dashboard's names. The mock's `transport_hold` maps to `other` in the handoff.

**D-15: Recovery is record-only. A monetary recovery *links* an existing Phase 5 credit and never posts one.** `convene: true` · stakes: high
Body: `{type, description (3-1000), amount_usd? (decimal:0,2, 0-99999.99), folio_item_uuid?}`.

- **`folio_credit`** requires `folio_item_uuid` and forbids a body `amount_usd` that differs. `amount_usd` is copied from `abs(folio_item.amount_usd)`. The item must meet all of these:
  - `source_type = credit`
  - it belongs to a folio of `ticket.reservation`, or, when the ticket has no reservation, to a folio whose reservation's `guest_id = ticket.guest_id`
  - it is not already linked

  A violation returns 422 `ticket_recovery_folio_invalid {folio_item_uuid, reason: not_credit|other_stay|already_linked}`. The unique index backstops `already_linked` under a race: catch `UniqueConstraintViolationException` and map it.
  **Council A7 adds a fourth reason: `no_stay`** — 422 `ticket_recovery_folio_invalid {reason: no_stay}` when a `folio_credit` recovery is attempted on a ticket that has **neither** a reservation **nor** a guest (there is no folio to look up against at all — a distinct, earlier-failing case than `other_stay`).
- **Other types**: `amount_usd` is an optional recorded value (for example an upgrade's rack-rate difference). No folio write happens. `folio_item_uuid` is prohibited.
- Allowed on any status except `closed` (422 `ticket_closed`). Compensation after resolution is normal. The action takes the ticket lock and writes the `recovery` action and the `ticket_recoveries` row together.

**Council A7, resource/testing notes:**
- `TicketResource` exposes `folio_credit_total_usd` (ledger-backed, sum of `abs()` on credit rows) and `recorded_value_usd` (all recoveries, any type) — see D-13. Phase 9 note: only `type = folio_credit` is ledger-backed; other types are informational totals, not reconcilable against the folio.
- Tests must assert the `abs()` on the negative credit row (folio credits are stored as negative amounts; the recovery records the positive/absolute value).
- No delete route exists for tickets or recoveries — confirm this in tests (a recovery, once linked, is permanent audit trail).

The dashboard flow for a credit has two steps:
1. `POST /cms/folios/{folio}/line-items {kind: credit, reason: "Ticket TK…", …}` with an `Idempotency-Key`, which needs `folios.post`.
2. `POST /support-tickets/{ticket}/recovery-actions {type: folio_credit, folio_item_uuid}`.

Rationale: the folio stays single-writer (floors, the bcmath ledger, idempotency, `folios.post`). A second money writer through tickets would need its own Idempotency-Key and permission and would duplicate Phase 5 guards. Duplicate non-monetary records are harmless, so there is no Idempotency-Key on this route. The unique link is the idempotency guard for the money case.

## 5. Replies (TICKET-05)

**D-16: `POST /support-tickets/{ticket}/reply {body (1-5000)}` writes a `reply` action. Internal only, never sent to the guest.** stakes: medium
- There is no separate `note` type or route: the route name is the contract, and every reply in this milestone is internal.
- When TICKET-08 lands, a mirrored reply is exactly one whose `message_id` is non-null, so historical rows stay unambiguous.
- A reply is allowed on any status except `closed` (422 `ticket_closed`).
- A reply does **not** change status (unlike the mock's open → in_progress).
- The response is the refreshed ticket with `actions[]`, status 201.

**D-17: Chatbot tickets with a conversation.** stakes: low
There is no reply routing. The resource exposes `conversation_uuid`. The guide says: to answer the guest, use the existing `POST /cms/conversations/{conversation}/messages`, then optionally log a ticket reply. The guide and the dashboard handoff state that ticket replies are not guest-visible.

## 6. Escalation (TICKET-07)

**D-18: `POST /support-tickets/{ticket}/escalate {user_uuid, reason (3-1000)}`.** stakes: medium
- The target is explicit. There is no "department manager" concept, and the dashboard picks from `GET /operations/staff?type=tickets`.
- The target must pass `AssigneeEligibility` with `tickets.respond`.
- Effects:
  - `assigned_user_id := target`
  - `escalation_level++`
  - `open → assigned` (other active statuses unchanged)
  - one `escalation` action (`target_user_id`, `body = reason`, `meta {level, previous_assignee_uuid}`)
- The timestamp is the action's `created_at`, exposed as the latest escalation in the resource.
- Only active statuses can be escalated. `resolved` or `closed` returns 422 `ticket_closed`.
- The body `level` is ignored: the server derives it. The mock's `level` becomes display-only.

**D-19: Loop guards.** `convene: false` · stakes: medium
- Target = actor returns 422 `ticket_escalation_invalid {reason: self}`.
- Target = current assignee returns 422 `ticket_escalation_invalid {reason: same_assignee}`.
- Level cap `config('hotel.ticket_max_escalation_level', 3)`: at the cap the request fails with 422 `ticket_escalation_limit {level, max}`.
- A→B→A is allowed within the cap. The cap is the termination guarantee.
- There is no scheduled or auto escalation (PITFALLS #5): escalation fires only on an explicit request under the ticket lock.

**D-20: Notifications.** stakes: low
There is no staff push or in-app notification this phase (no staff channel exists). The live signal is the ops-queue Firestore mirror (D-21): `assigned_user_uuid` changes, and the dashboard filters `assignee=me`. Staff notifications are deferred.

**D-21: Mirroring.** stakes: medium
This follows the Phase 6 D-11b pattern. The ticket single writers that change a queue row (create, status, assign/claim, escalate) dispatch `TicketChanged` (`ShouldDispatchAfterCommit`). A queued `MirrorTicketToFirestore` handles it and writes `ops_queue/ticket_{uuid}` through `OperationsQueueMirror`. Reply and recovery do not dispatch, because the queue row is unchanged. The queue ticket arm stops mirroring inline because the writers own it.

## 7. Claim (OPS-01)

**D-22: `PATCH /operations/queue/{type}/{uuid}/claim` (no body) for all three registry types.** `convene: true` · stakes: high
- **Permission:** the type's **work** permission (`service_requests.update | tickets.respond | housekeeping.update`), **not** assign. Claiming takes work for yourself, and kitchen/housekeeping line staff hold update but not assign. Checked in-service like assign/status; failure returns 403.
- **Semantics, evaluated under the row lock:**
  - unassigned → assign to the actor through the type's single assign writer, with the same side effects as assign. For a ticket, `open → assigned` plus an `assignment` action with `meta.claim = true`. For a housekeeping task, `pending → assigned` plus history. For a service request, status is unchanged.
  - Assigned to the actor → **200 no-op**, with no action or history row (idempotent retry), and message `custom.messages.queue_item_already_yours`.
  - Assigned to someone else → **409 `queue_item_already_claimed {assigned_user_uuid}`**. A claim never silently overrides. Supervisors reassign with `PATCH …/assign`.
  - Terminal status (from the registry's open-status list) → **the type's own existing closed/transition error code, not a new generic code — see council A1 below.**
- **Locking:** the three assign writers gain a `bool $claim = false` parameter. The unassigned/self/other check runs **inside** each writer's existing lock, so the guard and the write are serialized:
  - The ticket row lock.
  - The housekeeping room → task lock order (Phase 6 D-05).
  - The service-request arm gains `lockForUpdate()`; it has none today.

  `OperationsQueueService::claim()` resolves the item through the registry and delegates. There is no separate conditional UPDATE.
- The claimer implicitly passes eligibility, because the work permission was checked. `is_active` is guaranteed by auth.
- The response is `OperationsQueueItemResource` with status 200.

**Council A1 (crux amendment — overrides the consultant's `queue_item_closed` code):** **Drop the new `queue_item_closed` code entirely.** Claim delegates to each type's writer under its lock, and the writer's *existing* closed/terminal-status code passes through unchanged instead: `ticket_closed` for tickets, `housekeeping_task_closed` for housekeeping tasks. Document these per-queue-type codes explicitly in the API guide's claim section (do not imply one shared code). The service-request arm — which has no closed/transition exception today because nothing writes booking-adjacent SR status guards yet — gains `lockForUpdate()` **and** a closed check: reuse an existing SR closed/transition exception if the codebase already has one; otherwise add `service_request_closed` as a new additive 422 code (five locales), used by both the SR claim path and, going forward, any other SR terminal-status guard.

> **Note on a genuine contradiction between the two source documents:** the consultant's D-22 text and its own D-25 error-code table both list `queue_item_closed` as a real 422 code with context `{type, status}`. Council amendment A1 explicitly removes it. A1 is binding per the task instructions ("where an amendment overrides the consultant text, the amendment wins"), so **`queue_item_closed` does not exist in this phase** — every closed-item response uses the claimed item's own type-specific code. This is called out again under D-25.

**Council A8:** implement claim as **per-type adapters behind a `ClaimGuard`** (or an equivalent small dispatch shape) rather than one big conditional in the service — Claude's discretion on the exact shape, but it must run inside each writer's lock either way (matches the consultant's own "Claude's Discretion" note on this point).

**Council A9 (caveat, record in the guide like Phase 5):** SQLite's `lockForUpdate()` is a documented no-op. Claim-race serialization is **MySQL-only** in practice; `RecordsRowLocks`-style test helpers can only prove the SQL contains `FOR UPDATE`, not that SQLite actually serializes concurrent claims. Do not claim race-safety is verified by the test suite alone.

## 8. Staff list (OPS-02)

**D-23: `GET /operations/staff`.** `convene: true` · stakes: medium
- **Gate:** the same as the queue index (`service_requests.view|tickets.view|housekeeping.view`).
- **Query:**
  - `type` ∈ registry segments → filter to holders of that type's work permission (the dashboard's normal use).
  - `permission` → **restricted to the three queue registry work permissions only** (`service_requests.update|tickets.respond|housekeeping.update`) — **council A5 overrides the consultant's original "any `exists:permissions,name` on guard `users`"**. An unrecognized or out-of-set value returns 422, not a lookup against the full permission table.
  - `department` → a `Department` enum value.
  - `search` → name `like`.
- **Rows:** `is_active = true` and `type ∈ {staff, super_admin}`. Super admins match any permission/type filter (Gate::before parity), but are excluded when `department` is given unless they hold that role.
- **Department (no column exists):** users holding the role whose name equals the `Department` value. `sales` and `maintenance` return empty (documented). A `users.department` column is deferred.
- **Response:** unpaginated `data.items` (cap 200, `name asc`) + `meta {count, truncated}`. Row: `{uuid, name, type, departments[]}` — **council A5 drops `roles[]` from the consultant's original row shape**; only `type` and `departments[]` (array, tested as an array in every case including zero/one match) are returned. No email and no permission dumps. Document that `departments` derives from role names, so a role rename is a silent filter/response change.
- **Budget:** ≤ 4 queries (users, roles eager, permission-cache reads), asserted.
- **Path:** `/operations/staff`, as the requirement states. The dashboard moves off `/operations/queue/staff`. No alias route.

## 9. Queue path & OPS-03

**D-24: The dashboard adopts `{queue_type}/{uuid}` and the backend adds `queue_type`.** stakes: medium
Every queue row gains `queue_type` = the URL segment (`service-requests|tickets|housekeeping-tasks`), read from the `OperationsQueueType` registry. The existing `type` (`service_request|ticket|housekeeping_task`) is unchanged, which keeps the change additive.

The Phase 6 `allowed_statuses[]` for ticket rows now comes from `TicketStatus::allowedTransitions()` minus `assigned`. No `{id}` alias routes are added — the no-alias policy holds: the dashboard builds `/operations/queue/{queue_type}/{uuid}/…`.

The queue ticket eager load adds `room` for `room_number`.

## 10. Error codes, docs, tests

**D-25: New error codes (five locales, one exception each).**

| Code | Status | Context |
|---|---|---|
| `ticket_transition_invalid` | 422 | `{from, to, allowed}` |
| `ticket_closed` | 422 | `{status}` |
| `ticket_escalation_invalid` | 422 | `{reason}` |
| `ticket_escalation_limit` | 422 | `{level, max}` |
| `ticket_recovery_folio_invalid` | 422 | `{folio_item_uuid, reason}` — reasons: `not_credit\|other_stay\|already_linked\|no_stay` (the last is **council A7**) |
| `assignee_not_eligible` | 422 | `{user_uuid, required_permission}` |
| `queue_item_already_claimed` | 409 | `{assigned_user_uuid}` |
| ~~`queue_item_closed`~~ | ~~422~~ | **Removed — council A1.** Claim's terminal-status response reuses the claimed item's own type-specific code instead (`ticket_closed`, `housekeeping_task_closed`, or the new `service_request_closed` if the SR side needs one — see D-22/A1). |

Unknown ticket or type returns 404 `not_found`, as it does today. There are also message keys for ticket created, status updated, assigned, replied, recovery recorded, escalated, queue item claimed, and already yours. stakes: medium

**D-26: Docs and contract.** stakes: medium
- `API_GUIDE_DASHBOARD.md`: a new **Support Tickets** module covering:
  - the lifecycle table
  - that `assigned` is system-managed
  - that replies are internal
  - the two-step folio credit
  - escalation cap and loops
  - filters and shapes

  The **Operations Queue** module is edited for:
  - claim, **with the per-queue-type closed codes documented explicitly, not a shared `queue_item_closed` (council A1)**
  - `queue_type`
  - the ticket arm now enforcing transitions
  - assignee eligibility on all assign verbs, **including the per-queue-type "who becomes un-assignable" table (council A3)**
  - `/operations/staff`, **including the restricted `permission` filter values and the `departments[]`-not-`roles[]` response shape (council A5)**
  - new ticket statuses in `/dashboard/summary`
  - the SQLite `lockForUpdate()` caveat for claim races (council A9)

  The permission catalogue updates the reception/concierge presets and adds a note that `tickets.view` also opens the chat inbox **and, per council A4, document the `/cms/event-inquiries` exposure (verified, see D-10/A4 note)**.
- The mobile guide and changelog are untouched (there are no guest routes).
- Postman gets a folder "Support Tickets & Queue".
- Tree flips:
  - "support tickets" → `api:true` with the 8 real endpoints.
  - "live queue" → `api:true`, with meta "Firestore mirror; websocket deferred (RT-01)" and the claim/staff eps added. Its note (`{id}` vs `{type}/{uuid}`) is removed.
  - The "Operations" section stays `partial` because "reports" is Phase 9.
- Dashboard handoff renames:
  - `owner` → `user_uuid`
  - escalate `{target_owner, level}` → `{user_uuid, reason}`
  - recovery `action_type/detail/amount/currency` → `type/description/amount_usd` (USD only)
  - `critical` → `high`
  - staff path
  - claim no longer moves the item to `in_progress`
- The SUMMARY lists: `[BLOCKING] migrate + seed RolesAndPermissionsSeeder`, the queue worker for the ticket mirror, `HOTEL_TICKET_MAX_ESCALATION_LEVEL` (config + `.env.example`), and the additive contract tightenings (queue ticket status transitions, assignee eligibility, dropped `queue_item_closed` in favor of per-type codes).

**D-27: Tests.** stakes: medium
- Feature tests in `tests/Feature/Tickets/`:
  - `TicketIndexTest`: every filter, query budget, 401/403
  - `TicketShowTest`
  - `TicketCreateTest`: source forced, department fallback, guest derived from the reservation, mismatch 422
  - `TicketStatusTest`: full transition matrix, `assigned` rejected, reason required, stamps, self-assign
  - `TicketAssignTest`: eligibility (inactive, no permission, super admin OK), no-op self
  - `TicketReplyTest`: closed 422, no status change, `message_id` null
  - `TicketRecoveryTest`: folio_credit link happy path, `not_credit|other_stay|already_linked|no_stay` (the last per council A7), amount mismatch, non-money types, closed, `abs()` on the negative credit row, no delete route exists
  - `TicketEscalateTest`: self, same_assignee, cap, open → assigned, level++
- Feature tests in `tests/Feature/Operations/`:
  - `QueueClaimTest`: all 3 types, 409 on a second claim, 200 no-op on self, terminal-status per-type codes (**not** `queue_item_closed` — council A1), 403 with only assign or without the work permission, row-lock assertion via `RecordsRowLocks` (documented as MySQL-only proof per council A9), HK lock order room → task
  - `OperationsStaffTest`: type/permission/department/search, permission filter rejects non-work-permission values (422, council A5), `departments[]` asserted as an array not `roles[]`, super admin rules, inactive excluded, query budget, 401/403
  - `OperationsQueueTicketArmTest`: the queue status delegates to transitions and writes the timeline, `queue_type` on rows, ticket `room_number`, `LogicException` when the ticket arm is called with a null actor (council A2)
  - **`DeactivatedTokenClaimTest` (council fact-resolution regression):** deactivating a user revokes tokens; the old bearer token gets 401 on the claim route.
- Unit tests: `TicketStatus` transition table, `UpdateTicketStatusAction`, `AssigneeEligibility`.
- Re-pin `OperationsQueueTest`, `RolePresetsTest` (**including the widened blast-radius pins from council A4**), `SeederTest`, `PermissionGuideAccuracyTest`. Add a Firestore mirror fake assertion for `TicketChanged`.
- Every new route has happy/401/403/422 tests. The suite is green.

---

## Roadmap wording fixes (Phase 7) — applied to ROADMAP.md and REQUIREMENTS.md

- **SC-2:** add "`assigned` is set only by assign/claim/escalate; a status PATCH to `assigned` returns 422 `ticket_transition_invalid`". Replace "record a service-recovery action (type, amount/description)" with "…record a service-recovery action (type, description, optional amount; a folio credit is posted through the Phase 5 folio endpoint and linked by `folio_item_uuid`)".
- **SC-3:** "returns a domain error" → "returns 409 `queue_item_already_claimed`; re-claiming your own item is a 200 no-op; claim requires the type's work permission (`service_requests.update|tickets.respond|housekeeping.update`)".
- **SC-4:** "Every queue item carries its `type`" → "Every queue item carries `queue_type` (the URL segment) alongside `type`".
- **SC-5:** "new ticket/operations permissions are seeded" → "no new permission strings; the `reception` and `concierge` presets gain ticket permissions (listed in the summary)".
- **Reuses:** add `AssignHousekeepingTaskAction` (claim), `OperationsQueueType` registry, the Phase 5 credit path via `POST /cms/folios/{folio}/line-items`, `RecordsRowLocks`, `OperationsQueueMirror`. `AssignRequestAction` is reused as the delegating arm, not as the ticket writer.
- **REQUIREMENTS OPS-03:** "…expose their `queue_type` path segment so the dashboard can build `{queue_type}/{uuid}` paths".

These fixes have been applied directly to `.planning/ROADMAP.md` (Phase 7 section) and `.planning/REQUIREMENTS.md` (OPS-03) as part of producing this CONTEXT.md — see the diffs in those files. No other requirement lines were touched, since no other stated contract in those two files disagreed with the decisions above.

## Claude's Discretion

- Class, file and method names (`App\Actions\Tickets\*`, `App\Services\Tickets\TicketService`, `TicketFilter`, `Admin\SupportTicketController`, `Admin\OperationsStaffController`, requests under `Http/Requests/Tickets/`), factories (`TicketActionFactory`, `TicketRecoveryFactory`), and resource key order.
- Where the `$claim` guard lives: a parameter on each writer, or a small `ClaimGuard` the writers call (council A8 requires the adapter shape conceptually; exact class layout is discretionary). It must run inside the writer's lock either way.
- The name of the category→department helper. Whether `created`/`status_change` rows store the reason in `body` or `meta`: pick one and document it.
- Cap on `actions[]` in show (all, or the last 200 with a `meta.actions_truncated` flag).
- Lang wording, Postman layout, and the `DemoShowcaseSeeder` additions (staff tickets, a sample timeline).

## Deferred

- TICKET-08 guest-visible replies (`message_id` mirroring). `guest_app` ticket source and guest ticket creation.
- Staff notifications (in-app or FCM) on escalation or assignment. SLA/due-at and auto-escalation jobs. `critical` priority.
- An unassign verb. Assign-at-create. A `users.department` column. Workload counts (`open_items_count`) on the staff list.
- Recovery that posts money directly (a single call with an Idempotency-Key). Recovery totals in reports (Phase 9 may read `ticket_recoveries`).
- Reopening `closed` tickets. Ticket merge/duplicate linking. Attachments.
- RT-01 websocket queue. SQL UNION queue.
- Per-request `EnsureUserIsActive` middleware (council Skeptic dissent, rejected — deactivation already revokes tokens; covered by the D-27 regression test instead).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Conventions (hard gate)
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` + `references/developer-guide.md`; `.claude/skills/{laravel-conventions,module-slice,test-discipline,naive-reviewer}/SKILL.md`
- `.planning/codebase/CONVENTIONS.md` (Phase Summary Contract); Phase 6 `SUMMARY.md`s and `06-CONTEXT.md`/`06-DISCUSSION-LOG.md` (the `OperationsQueueType` registry, the widened `AssignRequestAction`/`UpdateRequestStatusAction`, housekeeping single writers, `RecordsRowLocks`, `OperationsQueueMirror`, room→task lock order precedent)
- `.planning/research/PITFALLS.md` (permission sprawl #9, no scheduled/auto side effects #5), `.planning/research/ARCHITECTURE.md`

### Existing code this phase extends
- `backend/app/Models/Ticket.php` + migration; `backend/app/Enums/{TicketStatus,TicketSource,TicketCategory}.php`
- `backend/app/Actions/Operations/{AssignRequestAction,UpdateRequestStatusAction,RouteRequestAction}.php`, `backend/app/Services/Operations/OperationsQueueService.php`, `backend/app/Http/Controllers/Admin/OperationsQueueController.php`, `backend/app/Http/Requests/Operations/UpdateRequestStatusRequest.php`, `backend/app/Support/OperationsQueueMirror.php`, `backend/app/Support/OperationsQueueType.php` (Phase 6), `backend/app/Listeners/MirrorServiceRequestToFirestore.php`
- Phase 6's `App\Actions\Housekeeping\{AssignHousekeepingTaskAction,UpdateHousekeepingTaskStatusAction}` (claim/lock-order precedent), `App\Models\HousekeepingTask`
- `backend/app/Models/{Guest,Reservation,Room,FolioItem}.php`, `backend/app/Actions/Folio/*` (read-only reuse: linking, never posting)
- `backend/database/seeders/RolesAndPermissionsSeeder.php`, `backend/app/Http/Controllers/Admin/StaffController.php` / `StaffPolicy.php` (contrast with the new unpaginated `/operations/staff`)
- `backend/routes/api.php` (`operations/queue/{type}/{uuid}`, `/dashboard/summary`, `cms` conversations/event-inquiries — event-inquiries gate verified at lines 518-526 (tickets.view / tickets.assign))

### API contract & docs
- `backend/docs/API_GUIDE_DASHBOARD.md` (Operations Queue module; new Support Tickets module), `backend/docs/CHANGELOG_MOBILE_API.md` (untouched, confirm), `backend/docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html`

### Planning artifacts
- `.planning/REQUIREMENTS.md` TICKET-01..08, OPS-01..03 (TICKET-08 out of scope); `.planning/ROADMAP.md` Phase 7 (wording fixed per this file); `.planning/PROJECT.md` Key Decisions (debt entry added per council A4)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- Phase 6's `OperationsQueueType` registry (model class, permission map, status enum, mirror id prefix, open-status list) — Phase 7 adds `tickets` and widens every read site (`resolve()`, `requiredPermission()`, `index()`, `summary()`, mirror) rather than forking it.
- `RecordsRowLocks` test helper (Phase 6 precedent) — proves `FOR UPDATE` appears in the SQL; does not prove SQLite serializes (council A9 caveat).
- `AssignHousekeepingTaskAction` / `UpdateHousekeepingTaskStatusAction` as the shape for the new ticket single writers (lock → re-read → write row + history in one transaction).
- `OperationsQueueMirror` (`documentId`, `payload`) for `MirrorTicketToFirestore`.
- Phase 5's folio line-item posting (`POST /cms/folios/{folio}/line-items`, `folios.post`, Idempotency-Key, bcmath ledger) — reused by **linking**, never by a second writer.

### Established Patterns
- Status + timestamped history/timeline table written in one transaction; row locks in a fixed order; domain exceptions with `error_code` + context; five-locale keys; real-bearer-token tests; `expectsDatabaseQueryCount` bounds on service paths; events after commit; listeners auto-discovered.
- Single writer per entity; polymorphic queue actions delegate to that writer rather than duplicating transition logic (Phase 6 D-13 precedent, extended here to tickets in D-07).
- `logExcept()` on models carrying free-text guest complaints, to keep PII/complaint text out of `activity_log` diffs (council A6 applies this to `Ticket.description`).

### Integration Points
- `routes/api.php`: new `support-tickets` group; widened `operations/queue/{type}/{uuid}/claim`; new `operations/staff`.
- `RolesAndPermissionsSeeder`: preset widening only, no new permission strings.
- `DemoShowcaseSeeder`: add staff-created tickets and a sample timeline (discretion).

</code_context>

<specifics>
## Specific Ideas

- Claim, status, assign and escalate all take the same row lock their type's single writer already uses — there is no separate "claim lock" or conditional UPDATE, so a claim can never race a status change into an inconsistent state.
- The ticket timeline (`ticket_actions`) is the one place history is read from; the `tickets` table only carries current state plus the two "have we ever" markers (`resolved_at`, `closed_at`, `escalation_level`) needed for filtering.

</specifics>

<deferred>
## Deferred Ideas

(see the `Deferred` list inside `<decisions>` above — kept in one place per this phase's consultant file rather than duplicated)

</deferred>

---

*Phase: 07-support-tickets-queue*
*Context gathered: 2026-09-27*
*Gate: research/planning begins only after Phase 6 is committed and green (council A10).*

## Post-research consultant decisions (2026-09-27) — override earlier text

Fable consultant, after 07-RESEARCH.md; verified against `routes/api.php` and `RolesAndPermissionsSeeder`.

- **PR-1 (research Q1):** `service_request_closed` (new 422, five locales) applies to BOTH the plain service-request assign route and the queue claim. Terminal set = the registry's open-status complement. Record in SUMMARY as an additive 422 on an existing route. Confidence high.
- **PR-2 (research Q2, D-18):** ticket show response gains `latest_escalation {level, target_user{uuid,name}, reason, created_at} | null`, derived from the loaded `actions[]` (no extra query). The list omits it (`escalation_level` + `escalated` filter suffice). Appended to D-13's field list as additive.
- **PR-3 (research Q3):** `actions[]` on show = newest 200, returned ascending, plus top-level `actions_truncated: bool`. Load via a separate ordered query `limit(201)`, not per-parent eager `limit()`.
- **PR-4 (correction a, D-10):** permission catalogue is 26 permissions / 11 groups (after Phase 6). D-10's "24" is a slip. Phase 7 adds no permission strings; pin 26.
- **PR-5 (correction b, A3):** reception keeps service-request assignability (holds `service_requests.update`); reception becomes un-assignable to housekeeping tasks (holds `housekeeping.assign` without `housekeeping.update`). The handoff table uses this.
- **PR-6 (correction c, A4):** accepted write widening — `tickets.respond` gates `POST /cms/conversations/{c}/messages`, so reception/concierge can reply to guest chat. Pin read AND reply in `RolePresetsTest`; named in the PROJECT.md debt entry. Logged amendment to A4, no re-convene.
- **PR-7 (correction d, D-09):** `AssigneeEligibility` and the ~12 existing test re-pins land in the same plan, with a `UserFactory` eligible-assignee state.
- **PR-8 (correction e):** query budget stays 6 (list) / 6 (show). Use a batched users query and `withSum`/subselects; if `withSum` on HasManyThrough fails, fall back to an `addSelect` subquery. Do not raise the budget.
