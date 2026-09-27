# Carlton Hotel Backend — API Gap Closure

## What This Is

The Laravel 13 backend (`backend/`) that serves the Carlton Hotel React staff dashboard and Flutter guest app. Phases P0–P10 shipped 283 routes (staff auth + RBAC, CMS, booking, events, services, folios, notifications/chat, operations queue) with 250 green PHPUnit tests. This milestone closes the remaining gaps that the clients already mock: 21 capabilities with no backend API and 4 partially-covered ones, delivered module by module under the TupCode Laravel conventions.

## Core Value

Every screen the dashboard and guest app already show works against a real, tested, convention-compliant `/api/v1` endpoint instead of mock data.

## Requirements

### Validated

<!-- Inferred from the existing codebase (.planning/codebase/ARCHITECTURE.md, STACK.md) and docs/carlton-tree.html nodes with api:true. -->

- ✓ Staff sign-in / me / logout with Sanctum tokens; guest OTP login and profile — existing (P1)
- ✓ Staff, roles and permissions management (spatie/permission, StaffPolicy) — existing (P2)
- ✓ Public and admin CMS for room types, rooms, amenities, FAQs, gallery, menus, pages, promotions, sliders, dining venues, event spaces, with recycle bin and media — existing (P3)
- ✓ Two-step public booking, availability check, guest reservations, stays, admin reservations, room assignment, check-in approvals — existing (P4/P5)
- ✓ Event inquiries (RFP) public submit and admin pipeline — existing (P6)
- ✓ Service catalogue, guest service requests with entitlement gates, pre-arrival documents — existing (P7)
- ✓ Guest folio view, express checkout, admin folio settle, manual cash / pay-on-arrival payments — existing (P8)
- ✓ Device tokens, guest–staff conversations and messages, Firebase push adapter — existing (P9)
- ✓ Operations queue (`/operations/queue/{type}/{uuid}`), dashboard summary — existing (P10)
- ✓ Response envelope, error_code contract, AR/EN localisation, activity logging, UUID public ids — existing (P0)

### Active

<!-- The 25 in-scope gaps. Detail and endpoint shapes live in REQUIREMENTS.md and the roadmap. -->

- [ ] Guest can sign out (token revoked); staff can view/update own profile and change password
- [ ] Front desk sees a live room board, marks rooms clean/dirty/inspected, and views 14-day availability and rate grids
- [ ] Staff can add reservation notes, list available rooms for a reservation, and check a reservation in and out with explicit verbs
- [ ] Staff have a guest directory with profile, notes and pre-arrival checklist; guests can save preferences and complete online check-in (arrival time, digital key); ID scan wired to existing document upload
- [ ] Staff can read a reservation's folio, post line items, record payments, and raise/resolve line-item disputes (guest side too)
- [ ] Housekeeping tasks (list, assign, status) exist and link to room status; staff have a service-request board; departure services can be listed and progressed; guest quick requests map to catalogue items
- [ ] Support tickets: list, create, status, assign, reply, recovery actions, escalate; queue items can be claimed; staff list available for assignment
- [ ] Event inquiries carry a checklist, deposit and notes; staff can list table reservations; guests can download a venue menu
- [ ] Night audit (checks and blockers, per business date) and a reports dashboard endpoint
- [ ] Every new capability has AR/EN keys, feature tests (happy / 401 / 403 / 422), API guide + Postman entries, and its node flipped to `api:true` in `docs/carlton-tree.html`

### Out of Scope

- SMS / WhatsApp / email OTP provider — needs vendor credentials and contract; static OTP stays until a provider is chosen
- Online payment gateway driver — needs merchant account and PCI review; manual driver (cash, pay on arrival) remains
- AI concierge (P11) — separate milestone with its own AI-SPEC
- Websocket / realtime live queue — polling is acceptable for this milestone
- Alias routes at the dashboard's mocked paths — the dashboard adopts the backend convention (`/cms/{resource}/{uuid}`) instead; documented per phase for the frontend team

## Context

- Conventions are mandatory and codified: `backend/.claude/skills/tupcode-laravel-backend/` (SKILL.md + `references/developer-guide.md`, identical to `D:\TupCode\backend-developer-guide-v2.md`), plus project skills `laravel-conventions`, `module-slice`, `naive-reviewer`, `test-discipline`. Codebase map in `.planning/codebase/`.
- Gap inventory source: `docs/carlton-tree.html` (`var TREE`; `api:false` = missing, `api:"partial"` = mismatch; each node's `ep` lists the endpoints the dashboard calls).
- Existing building blocks to reuse are catalogued per phase in the roadmap (e.g. `CheckAvailabilityAction`, `AssignRoomAction`, `RecordCashPaymentAction`, polymorphic `AssignRequestAction`/`UpdateRequestStatusAction`, `Ticket`/`Conversation`/`Message`, `Guest` + factory, `reports.view` permission already seeded).
- Execution model: each phase is planned with `/gsd-plan-phase` and built by the `council-build` workflow (Opus engineer, Opus QA, Fable consultant with two-tier ai-council, Sonnet delegates), which commits locally after a green suite.
- Known concerns (from CONCERNS.md): guest documents on public disk (P7), untested Firebase integration, hand-paginated merged operations queue, no negative permissions.

## Constraints

- **Tech stack**: PHP 8.3, Laravel 13, PHPUnit 12, SQLite in-memory for tests, MySQL in production — no new frameworks
- **Conventions**: TupCode guide §1–§17 is a hard gate; Base* classes, services returning `['data','code']`, domain exceptions, AR/EN keys, UUID public ids, `/api/v1` versioning, routes grouped by role
- **API contract**: `error_code` values and field names are contracts; additive changes only, breaking changes need explicit sign-off and a note to the Flutter/React teams
- **Database**: additive migrations only; 3NF; DECIMAL for money in USD; FKs indexed with explicit ON DELETE; `status` + history over boolean flags
- **Testing**: every new route has happy / 401 / 403 / 422 tests; non-trivial actions have unit tests; full suite stays green before any commit
- **Git**: commit after each phase, never push (owner pushes manually)
- **Security**: role checks in middleware, ownership in `authorize()`, throttle on auth endpoints, payment-like writes idempotent and transactional

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Scope = 21 missing + 4 cheap partials; providers, gateway, AI concierge deferred | External vendors and P11 are separate efforts with their own risks | — Pending |
| Dashboard adopts backend paths (`/cms/{resource}/{uuid}`), no alias routes | Aliases double the test matrix and split permissions | — Pending |
| New operational domains keep dashboard-named top-level paths (`/front-desk`, `/housekeeping`, `/support-tickets`, `/guests`, `/departure-services`, `/reports`) | Matches the `/operations` precedent; they are not CMS CRUD | — Pending |
| Phases built by `council-build` workflow instead of `/gsd-execute-phase` | Adds QA test-and-fix loop, consultant/council escalation and delegated grunt work per module | — Pending |
| Housekeeping tasks are a real table; departure services are a projection | Assignment/timing must persist; departures are derivable from bookings + requests | — Pending |
| Support-ticket replies stored in `ticket_actions`; `Message` mirroring reserved (nullable `message_id`) not implemented | Council (2026-09-25): no confirmed requirement for guest-visible replies; avoids undocumented duplicated data | — Pending |
| **Debt:** `tickets.*` doubles as the guest-relations permission set — widening `reception`/`concierge` with `tickets.view/.assign/.respond` (Phase 7) also grants read of `/cms/conversations` (guest chat PII) and, per the Phase 7 council, `/cms/event-inquiries` (RFP leads, contact PII, budgets), plus re-status/assign on event inquiries via `tickets.assign` | Council A4 (2026-09-27): avoids a new permission string for a narrow slice; accepted as a known coupling, not a bug | Split into `support_tickets.*` if the owner objects once the blast radius is visible in review — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-09-25 after initialization*
