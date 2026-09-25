# Phase 2: Rooms — Status Lifecycle & Grids - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-25
**Phase:** 02-rooms-status-lifecycle-grids
**Areas discussed:** Status model & occupancy, Transitions & who may move rooms, Grid semantics, Room board shape

---

## Status model & occupancy

| Option | Description | Selected |
|--------|-------------|----------|
| Two-axis: derive occupancy | rooms.status becomes housekeeping-only; occupied computed from checked-in reservation_rooms; data migration for existing 'occupied' rows | ✓ |
| Keep occupied in the same column | Extend the enum in place | |
| Separate occupancy column | rooms.occupancy maintained by check-in/out | |

**User's choice:** Two-axis: derive occupancy

---

## Transitions & who may move rooms

| Option | Description | Selected |
|--------|-------------|----------|
| Strict with inspection | dirty → cleaning → inspected → available | |
| Inspection optional | cleaning → available allowed | |
| Minimal: dirty ↔ available + maintenance | No cleaning/inspected states | ✓ |

**User's choice:** Minimal. REQUIREMENTS ROOMS-02 and ROADMAP criterion 2 were narrowed to match. Claude fixed `maintenance → dirty` as the only exit from maintenance (D-04).

| Option | Description | Selected |
|--------|-------------|----------|
| housekeeping + reception | Both presets get rooms.status; maintenance uses the same permission | ✓ |
| housekeeping only | Reception read-only | |
| housekeeping + reception; maintenance needs cms.edit | Extra gate for maintenance | |

**User's choice:** housekeeping + reception

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, any rooms.status holder | available → dirty allowed manually | ✓ |
| No, only check-out sets dirty | Manual transitions only move forward | |

**User's choice:** Yes, any rooms.status holder

---

## Grid semantics

| Option | Description | Selected |
|--------|-------------|----------|
| Free-room count | free + total per cell | |
| Boolean available/sold out | true/false per cell | |
| Count + booked breakdown | free, booked, total, out_of_order per cell | ✓ |

**User's choice:** Count + booked breakdown. Claude fixed `free = total − booked` (identical to the booking check) with `out_of_order` informational (D-08), and the 1..31 `days` range with 14 default.

| Option | Description | Selected |
|--------|-------------|----------|
| Effective nightly rate via pricing rules | base_price_usd adjusted per night by PricingRule | ✓ |
| Base price only | base_price_usd repeated | |
| Effective rate + which rule applied | plus rule scope | |

**User's choice:** Effective nightly rate. Claude added `rule_scope` to the cell anyway (cheap, derived from the same resolution) and locked that `QuoteReservationAction` is untouched this phase (D-10).

---

## Room board shape

| Option | Description | Selected |
|--------|-------------|----------|
| Status + occupancy + today's movement | room, housekeeping status (+changed_at/by), derived occupancy, arriving/departing/stayover flags, reservation uuid + guest name | ✓ |
| Status + occupancy only | No reservation details | |
| Full: also next arrival + notes | Notes arrive in Phase 3 | |

**User's choice:** Status + occupancy + today's movement

| Option | Description | Selected |
|--------|-------------|----------|
| Flat list, all rooms, filters | Unpaginated, sorted floor/number, filters status/floor/room_type | ✓ |
| Grouped by floor | Nested response | |
| Paginated like other lists | items + meta | |

**User's choice:** Flat list, all rooms, filters

---

## Claude's Discretion

- Resource class names; `reason` optional; `from` lower bound; test file names; whether per-room booked lists are returned (default counts only)
- Board permission `rooms.status|reservations.view`, grids `reservations.view` (D-11) — derived from the presets, not asked

## Deferred Ideas

- cleaning / inspected states; per-night quote pricing; rate editing from the grid; room notes on the board
