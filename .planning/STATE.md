---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
current_phase: 6
current_phase_name: Housekeeping & Guest Services
status: complete
stopped_at: Phase 6 closed; Phase 7 context captured, ready to research/plan Phase 7
last_updated: "2026-09-27T00:00:00.000Z"
last_activity: 2026-09-27
last_activity_desc: "Phase 6 closed (d8ac795): housekeeping tasks, queue registry, SR board, departure services; housekeeping.* permissions (23->26, 11 groups); reception gains service_requests.update; 405 envelope fix (613a980). Full suite 1709 green."
progress:
  total_phases: 9
  completed_phases: 6
  total_plans: 43
  completed_plans: 33
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-25)

**Core value:** Every screen the dashboard and guest app already show works against a real, tested, convention-compliant `/api/v1` endpoint instead of mock data.
**Current focus:** Phase 7: Support Tickets & Queue

## Current Position

Phase: 6 of 9 (Housekeeping & Guest Services) — complete
Plan: 9 of 9 in current phase (06-01 .. 06-09 all done)
Status: Phase 6 closed; Phase 7 context committed (ad70f91), ready to research/plan Phase 7
Last activity: 2026-09-27 — Phase 6 closed (d8ac795) + 405 fix (613a980); full suite 1709 green.

Progress: [██████░░░] 67%

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
- [Phase 4]: Digital key is a display-only credential (NOT lock-grade); encrypted at rest with an HMAC-SHA256 lookup hash, hidden + non-fillable, excluded from activity log, revoked on check-out/cancel/reject/expiry, fresh code minted on next approval
- [Phase 4]: `GuestEntitlement::currentReservation()` stays frozen (FA-06-1 still deferred); `targetReservation()`/`targetFrom()` added as additive siblings, used only by Phase 4 staff surfaces
- [Phase 4]: New permissions `guests.view`/`guests.edit`, seeded on reception + concierge only (seeder baseline 19 -> 21 permissions, 9 -> 10 groups)

### Pending Todos

None yet.

### Blockers/Concerns

- Phase 5+ (carried from Phase 4): `FA-06-1` still deferred — `GuestEntitlement::currentReservation()` still resolves the most recent booking, not the checked-in stay; 8 call sites unchanged (`StayController`, `TableReservationController`, `TransportRequestController`, `FolioService::myFolio`/`approveMyFolio`, `PreArrivalService`, `ServiceBookingService`, `ServiceRequestService`). `targetReservation()`/`targetFrom()` now exist as the recommended replacement when a future phase takes this up.
- Phase 3 (carried, non-blocking): `FA-02-1` — occupancy is still night-based; a checked-in guest on the check-out day reads `vacant`/`departing_today: true` until checked out
- Phase 3 (carried, non-blocking): `FA-03-2` (MySQL only) — `QuoteReservationAction`'s `ends_on > check_in` comparison lacks `whereDate`, can exclude a pricing rule's last day on MySQL while the rates grid follows the inclusive window; recommended fix noted for the per-night pricing work
- Phase 3 (carried, non-blocking): `FA-03-3` — quote and grid both iterate pricing rules with no `ORDER BY`; pin `id` order in both paths together
- Phase 2 (carried, non-blocking): `rooms.floor` has no index; negligible at hotel scale, revisit if room count grows
- Phase 4 (carried, non-blocking): the digital key is display-only, NOT lock-grade; preconditions for any real lock integration (non-static OTP delivery, Sanctum token expiry, hash-based verifier) recorded in PITFALLS.md Pitfall 6
- Phase 4 (carried, non-blocking): guest documents still sit on the public disk (P7 concern in CONCERNS.md); ID scans reuse that same upload path
- Phase 4 (carried, non-blocking): GUEST-03/04/05/06 remain formally "unclassified" in the edge-probe ledger; flagged, not blocking, truths authored alongside in each 04-0N plan
- Phase 6: adding a third type to the in-memory merged operations queue compounds the documented pagination bottleneck, so it needs a check during planning
- Phase 9: research is required on the business-date / last-closed-date model and on report aggregation indexing

## Deferred Items

Items acknowledged and carried forward from previous milestone close:

| Category | Item | Status | Deferred At |
|----------|------|--------|-------------|
| *(none)* | | | |

## Session Continuity

Last session: 2026-09-27T00:00:00.000Z
Stopped at: Phase 5 closed; ready to execute Phase 6
Resume file: .planning/phases/05-folio-extensions/SUMMARY.md
