# Phase 3: Reservations Front-Desk Verbs - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-26
**Phase:** 03-reservations-front-desk-verbs
**Areas discussed:** Check-in vs assign-room, Check-out gate, Notes storage, Available rooms, Permissions, Status history, Events, Roadmap wording

The owner instructed (2026-09-26): "go fully auto; when a decision is needed ask my consultant Fable 5.1, don't ask me." A Fable 5.1 consultant agent decided all areas after inspecting the codebase; it flagged two as high-stakes, which were then deliberated by ai-council workflows (runs `wf_8ce723ee-2a6` check-in, `wf_ddae7c63-597` check-out). Council amendments are folded into CONTEXT.md.

---

## Check-in vs assign-room (council: Architect, Skeptic, User Advocate; confidence 78, not split)

| Option | Description | Selected |
|--------|-------------|----------|
| A: check-in owns the status flip; assign-room narrowed to pure assignment | optional room_uuid (explicit > pre-assigned > auto-pick), stay window, maintenance refused | ✓ (with hardenings) |
| B: keep assign-room; check-in as thin alias | two routes, no pre-arrival moves | |
| C: check-in requires a pre-assigned room, only flips status | two-step desk flow | |

**Hardenings adopted:** hotel-local business date via `config('hotel.timezone')`; a distinct `GuestCheckedIn` event keeps the room-ready push at check-in; one shared availability predicate/picker; docs/tests/dashboard switch in the same phase.
**Open questions resolved by the consultant role:** early check-in allowed only via `early_check_in: true` + mandatory reason, day-before only (Skeptic's A-prime); hotel timezone default `Asia/Damascus` (env-overridable); room-ready push fires at check-in and on room moves; the React dashboard is the only known caller of assign-room.
**Dissent kept:** the gap could be met additively without touching assign-room (loses if no external caller exists — none found).

---

## Check-out gate (council: Architect, Skeptic, Risk & Security; confidence 80, not split)

| Option | Description | Selected |
|--------|-------------|----------|
| A: explicit `force` for folios.settle holders, logged, folio stays open, shared action for guest path | | ✓ (with amendments) |
| B: silent bypass for folios.settle holders | records nothing; reception already holds the permission | |
| C: never bypass | pushes staff to fake cash settlements | |
| D: auto-settle zero-balance folios | invents a settlement without a Payment row | |

**Amendments adopted:** `CheckOutMode {NONE, STAFF_FORCE, GUEST_EXPRESS}` with distinct audit labels; nullable system actor on `UpdateRoomStatusAction`; reservation + folio row locks with the gate evaluated inside; 403 for force-without-permission vs 422 `folio_unsettled`; idempotent "ensure dirty" over all reservation rooms; `GenerateFolioAction` must not rebuild items for checked-out reservations; a `folio_status` filter on the reservations index so overrides are read.
**Open questions resolved by the consultant role:** `reason` mandatory with `force`; guest express stays unguarded until a gateway exists; activity_log is the audit trail for now (first-class column deferred); the closing path for open folios is deferred to night audit.
**Dissent kept:** the gate is theatre while guests can self-checkout unsettled; room-dirty as a listener rather than inline (loses while the ensure-dirty guard is implemented and tested).

---

## Notes storage (consultant)

| Option | Description | Selected |
|--------|-------------|----------|
| Single nullable text column, PATCH replaces | LogsActivity keeps history | ✓ |
| Append-only reservation_notes table | per-author entries | |
| Column + explicit history | | |

---

## Available rooms (consultant)

Flat list for the reservation's room type, free for its dates, excluding maintenance, current room flagged `assigned`, ≤ 3 queries; multi-room deferred.

## Permissions (consultant)

No new permissions (`reservations.view` for reads, `reservations.create` for writes, `folios.settle` for the money override). Rejected: `reservations.checkin/checkout` (sprawl).

## Status history (consultant)

Deferred; `LogsActivity` on `Reservation` already records status transitions with causer.

## Events (consultant)

`ReservationCheckedOut` after commit, no listener this phase; room-dirty inline. Check-in dispatches `GuestCheckedIn` (council hardening).

## Roadmap wording (consultant)

Criteria 3, 4 and 5 and RESV-03/RESV-04 reworded: occupancy is derived (no `rooms.status` write at check-in); check-out refuses 422 `folio_unsettled` unless a `folios.settle` holder sends `force: true` (logged); "no new permissions" in the summary.

## Claude's Discretion

Predicate placement, config file, test names, string wording, Postman ordering.

## Deferred Ideas

Multi-room verbs; no-show; early-check-in pricing; open-folio closing path; `reservation_status_history`; dedicated check-in/out permissions; balance-guarded guest express.
