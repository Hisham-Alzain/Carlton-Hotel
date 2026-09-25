# Project Research Summary

**Project:** Carlton Hotel Backend — API Gap Closure (PMS operational features)
**Domain:** Hotel Property Management System (PMS) backend, extending an existing Laravel 13 layered API
**Researched:** 2026-09-25
**Confidence:** HIGH

## Executive Summary

This milestone closes 21 missing + 4 partial API gaps in a mature, convention-heavy Laravel 13 hotel backend (283 routes, 250 green tests already shipped in P0-P10). Research across stack, features, architecture, and pitfalls converges on one message: this is an extension exercise, not a build-from-scratch one. Every target capability - room/housekeeping status, folio ledger writes, night audit, support tickets, guest directory, event deposits, reports - is buildable with Laravel 13 primitives and packages already installed (mpdf, spatie/permission, spatie/activitylog). Zero new core packages are needed; the only real stack decision is discipline to not introduce a state-machine package, a second money representation, or a second PDF engine that would fragment conventions the codebase has already standardized on.

The recommended approach is to extend the existing layered architecture (Route to Controller to FormRequest to Service/Action to Resource to Model) with new domain folders (FrontDesk, Housekeeping, Support, NightAudit, Reports) that reuse proven patterns: the polymorphic AssignRequestAction/UpdateRequestStatusAction union (add HousekeepingTask as a third arm), the status + history-table convention over boolean flags, and the event-driven decoupling already demonstrated by RoomAssigned to SendRoomReadyNotification (extend to ReservationCheckedOut to CreateTurnoverHousekeepingTask). Synchronous locked checks (mirroring SettleFolioAction's lockForUpdate) gate cross-domain preconditions (checkout blocked on unsettled folio); events handle non-blocking side effects.

The dominant risks are not technical novelty but regression of already-fixed invariants and conflation of distinct concepts: (1) folio money-handling bugs reappearing in new endpoints that do not inherit GenerateFolioAction's fixes; (2) room housekeeping status merged into the availability-driving status field; (3) night audit using wall-clock today instead of a persisted business-date/last-closed state; (4) housekeeping task duplication from multiple triggers with no uniqueness guard; (5) permission-string sprawl across seven new domains. Each has a concrete, cheap prevention (transaction+lock discipline, separate independent status fields, persisted audit-run state, unique constraints, a domain.action permission convention) traceable to patterns the codebase has already proven or already failed once.

## Key Findings

### Recommended Stack

No new core packages. Laravel 13 plus existing dependencies (laravel/sanctum, spatie/laravel-permission, spatie/laravel-activitylog, mpdf/mpdf) cover every target feature. State machines are plain status enum columns plus append-only history tables with Action-layer transition guards, not spatie/laravel-model-states. Folio/deposit money stays raw DECIMAL with DB::transaction plus lockForUpdate, not a Money value object library or double-entry ledger package. Night audit is an Artisan-command-backed service triggered by an authenticated endpoint, not a scheduled-job monitoring package. PDF export reuses mpdf (already installed). Reports use plain Eloquent aggregation via the existing BaseFilter pattern, not spatie/laravel-query-builder, unless ad-hoc client-driven filtering is later required.

**Core technologies:**
- Laravel 13 primitives (Console commands, Scheduler, Events/Listeners) - batch/cron work (night audit) and cross-domain decoupling (checkout to housekeeping)
- status column + *_status_history table + Action-layer guard - every new state machine (room, housekeeping task, ticket, dispute)
- DB::transaction + lockForUpdate - every money-mutating action (folio line items, payments, disputes, event deposits), mirroring the already-fixed P8 pattern
- mpdf/mpdf (existing) - venue menu PDF export, same rendering path as invoices

### Expected Features

Standard hotel PMS conventions (Opera, Mews, Cloudbeds) validate nearly every requested capability as table stakes; a few are explicit differentiators; several tempting extras are explicitly out of scope.

**Must have (table stakes):**
- Room board (status + occupancy grid) and 14-day availability/rate grid
- Room status lifecycle (dirty/clean/inspected x vacant/occupied, plus OOO/OOS)
- Explicit check-in/check-out verbs with guardrails (room readiness, folio settled)
- Folio line items, payments, disputes (guest + staff side)
- Housekeeping tasks (list/assign/status) linked to room status
- Support tickets: full lifecycle (list/create/status/assign/reply/escalate), queue claim
- Night audit (checks and blockers per business date, read-only)
- Reports dashboard (occupancy, arrivals/departures, revenue - narrow v1)
- Guest directory (profile/notes/preferences), pre-arrival checklist, online check-in with digital key as a data field
- Event inquiry checklist/deposit, table reservation listing, venue menu download

**Should have (differentiators):**
- Computed (not stored) pre-arrival checklist and departure-services projection - avoids drift bugs common in legacy PMS
- Housekeeping status and task board wired transactionally (checkout to task, task-complete to status-flip) rather than two disconnected screens
- Night audit as a transparent checks-and-blockers report rather than an opaque pass/fail

**Defer (v2+):**
- Real digital-key/lock hardware integration
- Automatic night-audit date rollover
- Dynamic pricing / revenue management automation
- Housekeeping route optimization
- Websocket-based live boards
- Guest-visible ticket replies merged into the chat system
- CRM-grade guest preference/loyalty taxonomy

### Architecture Approach

Extend the existing layered stack with new domain folders that mirror current conventions (Booking, Cms, Folio become plus FrontDesk, Housekeeping, Support, NightAudit, Reports). The one new mechanism is deliberate use of Events/Listeners for cross-domain side effects that must not block the triggering action, paired with synchronous locked checks for preconditions that must block it - both patterns already exist in the codebase in isolated form and just need generalizing.

**Major components:**
1. Front Desk domain - room board, availability/rate grid, check-in/out verbs (foundation; nearly everything else depends on it)
2. Housekeeping domain - task table, generalizes the existing polymorphic assign/status union to a third type, reacts to ReservationCheckedOut
3. Folio ledger domain - extends existing Folio/Payment models with line-item post, disputes; independent of Housekeeping, parallelizable
4. Support tickets domain - new ticket_actions table for replies/escalation/recovery, reuses generalized queue
5. Night audit + Reports domains - pure read-only aggregation layers with no new source-of-truth tables; must be built last since they depend on every other domain being stable

### Critical Pitfalls

1. **Room status conflated with availability** - model housekeeping status as an independent field/enum from the date-range-driven availability query; never let AssignRoomAction gate on housekeeping status implicitly.
2. **Folio/deposit money-handling regressions** - every new folio- or deposit-mutating action must wrap in DB::transaction plus lockForUpdate and be idempotent under retry, mirroring the already-fixed P8 TOCTOU bug; verify with concurrency tests.
3. **Night audit using wall-clock today** - persist business-date/last-closed-date state explicitly; never derive the date to close from now()/today(); ensure re-running the same date is safe.
4. **Housekeeping task duplication** - enforce one open task per (room_id, task_type) via a unique constraint or transactional check-before-insert, since tasks can be triggered from checkout, manual flags, or future jobs.
5. **Permission sprawl across seven new domains** - fix a domain.action naming convention up front (matching the existing reports.view precedent) before any phase starts inventing permission strings.

## Implications for Roadmap

Based on research, suggested phase structure follows the dependency-driven build order identified in ARCHITECTURE.md, not the requirement list's order:

### Phase 1: Front Desk Foundation (room board, room status, availability/rate grid, check-in/check-out, reservation notes)
**Rationale:** Everything else (housekeeping triggers, guest directory online check-in, night audit's occupancy checks) depends on room status and the check-in/out verbs existing. Sets the "status independent of availability" and "set-based grid query" patterns other phases reuse.
**Delivers:** Room board endpoint, RoomStatus enum extension, availability/rate grid endpoints, CheckInReservationAction/CheckOutReservationAction (with folio-settled gate and ReservationCheckedOut event), reservation notes CRUD, rooms-available-for-reservation listing.
**Addresses:** Room board, availability/rate grid, check-in/out verbs, reservation notes, rooms-available listing (table stakes).
**Avoids:** Room status/availability conflation; N+1 in grids (set-based queries from day one).

### Phase 2: Housekeeping
**Rationale:** Depends on Phase 1's ReservationCheckedOut event existing; schema work can start in parallel with Phase 1.
**Delivers:** HousekeepingTask model/table, generalized AssignRequestAction/UpdateRequestStatusAction/OperationsQueueService (third union arm), CreateTurnoverHousekeepingTask listener wired to checkout.
**Uses:** Existing polymorphic assign/status pattern; Laravel Events/Listeners.
**Implements:** Housekeeping domain component.
**Avoids:** Task duplication - unique constraint on (room_id, task_type) for open tasks.

### Phase 3: Folio Ledger Extensions (line items, payments, disputes)
**Rationale:** Only depends on Phase 1 for the checkout gate to have something real to check against; otherwise independent - can run in parallel with Phase 2.
**Delivers:** Folio line-item post, payment record, dispute raise/resolve (staff + guest sides).
**Uses:** Raw DECIMAL money, DB::transaction plus lockForUpdate.
**Avoids:** Folio money-handling regressions - mandatory concurrency test per new mutation.

### Phase 4: Guest Directory & Profiles
**Rationale:** Depends on Phase 1's CheckInReservationAction for the online-check-in flow; otherwise independent, parallelizable with Phases 2-3.
**Delivers:** Guest profile/notes/preferences, computed pre-arrival checklist, online check-in (arrival time + digital-key data field), ID scan wired to existing document upload.
**Addresses:** Guest directory, pre-arrival checklist, online check-in, digital key.
**Avoids:** Digital key as an unguarded credential (server-generated high-entropy token bound to entitlement lifecycle); PII leakage (dedicated guests.notes.view permission, exclude notes from exports).

### Phase 5: Support Tickets Full Lifecycle
**Rationale:** Depends on Phase 2 having already generalized OperationsQueueService's union pattern - schedule after Phase 2 lands, or coordinate closely if run in parallel.
**Delivers:** ticket_actions table (reply/recovery/escalation log), assign/reply/recovery/escalate actions, queue claim.
**Avoids:** Escalation loops / claim races - atomic claim, status-guarded idempotent escalation.

### Phase 6: Event/Venue Extensions (checklist, deposit, table reservations, menu download)
**Rationale:** No dependency on Phases 1-5; lowest risk, schedulable as filler at any point including first if sequencing flexibility is needed.
**Delivers:** Event inquiry checklist items, deposit tracking, table reservation listing, venue menu download.
**Avoids:** Event deposits repeating folio mistakes - reuse the Phase 3 payment-recording pattern, not a bespoke path.

### Phase 7: Night Audit
**Rationale:** Must be sequenced after Folio (3), Housekeeping (2), and Front Desk check-in/out (1) exist - the blockers it reports are only meaningful once those verbs are stable. Should also follow Support Tickets (5) if ticket-related checks are included.
**Delivers:** NightAuditService running a Strategy/evaluator registry (NightAuditCheck classes per domain, read-only), NightAuditRun persistence.
**Avoids:** Wall-clock date boundary bug - persisted business-date/last-closed state, idempotent re-run.

### Phase 8: Reports Dashboard
**Rationale:** Widest fan-in dependency; reads from nearly every other new and existing table. Building it earlier invites rework against unstable schemas.
**Delivers:** Cross-domain aggregation endpoint (occupancy, revenue, arrivals/departures, narrow v1), reusing the existing reports.view permission.
**Avoids:** N+1/PHP-side aggregation (DB-level groupBy/selectRaw only); notes excluded from report payloads by default.

### Phase Ordering Rationale

- Front Desk first because room status and check-in/out are load-bearing dependencies for Housekeeping, Guest Directory, and Night Audit.
- Housekeeping and Folio can run in parallel after Phase 1 lands (independent of each other, both only need Phase 1's event/gate).
- Guest Directory parallelizes with 2-3, gated only on Phase 1's check-in action.
- Support Tickets sequenced after Housekeeping specifically to reuse (not re-derive) the generalized queue union pattern.
- Event/Venue has zero cross-phase dependency and is the safest filler phase for scheduling flexibility.
- Night Audit and Reports are deliberately last: both are pure read/aggregation layers over every other domain and are highest-rework-risk if built against unstable schemas.
- Cross-cutting: permission-naming convention (domain.action) and full-existing-test-suite-green-before-commit must be enforced starting Phase 1, not deferred - both are cheap now and expensive to retrofit across 7+ domains.

### Research Flags

Needs deeper research during planning (--research-phase):
- **Night Audit phase:** business-date/timezone modeling and "last closed date" state design has no existing precedent in this codebase to copy verbatim - needs its own design pass before coding.
- **Reports Dashboard phase:** aggregation query design across many tables with realistic volume assumptions; verify indexing strategy before implementation, not after.

Standard patterns (skip research-phase, proceed straight to planning):
- **Front Desk, Housekeeping, Folio, Guest Directory, Support Tickets, Event/Venue phases:** all directly extend existing, already-documented conventions (status+history, polymorphic assign/status union, DB::transaction+lockForUpdate, existing document-upload pipeline) with clear precedent already inspected in ARCHITECTURE.md.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Verified against composer.json directly; new-package claims cross-checked against 2026 Packagist/GitHub data |
| Features | MEDIUM-HIGH | Grounded in established PMS vendor documentation (Opera, Mews, Cloudbeds, Optii) plus this project's own validated PROJECT.md scope; no direct API access to vendor internal schemas |
| Architecture | HIGH | Based on direct inspection of the existing codebase (Actions, Services, Models, Events/Listeners) - an internal-integration question, not an ecosystem survey |
| Pitfalls | HIGH (project-specific) / MEDIUM (generic industry claims) | Grounded in this codebase's own documented incidents (CONCERNS.md) and enforced conventions; generic night-audit/digital-key claims sourced from web content, not vendor-verified |

**Overall confidence:** HIGH

### Gaps to Address

- **Business-date/timezone model for night audit:** no existing single-timezone constant or last-closed-date state exists yet in the codebase; must be designed during Phase 7 planning, not assumed.
- **Rate grid data source:** if no date-specific rate override table exists yet in CMS, a flat per-room-type rate is an acceptable v1 - confirm actual CMS rate schema during Phase 1 planning before committing to grid shape.
- **Digital key token design:** no existing precedent for credential-grade token issuance in this codebase (only OTP/Sanctum tokens) - needs explicit design (entropy, expiry, invalidation) during Phase 4 planning rather than treating it as just a field.
- **Operations queue scaling:** adding Housekeeping as a third unioned type in OperationsQueueService risks compounding an already-documented in-memory merge/pagination bottleneck - confirm during Phase 2 planning whether housekeeping needs its own dedicated board instead of joining the merged queue.

## Sources

### Primary (HIGH confidence)
- backend/composer.json, app/Actions/Operations/*, app/Services/Operations/OperationsQueueService.php, app/Models/{Room,Ticket,Folio,Reservation,ServiceRequest,Guest,ReservationRoom}.php, app/Actions/Booking/CreateReservationAction.php, app/Actions/Folio/SettleFolioAction.php, app/Events/*, app/Listeners/* - direct codebase inspection
- .planning/codebase/ARCHITECTURE.md, STRUCTURE.md, CONVENTIONS.md, CONCERNS.md - project-generated codebase maps
- .planning/PROJECT.md - project's own validated scope and decision log
- backend/.claude/skills/tupcode-laravel-backend/references/developer-guide.md section 15 - money/status/schema conventions

### Secondary (MEDIUM-HIGH confidence)
- Packagist/GitHub 2026 data (mpdf/mpdf 8.3.1, spatie/laravel-query-builder 7.3.3, spatie/laravel-model-states v2, spatie/laravel-model-status) - web search, cross-checked against changelogs
- Cloudbeds, Optii, Mews vendor documentation on room status codes and night audit process
- StayNTouch, Canary Technologies on contactless/mobile check-in patterns

### Tertiary (MEDIUM confidence, needs validation)
- SetupMyHotel housekeeping training reference (industry training content, not vendor-official)
- NowSecure/NerdWallet mobile hotel-key security articles - applied here to token design principles only, no physical lock in scope

---
*Research completed: 2026-09-25*
*Ready for roadmap: yes*
