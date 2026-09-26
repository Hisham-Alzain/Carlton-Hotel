---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
current_phase: 3
current_phase_name: Reservations — Front-Desk Verbs
status: complete
stopped_at: Phase 4 context gathered (auto); Phase 3 hardening pending
last_updated: "2026-09-26T02:12:14.560Z"
last_activity: 2026-09-26
last_activity_desc: "Phase 3 closed: notes/available-rooms/check-in/check-out desk verbs, assign-room narrowed (breaking, D-03), guest express checkout delegated to the shared check-out action, status/folio_status list filters, docs/Postman/tree closure. Full suite 1229/1229 green."
progress:
  total_phases: 4
  completed_phases: 0
  total_plans: 15
  completed_plans: 3
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-25)

**Core value:** Every screen the dashboard and guest app already show works against a real, tested, convention-compliant `/api/v1` endpoint instead of mock data.
**Current focus:** Phase 3: Reservations — Front-Desk Verbs

## Current Position

Phase: 3 of 9 (Reservations — Front-Desk Verbs) — complete
Plan: 7 of 7 in current phase (03-01 .. 03-07 all done)
Status: Phase 3 closed; ready to plan Phase 4
Last activity: 2026-09-26 — Phase 3 closed: notes/available-rooms/check-in/check-out desk verbs, assign-room narrowed (breaking, D-03), guest express checkout delegated to the shared check-out action, status/folio_status list filters, docs/Postman/tree closure. Full suite 1229/1229 green.

Progress: [███░░░░░░░] 33%

## Performance Metrics

**Velocity:**

- Total plans completed: 0
- Average duration: -
- Total execution time: 0.0 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| - | - | - | - |

**Recent Trend:**

- Last 5 plans: -
- Trend: -

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- [Roadmap]: Module order 1 Access → 2 Rooms → 3 Reservations → 4 Guests → 5 Folio → 6 Housekeeping/Services → 7 Tickets/Queue → 8 Events/Dining → 9 Night Audit/Reports (user-approved session plan)
- [Roadmap]: DOCS-01 and XCUT-01 are traced to Phase 1 but enforced as a contract gate in every phase's success criteria
- [Roadmap]: Housekeeping status stays independent of availability (ROOMS-02); check-out emits an event that Phase 6's turnover-task listener consumes
- [Init]: Dashboard adopts backend paths (no alias routes); new operational domains use top-level paths (`/front-desk`, `/housekeeping`, `/support-tickets`, `/guests`, `/departure-services`, `/reports`)
- [Init]: Ticket replies stored in `ticket_actions` only; chat mirroring deferred (TICKET-08, v2)
- [Init]: Phases built by `council-build`; commit locally after each phase, never push
- [Phase 2]: Two-axis room model — `rooms.status` is housekeeping-only (`available|dirty|maintenance`), occupancy derived at read time from reservations; audited via `room_status_history` + denormalized `status_changed_at`/`status_changed_by`, single writer `UpdateRoomStatusAction`
- [Phase 2]: Rate-grid nightly rate is a deliberate clone of `QuoteReservationAction`'s rule loop (`PricingService::nightlyRate`), not a call into the action — `QuoteReservationAction`/`CheckAvailabilityAction` stay byte-unmodified
- [Phase 3]: `AssignRoomAction` narrowed to pure assignment (D-03, breaking); check-in is now its own verb and the only writer of `checked_in_at`/`checked_in` status
- [Phase 3]: Check-out is a single shared action (`CheckOutReservationAction`) for staff plain, staff forced and guest express alike; `folio_unsettled` is thrown after commit so the built folio survives the refusal (FA-05-7)
- [Phase 3]: No new permissions this phase; `HOTEL_TIMEZONE`/`HotelClock` introduced for hotel-local stay-window checks

### Pending Todos

None yet.

### Blockers/Concerns

- Phase 4: `FA-06-1` (carried from Phase 3) — `GuestEntitlement::currentReservation()` should resolve the checked-in stay, not the most recent booking; affects 8 call sites (`StayController`, `TableReservationController`, `TransportRequestController`, `FolioService::myFolio`/`approveMyFolio`, `PreArrivalService`, `ServiceBookingService`, `ServiceRequestService`)
- Phase 3 (carried, non-blocking): `FA-02-1` — occupancy is still night-based; a checked-in guest on the check-out day reads `vacant`/`departing_today: true` until checked out
- Phase 3 (carried, non-blocking): `FA-03-2` (MySQL only) — `QuoteReservationAction`'s `ends_on > check_in` comparison lacks `whereDate`, can exclude a pricing rule's last day on MySQL while the rates grid follows the inclusive window; recommended fix noted for the per-night pricing work
- Phase 3 (carried, non-blocking): `FA-03-3` — quote and grid both iterate pricing rules with no `ORDER BY`; pin `id` order in both paths together
- Phase 2 (carried, non-blocking): `rooms.floor` has no index; negligible at hotel scale, revisit if room count grows
- Phase 4: the digital key needs an explicit token design (entropy, expiry, invalidation on check-out/cancel); the codebase has no precedent for it
- Phase 4: guest documents sit on the public disk (P7 concern in CONCERNS.md), which matters because ID scans reuse that upload path
- Phase 6: adding a third type to the in-memory merged operations queue compounds the documented pagination bottleneck, so it needs a check during planning
- Phase 9: research is required on the business-date / last-closed-date model and on report aggregation indexing

## Deferred Items

Items acknowledged and carried forward from previous milestone close:

| Category | Item | Status | Deferred At |
|----------|------|--------|-------------|
| *(none)* | | | |

## Session Continuity

Last session: 2026-09-26T02:12:14.522Z
Stopped at: Phase 4 context gathered (auto); Phase 3 hardening pending
Resume file: .planning/phases/04-guests-stay/04-CONTEXT.md
