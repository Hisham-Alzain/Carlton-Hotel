---
phase: 07-support-tickets-queue
status: complete
completed: 2026-10-02
requirements-completed: [TICKET-01, TICKET-02, TICKET-03, TICKET-04, TICKET-05, TICKET-06, TICKET-07, OPS-01, OPS-02, OPS-03, DOCS-01, XCUT-01]
---

# Phase 7 — Support Tickets & Queue: Summary

Staff run the full support-ticket lifecycle and route operations-queue work to the right colleague. Tickets have an append-only timeline (`ticket_actions`) and record-only service recovery (`ticket_recoveries`). A folio credit is posted through the Phase 5 folio route and linked, never posted twice. Every ticket write goes through a single writer that takes a row lock; ticket changes reach the ops queue through a queued Firestore mirror.

The operations queue gains a claim verb for all three queue types (evaluated inside each type's assign lock), assignee eligibility on every assign verb, `queue_type` on every row, and a bounded staff directory for the assignee picker. Reception and concierge gain ticket permissions. No permission string was added.

## Endpoints delivered

All routes are `auth:users`.

| Method | Path | Permission |
|---|---|---|
| GET | `/api/support-tickets` | tickets.view |
| POST | `/api/support-tickets` | tickets.respond |
| GET | `/api/support-tickets/{ticket}` | tickets.view |
| PATCH | `/api/support-tickets/{ticket}/status` | tickets.respond |
| PATCH | `/api/support-tickets/{ticket}/assign` | tickets.assign |
| POST | `/api/support-tickets/{ticket}/reply` | tickets.respond |
| POST | `/api/support-tickets/{ticket}/recovery-actions` | tickets.respond |
| POST | `/api/support-tickets/{ticket}/escalate` | tickets.respond |
| PATCH | `/api/operations/queue/{type}/{uuid}/claim` | in service: the type's work permission |
| GET | `/api/operations/staff` | service_requests.view \| tickets.view \| housekeeping.view |

No DELETE under `/support-tickets` (405 `method_not_allowed`).

## Waves

07-01 domain foundation · 07-02 `AssigneeEligibility` + hardened SR/HK assign · 07-03 create + show + mirror · 07-04 list/filter · 07-05 status + assign writers · 07-06 reply + escalation · 07-07 record-only recovery · 07-08 queue ticket arms, `queue_type` · 07-09 queue claim (`ClaimGuard`, 409) · 07-10 `/operations/staff`, preset widening, assignability matrix · 07-11 docs, Postman, tree, gate. See each `07-NN-SUMMARY.md`.

## Key decisions (sources in 07-CONTEXT.md)

- **Timeline (D-01, A6, A8):** `ticket_actions` canonical, append-only (update/delete throw); `meta` keys whitelisted per type; `message_id` reserved, never written/exposed; `Ticket.description` excluded from activity log.
- **Lifecycle (D-06..D-08):** six statuses with explicit transition table; `assigned` only via assign/claim/escalate; reason required to close from non-resolved and to reopen; `resolved_at`/`closed_at` stamped; in_progress self-assigns an unassigned ticket; each writer locks and re-reads the row.
- **Eligibility (D-09, PR-7):** active, staff/super_admin type, holds the queue type's work permission; applies to ticket assign/escalate, SR assign, HK assign. Claim skips it.
- **Presets (D-10, A4, PR-4, PR-6):** reception + `tickets.view|respond`; concierge + `tickets.view|assign|respond`. Catalogue stays 26 / 11.
- **Create (D-04, D-05, D-11, D-12):** source forced `staff`; department body → category helper → concierge; guest derived from reservation (mismatch 422); priority labels ↔ 1/2/3.
- **List/show (D-13, PR-2/3/8):** both pinned at 6 queries; show returns newest 200 actions ascending + `actions_truncated` + show-only `latest_escalation`; A7 totals `folio_credit_total_usd` and `recorded_value_usd`.
- **Recovery (D-14, D-15, A7):** record-only; `folio_credit` links an existing negative folio line by `folio_item_uuid`; reasons `no_stay|not_credit|other_stay|already_linked`; stored amount is absolute.
- **Reply (D-16/17):** internal only; no status change, no chat message; guests answered via `/cms/conversations/{c}/messages` using the exposed `conversation_uuid`.
- **Escalation (D-18..D-20):** body `{user_uuid, reason}`; server-derived level; guard order closed, self, same_assignee, cap (`HOTEL_TICKET_MAX_ESCALATION_LEVEL`, default 3), eligibility; no notifications.
- **Mirroring (D-21):** `TicketChanged` after commit → queued `MirrorTicketToFirestore`; reply/recovery don't dispatch.
- **Claim (D-22, A1, A8, A9, PR-1):** closed check → `ClaimGuard` → assign under lock. Own item: 200 no-op; other's item: 409 `queue_item_already_claimed {assigned_user_uuid}`; terminal: per-type closed code. No shared closed code.
- **Staff directory (D-23, A5):** whitelisted `permission` filter, `departments[]`, no `roles[]`, cap 200, 4 queries; mock `/operations/queue/staff` not aliased.
- **Queue path (D-24, A2):** `queue_type` = URL segment; ticket arm requires an actor (`LogicException` otherwise).

## Permissions (XCUT-01)

No new permissions. Preset changes:

| Permission | Preset change | Routes it newly opens | Notes |
|---|---|---|---|
| tickets.assign | + concierge | `PATCH /support-tickets/{t}/assign`; `PATCH /cms/event-inquiries/{i}/status`, `/assign` | event-inquiry triage exposure (A4) |
| tickets.respond | + reception, + concierge | ticket create/status/reply/recovery/escalate; `POST /cms/conversations/{c}/messages`; ticket claim | PR-6 |
| tickets.view | + reception, + concierge | ticket reads; `GET /cms/conversations*`; `GET /cms/event-inquiries*`; ticket rows in queue; summary blocks; `/operations/staff` | |

Blast radius pinned in `RolePresetsTest::test_ticket_preset_blast_radius`. Kitchen and housekeeping stay without `tickets.*` (pinned 403). PROJECT.md debt entry present (split into `support_tickets.*` on objection). Catalogue unchanged: 26 / 11.

## Assignability after Phase 7 (A3, PR-5)

Source: `AssignabilityMatrixTest`.

| Queue type (work permission) | Assignable presets | Newly un-assignable vs Phase 6 |
|---|---|---|
| service-requests (service_requests.update) | reception, kitchen, housekeeping, concierge, super admin | events, content_editor, content_manager |
| tickets (tickets.respond) | events, reception, concierge, super admin | kitchen, housekeeping, content_editor, content_manager |
| housekeeping-tasks (housekeeping.update) | housekeeping, super admin | reception (holds assign without update, PR-5), kitchen, concierge, events, content_* |

Inactive users and non-staff types are never assignable.

## Dashboard & App Path Changes (DOCS-01)

| Client | Method | Path | Change |
|---|---|---|---|
| dashboard | GET | `/dashboard/summary` | additive: tickets block counts `in_progress`, `waiting_guest` |
| dashboard | PATCH | `/housekeeping/tasks/{task}/assign` | additive 422 `assignee_not_eligible` |
| dashboard | GET | `/operations/queue` | additive: `queue_type`, ticket `room_number`, new ticket statuses, enforced `allowed_statuses` |
| dashboard | PATCH | `/operations/queue/{type}/{uuid}/assign` | additive 422: `assignee_not_eligible`, `service_request_closed`, `ticket_closed` |
| dashboard | PATCH | `/operations/queue/{type}/{uuid}/status` | additive 422: tickets enforce `ticket_transition_invalid` + reason rules; timeline written |
| dashboard | PATCH | `/operations/queue/{type}/{uuid}/claim` | added |
| dashboard | GET | `/operations/staff` | added (replaces mock `/operations/queue/staff`) |
| dashboard | GET/POST | `/support-tickets` | added |
| dashboard | GET | `/support-tickets/{ticket}` | added |
| dashboard | PATCH | `/support-tickets/{ticket}/status`, `/assign` | added |
| dashboard | POST | `/support-tickets/{ticket}/reply`, `/recovery-actions`, `/escalate` | added |
| app | — | — | none |

No existing path, field or error_code removed or renamed.

**Flag for the dashboard team:** additive tightenings on existing queue routes: ticket status transitions and reason rules, `assignee_not_eligible` on every assign verb, `service_request_closed` on SR assign, per-type closed codes on claim (planned shared `queue_item_closed` dropped, A1).

## Docs Updated (DOCS-01)

- [x] `API_GUIDE_DASHBOARD.md`: Support Tickets module with handoff section; Operations Queue updates (claim, `queue_type`, eligibility table, staff directory, MySQL-only lock caveat); permission catalogue; error codes.
- [x] Mobile guide / changelog unchanged (no guest routes).
- [x] Postman folder `21 - Support Tickets & Queue (Admin)`, 14 requests.
- [x] `carlton-tree.html`: `support tickets` false → true, `live queue` partial → true (api:true 82 → 84 of 94).

## Error codes added

422: `ticket_transition_invalid {from,to,allowed}`, `ticket_closed {status}`, `ticket_escalation_invalid {reason}`, `ticket_escalation_limit {level,max}`, `ticket_recovery_folio_invalid {folio_item_uuid,reason}`, `assignee_not_eligible {user_uuid,required_permission}`, `service_request_closed {status}`. 409: `queue_item_already_claimed {assigned_user_uuid}`. Validation keys: `ticket_reason_required`, `ticket_guest_mismatch`, `ticket_recovery_amount_mismatch`. All in 5 locales. `queue_item_closed` exists nowhere.

## Test counts

1709 baseline → 1729 → 1748 → 1785 → 1805 → 1873 → 1900 → 1932 → 1944 → 1980 → 2023 (12790 assertions), all green.

## Deviations

- 07-01: scratch migration DB in scratchpad; extra indexes on `ticket_actions.message_id`, `ticket_recoveries.type`; `TicketStatus::canTransitionTo()`.
- 07-03: `actionsTruncated` non-persisted property; show uses `custom.messages.success`.
- 07-04: default sort in `TicketFilter::applySort()`.
- 07-07: `amount_usd` uses `min`/`max` (localized messages); `FolioItemSource::tryFrom()`.
- 07-09: HK/SR arms compare against the claimer passed as assignee.
- 07-10: `GuestActivitySeeder` stamps `resolved_at`/`closed_at` (outside plan list); staff response nests `meta` under `data`; `!`-escaped LIKE search; concierge pin in `SeederTest` updated.
- 07-11: guide lacks stated default/max `per_page` for `/support-tickets`.

## Carry-forwards

- Closed checked before claim no-op; claimed HK history reason is `claimed`; `latest_escalation` null if outside the 200 loaded actions.
- Claim/assign race safety verified on MySQL only (SQLite `lockForUpdate` no-op, A9).
- Deferred: TICKET-08 guest-visible replies/guest_app source, staff notifications, SLA/auto-escalation, `critical` priority, unassign verb, assign-at-create, direct money posting, reopening closed tickets, attachments, RT-01 websocket, SQL UNION queue, per-request `EnsureUserIsActive`.

## Production deploy notes

- [BLOCKING] `php artisan migrate` (3 additive: `2026_09_28_100000_add_support_columns_to_tickets_table`, `..._100100_create_ticket_actions_table`, `..._100200_create_ticket_recoveries_table`).
- [BLOCKING] `php artisan db:seed --class=RolesAndPermissionsSeeder` (preset widening).
- [BLOCKING] queue worker for `MirrorTicketToFirestore`.
- [BLOCKING] add `HOTEL_TICKET_MAX_ESCALATION_LEVEL` (default 3) and `HOTEL_TURNOVER_SLA_MINUTES` (120) to `.env.example` by hand.
- [BLOCKING] notify the React team of the contract tightenings and handoff renames.

## Phase gate (07-11)

(a) route guards match, no DELETE · (b) 26 permissions · (c) scratch migrate/rollback/migrate rc 0, database.sqlite unchanged · (d) folio files unchanged · (e) mobile docs unchanged · (f) `queue_item_closed` absent · (g) PROJECT.md debt entry present · (h) full suite 2023 passed.

Decision coverage: D-01..D-27, A1..A10, PR-1..PR-8 are each covered by the plan summaries 07-01..07-11 and the tests named there.
