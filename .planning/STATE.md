---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
current_phase: 10
current_phase_name: Loyalty Points Program
status: executing
stopped_at: Completed 10-14-PLAN.md
last_updated: "2026-10-05T13:00:00.000Z"
last_activity: 2026-10-05
last_activity_desc: Completed 10-14 (GET /cms/loyalty/reports: per-type points over a hotel-local period plus outstanding points and USD liability, loyalty.view only)
progress:
  total_phases: 11
  completed_phases: 5
  total_plans: 98
  completed_plans: 76
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-25)

**Core value:** Every screen the dashboard and guest app already show works against a real, tested, convention-compliant `/api` endpoint instead of mock data.
**Current focus:** Phase 10 — Loyalty Points Program

## Current Position

Phase: 10 (Loyalty Points Program) — EXECUTING
Plan: 15 of 15
Status: Ready to execute
Last activity: 2026-10-05 — Completed 10-14 (GET /api/cms/loyalty/reports, loyalty.view)

Progress: [████████░░] 77%

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
**Per-Plan Metrics:**

| Plan | Duration | Tasks | Files |
|------|----------|-------|-------|
| Phase 10 P01 | 45min | 3 tasks | 34 files |
| Phase 10 P02 | 30min | 2 tasks | 18 files |
| Phase 10 P03 | 35min | 2 tasks | 3 files |
| Phase 10 P04 | 40min | 3 tasks | 17 files |
| Phase 10 P05 | 40min | 2 tasks | 5 files |
| Phase 10 P06 | resumed | 2 tasks | 16 files |
| Phase 10 P07 | 25min | 2 tasks | 13 files |
| Phase 10 P08 | 25min | 2 tasks | 15 files |
| Phase 10 P09 | 25min | 2 tasks | 15 files |
| Phase 10 P10 | 25min | 2 tasks | 9 files |
| Phase 10 P11 | 45min | 2 tasks | 7 files |
| Phase 10 P12 | 40min | 2 tasks | 5 files |
| Phase 10 P13 | 40min | 2 tasks | 13 files |
| Phase 10 P14 | 30min | 2 tasks | 12 files |

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
- [Phase 7]: Ticket timeline `ticket_actions` canonical and append-only; record-only recovery links Phase 5 folio credits; queue claim for all three types; reception/concierge gain `tickets.*` (A4 debt: chat + event inquiries ride on it)
- [Phase 8]: Event-inquiry routes re-gated to new `events.view|manage|deposit` (26 -> 29 permissions, 11 -> 12 groups), resolving the event half of the A4 debt; event deposit is a ledger-backed `payments` row (single writer, Idempotency-Key); `media.collection` added for venue menu files; `ReserveTableAction` stores true UTC (closed 3886416)
- [Phase 9 plan]: New `night_audit.manage` (29 -> 30 permissions, 12 -> 13 groups), no preset changes; audit GET gated `reports.view|night_audit.manage`; persisted business-date singleton advanced only by explicit `POST …/{audit}/close` (AUDIT-04 added); blockers only for unsettled departures + unassigned arrivals; reports use posted folio lines (revenue) and completed payments by payable type (collections), exact integer-cents aggregation
- [Phase ?]: [Phase 10-01] LoyaltyReward registered in RecycleBinRetentionTest::SOFT_DELETABLE now (auto-discovered purge list); 10-08 step 8 is verify-only
- [Phase ?]: [Phase 10-01] loyalty_rewards carries a standalone sort_order index (CmsListIndexTest) besides (is_active, sort_order); voucher/application FK indexes served by composite leftmost prefixes
- [Phase ?]: [Phase 10-02] LoyaltyMath rejects negatives in every entry point and LoyaltyProgram avoids ?? except minRedeemPoints(); a null rate or cap only ever means the capability is off
- [Phase ?]: [Phase 10-03] LoyaltyLedger is the only point mover; a clawback draws on an active/depleted origin even when unswept past expiry (points leave once), refund() takes only redeem entries and clawback() only earn entries
- [Phase ?]: [Phase 10-04] Loyalty settings PUT applies present keys only (explicit null clears); loyalty.view/manage/adjust seeded in no preset (30 -> 33 permissions, 13 -> 14 groups); loyalty.adjust sits in CmsAccessControlTest::$notYetBuilt until plan 10-07
- [Phase 10-05]: Earn runs inline in the folio-locked settle transaction at all three settlement statements; only UniqueConstraintViolationException is caught, so any other failure rolls the settlement back
- [Phase 10-06]: Lifetime earned/redeemed are net (earn + positive adjust - clawback; redeem - refund), floored at 0, from one grouped query; account runs 3 queries and a ledger page 7 regardless of size; reason/performed_by emitted for staff callers only; staff guest view gated loyalty.view alone (adjusters must also hold it)
- [Phase 10-07]: Manual adjust is one action under the guest lock (guest -> batches); zero and over-cap points are the domain error loyalty_adjustment_invalid (not a field rule); ledger key adjust:{guest_id}:{client_key} with actor in the replay payload (another desk = 409); loyalty.adjust is the last seeded loyalty permission to be route-enforced, so CmsAccessControlTest::$notYetBuilt is back to []
- [Phase 10-08]: Rewards bin routes sit inside the existing cms.restore/cms.purge groups (a loyalty.manage-only token gets 403 on the bin); the discount_usd/type pairing is judged in an after() hook on the effective post-update values; the guest catalog is `indexPublic` (active, non-trashed, sort_order then id) and needs no program settings; DELETE answers 204 like every other bin-backed CRUD
- [Phase 10-09]: Redeem is one action under the guest lock (guest -> batches) keyed `redeem:reward:{guest_id}:{client_key}`; the reward is re-read inside the write so a stale bound model cannot redeem a just-deactivated reward; voucher expiry is hotel-local end of day (Q13); a missing key answers the shared validation_failed shape (errors.idempotency_key), insufficient-points details sit under `context`; LoyaltyLedger is final, so atomicity is tested through a LoyaltyLedgerEntry::creating listener
- [Phase ?]: [Phase 10-11]: Booking replay compares stored facts (room type, dates, payment method, promo id, points, voucher code) under the room_type then guest locks and answers 200 before the availability check; the guest lock is taken only when loyalty fields are sent; total_usd is written net at create; staff and OTP bookings never carry loyalty keys
- [Phase 10-12]: Cancel re-checks status under the reservation row lock and reverses in the same transaction (reservation -> folio -> guest -> application/batches/voucher); refund and voucher restore come before the folio clawback; clawback floors at zero and logs loyalty.clawback_shortfall only for entries written by that call; ReverseLoyaltyForFolioAction is the unit-tested seam for the future folio refund flow; a plain cancel now costs a pinned 7 queries
- [Phase 10-13]: Expiry sweep is bookkeeping per batch (guest lock, then LoyaltyLedger::expire re-read FOR UPDATE) plus a locked per-voucher flip; the warning is one transaction per guest (row, push, markers) so a push failure rolls back and retries, one guest's failure never stops the others and makes the command exit non-zero; both commands run daily (01:00 and 09:00) in hotel time, withoutOverlapping
- [Phase 10-14]: The loyalty report is a plain LoyaltyReportService (no Phase 9 symbol, loyalty.view not reports.view) over the half-open HotelClock::dayWindow range of occurred_at, max 366 days; refunds are their own line, never issued; outstanding_points and liability_usd are point in time (active batches with expires_at > now) and liability is null while the redeem value is unset; the query count is pinned at 3

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
- Phase 8: generic `POST /service-bookings` can create `restaurant_table` bookings with a client-supplied instant (PR-9, deferred); they will show in the staff table-reservation list
- Phase 9 (planned): concurrent first-open / close serialization is MySQL-only (manual checks in 09-VALIDATION); the business date must be initialized once by a `night_audit.manage` holder after deploy

### Roadmap Evolution

- Phase 10 added: Loyalty Points Program

## Deferred Items

Items acknowledged and carried forward from previous milestone close:

| Category | Item | Status | Deferred At |
|----------|------|--------|-------------|
| *(none)* | | | |

## Session Continuity

Last session: 2026-10-05T13:00:00.000Z
Stopped at: Completed 10-14-PLAN.md
Resume file: None
