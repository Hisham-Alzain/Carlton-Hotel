---
phase: 7
slug: support-tickets-queue
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-09-27
---

# Phase 7 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Source: `07-RESEARCH.md` → Validation Architecture, amended by the post-research consultant decisions PR-1..PR-8 in `07-CONTEXT.md`.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12 via `php artisan test` (Laravel 13.19), SQLite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array` |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter=Ticket` (or the touched file paths) |
| **Full suite command** | `cd backend && php artisan test` (serial; ParaTest is not installed) |
| **Estimated runtime** | ~4 minutes full suite (1709 tests green at `a17c293`, the phase base) |
| **Lock proofs** | `tests/Concerns/RecordsRowLocks.php` proves the SQL carries `for update`; SQLite never serialises (council A9: race safety is MySQL-only) |
| **Query budgets** | `$this->expectsDatabaseQueryCount(n)` wrapped around the service call (precedent `GuestDirectoryTest:315`): ticket list ≤ 6, ticket show ≤ 6 (PR-8), staff list ≤ 4 (D-23) |
| **Firestore** | `tests/Support/FakeFirebaseService.php` bound to `FirebaseServiceInterface`; `$fake->mirrors`, `$fake->throwOnMirror` |

## Sampling Rate

- **After every task:** the touched test files (`php artisan test <paths>` or the plan's `--filter`), plus `LocaleFoundationTest` whenever a lang file changed (five-locale key parity).
- **After every plan:** full suite (`php artisan test`) — every GREEN plan ends on it.
- **Before `/gsd-verify-work` and before the owner commits:** full suite green; the 07-11 gate (routes, permissions, scratch migrations, folio files untouched since `07-BASE.txt`, mobile guide and changelog untouched).
- **Max feedback latency:** 300 seconds.

## Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | Plan |
|--------|----------|-----------|-------------------|------|
| TICKET-01 | list: every filter (status, department, source, category, priority label, assignee uuid / unassigned / me, guest, reservation, escalated, created_at gte/lte), sort, default order, includes resolved/closed, totals, 401/403/422, ≤ 6 queries | feature | `php artisan test tests/Feature/Tickets/TicketIndexTest.php` | 07-04 |
| TICKET-01 | show: ordered `actions[]` (newest 200, ascending, `actions_truncated`), `latest_escalation`, totals, `conversation_uuid`, no `message_id`, 401/403/404, ≤ 6 queries | feature | `php artisan test tests/Feature/Tickets/TicketShowTest.php` | 07-03 |
| TICKET-02 | create: source forced `staff`, department fallback, guest derived from reservation, mismatch 422, `created` action, `created_by`, 201 message, mirror once, 401/403/422 | feature + unit | `php artisan test tests/Feature/Tickets/TicketCreateTest.php tests/Unit/Tickets/CreateTicketActionTest.php` | 07-03 |
| TICKET-03 | full transition matrix, `assigned` rejected, reason rules, stamps, reopen clears, self-assign, timeline row, row lock, mirror once, 401/403/422 | feature + unit | `php artisan test tests/Feature/Tickets/TicketStatusTest.php tests/Unit/Tickets/TicketStatusTransitionTest.php tests/Unit/Tickets/UpdateTicketStatusActionTest.php` | 07-01, 07-05 |
| TICKET-04 | open → assigned, swap, closed 422, no-op self, eligibility (inactive, wrong type, no permission, super admin OK), 403 without `tickets.assign` | feature + unit | `php artisan test tests/Feature/Tickets/TicketAssignTest.php tests/Unit/Tickets/AssignTicketActionTest.php tests/Unit/Support/AssigneeEligibilityTest.php` | 07-02, 07-05 |
| TICKET-05 | reply row, no status change, closed 422, reserved chat link null, 201, no mirror, 401/403/422 | feature | `php artisan test tests/Feature/Tickets/TicketReplyTest.php` | 07-06 |
| TICKET-06 | folio_credit link + `abs()`, `not_credit` / `other_stay` / `already_linked` / `no_stay`, amount mismatch, unique-index backstop, non-money types, `folio_item_uuid` prohibited, closed 422, no DELETE route (405), totals, 401/403/422 | feature + unit | `php artisan test tests/Feature/Tickets/TicketRecoveryTest.php tests/Unit/Tickets/RecordTicketRecoveryActionTest.php` | 07-07 |
| TICKET-07 | self, same_assignee, cap (config override), open → assigned, level++, meta whitelist, closed 422, eligibility, `latest_escalation`, no notifications, 401/403/422 | feature + unit | `php artisan test tests/Feature/Tickets/TicketEscalateTest.php tests/Unit/Tickets/EscalateTicketActionTest.php` | 07-06 |
| OPS-01 | claim × 3 types, 409 other, 200 no-op self (message), per-type closed codes, 403 with only assign / no permission, row locks (tickets, service_requests, rooms → housekeeping_tasks), mirror | feature + unit | `php artisan test tests/Feature/Operations/QueueClaimTest.php tests/Unit/Support/ClaimGuardTest.php` | 07-09 |
| OPS-01 | deactivation revokes tokens; the old token gets 401 on claim | feature | `php artisan test tests/Feature/Operations/DeactivatedTokenClaimTest.php` | 07-09 |
| OPS-01 | SR assign gains row lock, `service_request_closed` (PR-1), eligibility on SR/HK assign | feature + unit | `php artisan test tests/Feature/Operations/QueueAssignEligibilityTest.php tests/Unit/Support/AssigneeEligibilityTest.php` | 07-02 |
| OPS-02 | type / permission / department / search, permission whitelist 422 (A5), `departments[]` array (0/1/n), no `roles` or email, super admin rules, inactive excluded, sales/maintenance empty, cap/truncated, ≤ 4 queries, 401/403 | feature | `php artisan test tests/Feature/Operations/OperationsStaffTest.php` | 07-10 |
| OPS-03 | `queue_type` on all three row types, ticket `room_number`, ticket `allowed_statuses`, queue status delegates and writes the timeline, A2 `LogicException`, mirror once | feature | `php artisan test tests/Feature/Operations/OperationsQueueTicketArmTest.php tests/Feature/Operations/OperationsQueueTest.php` | 07-08 |
| XCUT | presets + A4/PR-6 blast-radius pins (conversations read **and reply**, event inquiries read, concierge event-inquiry status/assign), kitchen/housekeeping unchanged, 26 permissions / 11 groups (PR-4), A3/PR-5 assignability matrix | feature | `php artisan test tests/Feature/Staff/RolePresetsTest.php tests/Feature/SeederTest.php tests/Feature/Staff/AssignabilityMatrixTest.php tests/Feature/Docs/PermissionGuideAccuracyTest.php` | 07-10, 07-11 |
| Regression | D-09 re-pins of the existing assign tests (PR-7) | feature + unit | `php artisan test tests/Feature/Operations tests/Feature/Housekeeping tests/Unit/Housekeeping tests/Feature/Notification/FirestoreMirrorResilienceTest.php` | 07-02 |

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 07-01-01 | 01 | 1 | TICKET-03, TICKET-06 | T-07-10 | Baseline suite green (A10); specs for transition table, enums, meta whitelist, append-only timeline, restrictOnDelete, logExcept (RED) | unit | `cd backend && ! php artisan test --filter='TicketStatusTransitionTest\|TicketEnumsTest\|TicketSchemaTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-01-02 | 01 | 1 | TICKET-02..07 | T-07-05, T-07-10 | Three additive migrations, enums, models, factories, config cap; scratch migrate cycle; dev DB untouched | unit + script | `cd backend && php artisan test --filter='TicketStatusTransitionTest\|TicketEnumsTest\|TicketSchemaTest'` (+ scratch cycle in plan) | ✅ after 07-01-01 | ⬜ pending |
| 07-01-03 | 01 | 1 | TICKET-02 | — | Department helper shared with RouteRequestAction; queue ticket allowed_statuses re-pinned; full suite | feature | `cd backend && php artisan test` | ✅ | ⬜ pending |
| 07-02-01 | 02 | 2 | TICKET-04, OPS-01 | T-07-02 | Specs: eligibility rules, SR assign lock + `service_request_closed`, HK reception un-assignable (RED) | unit + feature | `cd backend && ! php artisan test --filter='AssigneeEligibilityTest\|QueueAssignEligibilityTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-02-02 | 02 | 2 | TICKET-04, OPS-01 | T-07-02, T-07-04 | AssigneeEligibility on SR/HK assign; SR arm locked, closed-checked, mirrored after commit; two codes × 5 locales | unit + feature | `cd backend && php artisan test --filter='AssigneeEligibilityTest\|QueueAssignEligibilityTest\|LocaleFoundationTest'` | ✅ after 07-02-01 | ⬜ pending |
| 07-02-03 | 02 | 2 | TICKET-04 | T-07-02 | ~12 existing assign tests re-pinned with an eligible assignee state (PR-7); full suite | feature + unit | `cd backend && php artisan test` | ✅ | ⬜ pending |
| 07-03-01 | 03 | 3 | TICKET-01, TICKET-02 | T-07-08 | Specs: create (source forced, dept fallback, guest derivation, mismatch), show shape, ≤ 6 queries (RED) | feature + unit | `cd backend && ! php artisan test --filter='TicketCreateTest\|TicketShowTest\|CreateTicketActionTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-03-02 | 03 | 3 | TICKET-01, TICKET-02 | T-07-08, T-07-11 | CreateTicketAction, TicketChanged + queued mirror, TicketService store/show within budget, resources | unit | `cd backend && php artisan test --filter=CreateTicketActionTest` | ✅ after 07-03-01 | ⬜ pending |
| 07-03-03 | 03 | 3 | TICKET-01, TICKET-02 | T-07-08 | POST + GET {ticket} routes behind tickets.respond / tickets.view; message key × 5 locales | feature | `cd backend && php artisan test --filter='TicketCreateTest\|TicketShowTest\|CreateTicketActionTest\|LocaleFoundationTest'` | ✅ after 07-03-01 | ⬜ pending |
| 07-04-01 | 04 | 4 | TICKET-01 | T-07-06 | Specs: every filter, sort, 422 on malformed input, ≤ 6 queries (RED) | feature | `cd backend && ! php artisan test --filter=TicketIndexTest` | ❌ W0 (this task) | ⬜ pending |
| 07-04-02 | 04 | 4 | TICKET-01 | T-07-06 | TicketFilter + index behind tickets.view; `assignee=me` resolved in controller | feature | `cd backend && php artisan test --filter='TicketIndexTest\|TicketShowTest\|TicketCreateTest'` | ✅ after 07-04-01 | ⬜ pending |
| 07-05-01 | 05 | 5 | TICKET-03, TICKET-04 | T-07-01, T-07-02 | Specs: transition matrix, reason rules, stamps, self-assign, assign/swap/no-op/closed/eligibility, row lock (RED) | unit + feature | `cd backend && ! php artisan test --filter='UpdateTicketStatusActionTest\|AssignTicketActionTest\|TicketStatusTest\|TicketAssignTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-05-02 | 05 | 5 | TICKET-03, TICKET-04 | T-07-01, T-07-02 | Two single writers under the ticket lock; two codes × 5 locales | unit | `cd backend && php artisan test --filter='UpdateTicketStatusActionTest\|AssignTicketActionTest\|LocaleFoundationTest'` | ✅ after 07-05-01 | ⬜ pending |
| 07-05-03 | 05 | 5 | TICKET-03, TICKET-04 | T-07-01 | PATCH status (tickets.respond) and assign (tickets.assign) | feature | `cd backend && php artisan test --filter='TicketStatusTest\|TicketAssignTest'` | ✅ after 07-05-01 | ⬜ pending |
| 07-06-01 | 06 | 6 | TICKET-05, TICKET-07 | T-07-09 | Specs: reply rules, escalation guards, cap, meta, latest_escalation, no notifications (RED) | feature + unit | `cd backend && ! php artisan test --filter='TicketReplyTest\|TicketEscalateTest\|EscalateTicketActionTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-06-02 | 06 | 6 | TICKET-05, TICKET-07 | T-07-09 | Reply and escalate writers; two codes × 5 locales | unit | `cd backend && php artisan test --filter='EscalateTicketActionTest\|LocaleFoundationTest'` | ✅ after 07-06-01 | ⬜ pending |
| 07-06-03 | 06 | 6 | TICKET-05, TICKET-07 | T-07-09 | POST reply (201) and escalate behind tickets.respond | feature | `cd backend && php artisan test --filter='TicketReplyTest\|TicketEscalateTest'` | ✅ after 07-06-01 | ⬜ pending |
| 07-07-01 | 07 | 7 | TICKET-06 | T-07-03, T-07-04 | Specs: link rules, four reasons, abs, backstop, totals, no delete route (RED) | feature + unit | `cd backend && ! php artisan test --filter='TicketRecoveryTest\|RecordTicketRecoveryActionTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-07-02 | 07 | 7 | TICKET-06 | T-07-03, T-07-04, T-07-13 | Record-only writer; unique link backstop; code × 5 locales | unit | `cd backend && php artisan test --filter='RecordTicketRecoveryActionTest\|LocaleFoundationTest'` | ✅ after 07-07-01 | ⬜ pending |
| 07-07-03 | 07 | 7 | TICKET-06 | T-07-03 | POST recovery-actions (201) behind tickets.respond; folio files untouched | feature | `cd backend && php artisan test --filter='TicketRecoveryTest\|RecordTicketRecoveryActionTest\|TicketShowTest'` | ✅ after 07-07-01 | ⬜ pending |
| 07-08-01 | 08 | 8 | OPS-03, TICKET-03 | T-07-01 | Specs: queue_type, ticket room_number, delegation + timeline, A2, mirror once (RED) | feature | `cd backend && ! php artisan test --filter=OperationsQueueTicketArmTest` | ❌ W0 (this task) | ⬜ pending |
| 07-08-02 | 08 | 8 | OPS-03, TICKET-03 | T-07-01, T-07-11 | Queue ticket arms delegate to the single writers; inline mirror removed; `queue_type` on every row | feature | `cd backend && php artisan test --filter='OperationsQueue\|Firestore\|DashboardSummaryTest'` then full suite | ✅ after 07-08-01 | ⬜ pending |
| 07-09-01 | 09 | 9 | OPS-01 | T-07-04, T-07-14 | Specs: claim × 3 types, 409, no-op, per-type closed codes, 403, locks, deactivated token (RED) | feature + unit | `cd backend && ! php artisan test --filter='QueueClaimTest\|DeactivatedTokenClaimTest\|ClaimGuardTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-09-02 | 09 | 9 | OPS-01 | T-07-04 | ClaimGuard inside each assign writer's lock; 409 code × 5 locales | unit | `cd backend && php artisan test --filter='ClaimGuardTest\|AssignTicketActionTest\|AssignHousekeepingTaskActionTest\|LocaleFoundationTest'` | ✅ after 07-09-01 | ⬜ pending |
| 07-09-03 | 09 | 9 | OPS-01 | T-07-04, T-07-14 | PATCH …/claim gated by the type's work permission in-service | feature | `cd backend && php artisan test --filter='QueueClaimTest\|DeactivatedTokenClaimTest'` then full suite | ✅ after 07-09-01 | ⬜ pending |
| 07-10-01 | 10 | 10 | OPS-02 | T-07-06, T-07-07 | Specs: staff directory, preset re-pins, blast radius, assignability matrix (RED) | feature | `cd backend && ! php artisan test --filter='OperationsStaffTest\|RolePresetsTest\|AssignabilityMatrixTest'` | ❌ W0 (this task) | ⬜ pending |
| 07-10-02 | 10 | 10 | OPS-02 | T-07-06 | GET /operations/staff behind the queue view gate; ≤ 4 queries; no email/permissions | feature | `cd backend && php artisan test --filter=OperationsStaffTest` | ✅ after 07-10-01 | ⬜ pending |
| 07-10-03 | 10 | 10 | OPS-02 | T-07-07 | reception/concierge presets widened (no new permission strings); demo seeder timeline; scratch seed | feature | `cd backend && php artisan test --filter='RolePresetsTest\|SeederTest\|AssignabilityMatrixTest\|OperationsStaffTest'` then full suite | ✅ after 07-10-01 | ⬜ pending |
| 07-11-01 | 11 | 11 | DOCS-01, XCUT-01 | T-07-12 | Dashboard guide accurate; inert list unchanged | grep + feature | `cd backend && php artisan test --filter='PermissionGuideAccuracyTest'` | ✅ | ⬜ pending |
| 07-11-02 | 11 | 11 | DOCS-01 | T-07-12 | Postman JSON valid; tree api:true +2; mobile guide and changelog untouched (D-26) | script | node checks in the plan's verify | ✅ | ⬜ pending |
| 07-11-03 | 11 | 11 | XCUT-01 | T-07-12, T-07-13 | Gate: routes, 26 permissions, scratch migrations, folio untouched, dropped shared closed code absent, full suite; SUMMARY contract | full suite | `cd backend && php artisan test` | ✅ | ⬜ pending |

Every task has an `<automated>` verify. RED tasks assert failure and are followed in the same plan by GREEN tasks that run the same specs, so no three consecutive tasks lack a passing automated check. Show (`GET /support-tickets/{ticket}`) and claim (`PATCH …/claim`) take no request body: show's 422 row is replaced by a 404 test (flagged FA-7.03-1); claim's 422 is the per-type closed code (A1).

## Wave 0 Requirements

Wave 0 is folded into the first (QA, RED) task of each plan:

- [ ] 07-01-01: `07-BASE.txt`; `tests/Unit/Tickets/{TicketStatusTransition,TicketEnums,TicketSchema}Test.php` (new `tests/Unit/Tickets/` directory); factories `TicketActionFactory`, `TicketRecoveryFactory` and the `TicketFactory` states arrive in 07-01-02
- [ ] 07-02-01: `tests/Unit/Support/AssigneeEligibilityTest.php`, `tests/Feature/Operations/QueueAssignEligibilityTest.php`; the `UserFactory` eligible-assignee state arrives in 07-02-02 (PR-7)
- [ ] 07-03-01: `tests/Feature/Tickets/{TicketCreate,TicketShow}Test.php` (new `tests/Feature/Tickets/` directory), `tests/Unit/Tickets/CreateTicketActionTest.php`
- [ ] 07-04-01: `tests/Feature/Tickets/TicketIndexTest.php`
- [ ] 07-05-01: `tests/Unit/Tickets/{UpdateTicketStatusAction,AssignTicketAction}Test.php`, `tests/Feature/Tickets/{TicketStatus,TicketAssign}Test.php`
- [ ] 07-06-01: `tests/Feature/Tickets/{TicketReply,TicketEscalate}Test.php`, `tests/Unit/Tickets/EscalateTicketActionTest.php`
- [ ] 07-07-01: `tests/Feature/Tickets/TicketRecoveryTest.php`, `tests/Unit/Tickets/RecordTicketRecoveryActionTest.php`
- [ ] 07-08-01: `tests/Feature/Operations/OperationsQueueTicketArmTest.php`; `OperationsQueueTest` queue_type assertions
- [ ] 07-09-01: `tests/Feature/Operations/{QueueClaim,DeactivatedTokenClaim}Test.php`, `tests/Unit/Support/ClaimGuardTest.php`
- [ ] 07-10-01: `tests/Feature/Operations/OperationsStaffTest.php`, `tests/Feature/Staff/AssignabilityMatrixTest.php`; re-pins of `RolePresetsTest` and `SeederTest`
- [ ] Existing infrastructure reused: `tests/Concerns/RecordsRowLocks.php`, `tests/Support/FakeFirebaseService.php`, RefreshDatabase; no framework install

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Claim race serialization under real concurrency | OPS-01 | SQLite `lockForUpdate()` is a no-op (council A9); the suite proves only that `for update` is issued | On a MySQL staging DB, fire two `PATCH /operations/queue/tickets/{uuid}/claim` requests from two staff tokens at once; exactly one gets 200, the other 409 `queue_item_already_claimed` |
| Guides, Postman, tree nodes (support tickets, live queue) | DOCS-01 | Documentation content | Open `docs/carlton-tree.html`; both nodes are api:true with real `ep`s and the live-queue note is gone |
| SUMMARY lists presets, [BLOCKING] deploy notes, queue worker, additive tightenings, un-assignable table | XCUT-01 | Summary format | Read `.planning/phases/07-support-tickets-queue/SUMMARY.md` |

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 300s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
