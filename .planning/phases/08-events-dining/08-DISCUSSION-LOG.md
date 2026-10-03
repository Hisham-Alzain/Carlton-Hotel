# Phase 8: Events & Dining - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves how they were made, the alternatives considered, and the dissent.

**Date:** 2026-10-02
**Phase:** 08-events-dining
**Mode:** fully automatic (owner instruction, standing memory: never ask the owner mid-milestone; route every gate decision to the Fable consultant/council and log the outcome)

## How the decisions were made — and what was different this time

1. **No Fable consultant and no ai-council were available for Phase 8.** An Opus 5.5 agent stood in for the Fable consultant. It inspected the codebase read-only (event-inquiry schema, service and routes; the permission seeder and presets; the payments table, `RecordCashPaymentAction`, `RecordFolioPaymentAction`, `IdempotentWrite`; `ReserveTableAction` and `HotelClock`; `DiningVenue`/`Media`/`PurgesMedia`; the dashboard mocks) and produced D-01..D-32, roadmap/requirements wording fixes, Claude's Discretion, and a Deferred list. Captured verbatim in CONTEXT.md.
2. **No council vote.** Phase 7 sent eight `convene: true` items to a three-member council (confidence 76). For Phase 8 the high-stakes items carry explicit **Risk** and **Dissent** notes in the decision text in place of a vote. The items that would have been convened under Phase 7's practice are listed below so a later council (or the owner) can review them after the fact.
3. **Id range.** The brief header announced "D-01..D-34"; the issued set is D-01..D-32. Checked: no numbering gap, no referenced-but-missing id. Treated as a header typo.
4. **Post-research rulings.** The Opus planning crew verified each decision's premise against the code (`08-RESEARCH.md`). Nine premise gaps needed a ruling; they were decided by the same Opus stand-in under the same delegation and recorded as PR-1..PR-9 at the end of CONTEXT.md. None was routed to the owner.
5. Gate: Phase 7 committed at 0961153 with the full suite green (2023 tests) — met before research started.

## Items that would have been convened (high stakes, reviewed by the stand-in alone)

| Decision | Why high-stakes | Stand-in's position | Recorded dissent |
|---|---|---|---|
| D-01 `staff_notes` column | Field-name contract; dashboard mock writes `notes` | New column; `notes` stays the guest brief | Dashboard mock sends `notes` — rejected as a semantic overload |
| D-02 checklist table, lazy rows | New table; shape of the checklist history | One table, enum template, activity log as history | JSON column / reuse `event_requirements` / template table — rejected |
| D-05 deposit state, no `deposit_usd` | Money; 3NF | `deposit_status` + `deposit_paid_at` over the `payments` ledger | Brief named `deposit_usd`; agreed-amount meaning deferred |
| D-07 `media.collection` | Shared table used by every CMS entity | Additive column with default `images` | Touching a shared table for one feature — accepted with test guard |
| D-12 `events.*` re-gate | Narrows access on routes Phase 7 opened | Split now; reception/concierge lose event access | Keep `tickets.*` / OR-gate — rejected |
| D-15 ledger-backed deposit | Money write; idempotency | Single writer through `RecordCashPaymentAction` + `IdempotentWrite` | Record-only number on the inquiry — rejected |
| D-22 `ReserveTableAction` tz fix | Changes stored instants for new bookings | Hotel-local in, UTC stored; no backfill | Filter on naive UTC date — rejected |

## Alternatives considered (per area)

### Schema
- Staff notes: overwrite `notes` (rejected, destroys the client brief) vs new `staff_notes` (chosen).
- Checklist: JSON column on `event_inquiries` (rejected, filterable state in JSON), reuse `event_requirements` with a done flag (rejected, guest vs staff data), configurable template table (deferred, YAGNI), lazy rows over an enum template (chosen).
- Deposit: `deposit_usd` copy of the paid amount (rejected, 3NF), agreed/required amount (deferred with partial deposits), status + timestamp over the ledger (chosen).
- Menu file: `dining_venues.menu_media_id` (rejected, leaks either into `images` or into the library), `menu_url` string (rejected, no file lifecycle), `media.collection` (chosen).

### Permissions
- Keep `tickets.*` and add only `events.deposit` (rejected: RFP PII stays readable by reception/concierge).
- Transitional `events.view|tickets.view` OR-gate (rejected: keeps the debt indefinitely).
- `dining.view` for the table-reservation list (rejected: sprawl for one read route; `service_requests.view` reused, housekeeping visibility accepted as low sensitivity).

### Money
- Partial/instalment deposits (deferred; needs `deposit_required_usd` + `partially_paid`).
- Auto-confirm on deposit (rejected: confirmation stays an explicit staff verb; the reservation coupling in `RecordCashPaymentAction` is not copied).
- Bank transfer / card methods (deferred: widening `PaymentMethod` would widen folio validation too).

### Dining
- Leave the timezone bug and filter on naive UTC dates (rejected).
- Fix `CreateServiceBookingAction` too (deferred; logged as PR-9).
- Menu download as a stream or redirect (rejected: the requirement says "returns the URL", the app opens it externally).

## Post-research rulings (summary; full text in CONTEXT.md)

| Ruling | Gap found | Outcome |
|---|---|---|
| PR-1 | Library list, `attachExisting` and nested destroy would see `menu` rows | All three scoped to `collection = images`; library update/purge stay unscoped by design |
| PR-2 | Dashboard summary `event_inquiries` rides on `tickets.view` | Gated on `events.view` |
| PR-3 | `guests.name` exists; D-23 named only first/last | Load `name, first_name, last_name`; `name` with fallback |
| PR-4 | Tables are hard-deleted; bookings can orphan | Row still listed with null table/venue |
| PR-5 | Asia/Damascus has no DST | DST unit test uses Europe/London |
| PR-6 | Discretion: migrate `EventInquiryService`? | Not migrated; methods added as-is |
| PR-7 | `EventInquiryResource` extends `JsonResource` | Detail extends list resource; base class unchanged |
| PR-8 | FQCN vs morph class in replay lookup | Test pins FQCN `payable_type` |
| PR-9 | Generic booking route can create table bookings | Deferred, documented as known gap |

## Deferred ideas

See CONTEXT.md § Deferred (unchanged by research, plus PR-9).
