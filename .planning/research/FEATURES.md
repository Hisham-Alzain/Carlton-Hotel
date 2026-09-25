# Feature Research

**Domain:** Hotel Property Management System (PMS) — front-desk, housekeeping, folio, support-ticket, and reporting modules for a mid-scale property backend
**Researched:** 2026-09-25
**Confidence:** MEDIUM-HIGH (established PMS domain conventions from Mews/Cloudbeds/Opera/Apaleo docs and hospitality operations literature; no direct API access to those vendors' internal schemas)

## Scope Note

This is a **subsequent milestone**. Booking, folio basics (view/settle/manual payment), service requests, chat, and the operations queue are already validated (P1–P10) — see PROJECT.md. This document covers only the 12 target feature areas that are net-new gaps, and treats existing modules as fixed integration points (e.g., housekeeping tasks link to the existing `Reservation`/room model, not a new booking engine).

## Feature Landscape

### Table Stakes (Users Expect These)

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Room board (grid of all rooms by floor/type, color-coded status) | Front desk's single most-used screen in every PMS (Opera's "Rooms" view, Mews' "Timeline", Cloudbeds' "Front Desk" grid); without it staff work blind | MEDIUM | Read model over existing `rooms` + `reservations`; no new domain table needed, just a query/resource assembling current occupancy + housekeeping status per room |
| Room status lifecycle (housekeeping side): Dirty → Clean → Inspected, crossed with Vacant/Occupied | Universal hospitality convention (VD/VC/VI/OD/OC/OI codes); housekeeping and front desk both key off this | LOW-MEDIUM | Add `housekeeping_status` (dirty/clean/inspected) as a column/enum on room or a `room_status` table with history — "status + history over boolean flags" per project's DB constraint |
| Out-of-order / out-of-service exception states | Every PMS has OOO/OOS; without it maintenance rooms get sold or shown available | LOW | Model as status values, not a separate boolean, so history and reason are preserved |
| 14-day (or configurable-range) availability grid by room type | Standard front-desk/revenue tool in every PMS (Cloudbeds "Availability" tab, Opera "Reservation Availability") — used to spot sell-outs and place walk-ins | MEDIUM | Derive from existing `CheckAvailabilityAction`; aggregate per room type per day, do not re-implement inventory logic |
| Rate grid alongside availability (rate per room type per day) | Front desk and revenue staff expect rate-shopping in the same screen as availability | LOW-MEDIUM | Read from existing rate/pricing data (room type base rate ± any date overrides already in CMS); if no date-specific rate table exists yet, flat rate per room type is an acceptable v1 |
| Reservation notes (free text, staff-authored, timestamped, attributable to author) | Every PMS has a notes/comments field on a reservation visible to all front-desk staff (shift handoff) | LOW | Simple child table `reservation_notes` (uuid, reservation_id, author, body, created_at); no threading needed |
| Explicit check-in / check-out actions with guardrails | Table stakes: you cannot check in before room is Inspected/Clean and Vacant, cannot check in a reservation not in "confirmed" state, cannot check out with an open (unsettled) folio without an override | MEDIUM | Reuse existing folio settle logic; check-in/out are Actions (verb-named), not raw status PATCH, so business rules stay centralized |
| List of rooms available for a given reservation (for room assignment/change) | Needed anytime front desk assigns or reassigns a room; already partially exists via `AssignRoomAction` — this is the missing "what can I assign" read endpoint | LOW | Query existing available-room logic already backing `AssignRoomAction`, exposed as a list endpoint |
| Guest directory / guest profile view for staff (stay history, contact info, notes) | Every PMS has a "Guest Profile" search (Opera Guest Search, Cloudbeds Guest List) — front desk needs this before any personalized service | LOW-MEDIUM | `Guest` model already exists; add staff-facing list/search + a `guest_notes` child table |
| Guest preferences (room type, floor, pillow type, dietary, etc.) stored and surfaced at booking/check-in time | Table stakes in any PMS with a CRM layer; guests expect "remembered" preferences on repeat stays | LOW-MEDIUM | Key-value or fixed-column preferences on `Guest`/`GuestProfile`; surfaced (not enforced) at assignment time |
| Pre-arrival checklist per reservation (ID verified, payment authorized, preferences confirmed, room assigned) | Standard in Opera/Mews arrivals workflow — front desk needs a single glance to know what's blocking a smooth arrival | LOW-MEDIUM | Can be **computed**, not stored: derive checklist items from existing state (folio balance, room assignment, document upload, ID scan) rather than a separate table — avoids a second source of truth |
| Online check-in: guest submits arrival time estimate ahead of stay | Standard guest-app feature (Mews Guest Journey, Cloudbeds' Guest Experience); low complexity, high perceived value | LOW | Simple field on reservation or a small `online_checkin` record; write path only, no lock integration |
| ID scan wired into existing document upload | PROJECT.md explicitly calls for reusing existing upload pipeline — this is table stakes for any check-in-by-mobile flow (contactless check-in requires ID capture per multiple vendor guides) | LOW | Tag existing document-upload record with a `purpose = id_scan` rather than building a parallel upload path |
| Folio: itemized line items, running balance, payments ledger | Core PMS accounting primitive (Opera's "Folio Window", every PMS's guest ledger); folio *viewing* already exists — this milestone adds the write path (post charge, add payment) | MEDIUM | DECIMAL money, immutable line items (void + reissue, never edit-in-place, to preserve audit trail — matches project's "status + history" convention) |
| Line-item dispute: guest flags a charge, staff resolves it | Standard guest-self-service pattern once folios are guest-visible (any PMS with a guest portal supports "question this charge") | MEDIUM | Dispute as its own state on the line item (`open → resolved/rejected`) with a resolution note, not a delete — preserves audit trail |
| Housekeeping task list with assignment and status | Universal housekeeping module (Opera Housekeeping, Cloudbeds Housekeeping App, ALICE/Optii for larger properties) | MEDIUM | Real table per PROJECT.md decision ("Housekeeping tasks are a real table"); links to room + assignee + status |
| Housekeeping task auto-generation tied to room status changes (checkout → "needs cleaning" task) | Table stakes — without auto-generation, someone has to manually create a task for every departure, defeating the purpose | MEDIUM | Trigger task creation on checkout Action completing; keep it inside the same transaction or as a listener, not a cron poll |
| Departure services (checklist of things due before/at checkout: late checkout requests, luggage, transport) | Every PMS surfaces a "departures today" board; project already decided this is a **projection**, not a new table | LOW-MEDIUM | Derive from existing reservations (departure date = today) + existing service requests filtered by category; no new persistence |
| Support ticket list, create, status transitions | Basic helpdesk table stakes once tickets are a first-class object (already partially implied by "queue items can be claimed") | MEDIUM | Standard states: `open → assigned → in_progress → resolved → closed`, plus `escalated` as an overlay flag/state |
| Support ticket assignment + claim from queue | Every ops/helpdesk tool (Zendesk, Freshdesk, and PMS "guest requests" modules like Cloudbeds' Guest Experience Task tool) supports self-claim as well as manager-assign | LOW-MEDIUM | Reuse existing polymorphic `AssignRequestAction` pattern already in the codebase |
| Support ticket reply thread (staff-authored, internal by default) | Table stakes for any support object; PROJECT.md decision explicitly scopes this to `ticket_actions` (internal log), not guest-visible messages yet | LOW-MEDIUM | Do not conflate with the existing `Conversation`/`Message` guest-chat system — keep them separate per the Key Decision already logged |
| Support ticket escalation | Standard helpdesk pattern (SLA breach or explicit "escalate" button raising priority/visibility to a manager) | LOW-MEDIUM | Escalation = state/priority change + notification, not a new ticket type |
| Night audit: per-business-date checks and blockers | Universal hotel back-office function — every PMS (Mews, Cloudbeds, Opera, RoomRaccoon) runs this nightly; checks include unprocessed no-shows, unassigned arrivals, open folios past checkout, room status/occupancy mismatches | MEDIUM-HIGH | Read-only "run checks, list blockers" endpoint first; do NOT build automatic date-rollover in this milestone (see Anti-Features) — that is a much bigger, riskier feature than "checks and blockers" as scoped |
| Reports dashboard (occupancy, ADR-style summary, arrivals/departures, revenue) | Every PMS has a manager dashboard/flash report; `reports.view` permission already seeded per PROJECT.md, signaling this was anticipated | MEDIUM | Aggregate existing data (reservations, folios, room status); no new source-of-truth tables, this is a reporting/read layer |
| Event inquiry checklist + deposit tracking | Standard for any venue/RFP pipeline (Opera Sales & Catering, Tripleseat-style checklists: contract signed, deposit received, menu confirmed, setup confirmed) | LOW-MEDIUM | Checklist items + a deposit amount/status field on the existing event inquiry; reuse folio/payment pattern for deposit if it must be a real charge |
| Table reservation listing for events | Basic list view once table reservations exist as a concept tied to event spaces/dining | LOW | Straightforward index endpoint; likely already has an underlying model from P6 |
| Venue menu download for guests | Simple static-asset/document serving off existing media pipeline | LOW | Attach existing media/document to venue; guest-facing download endpoint |

### Differentiators (Competitive Advantage)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Computed pre-arrival checklist (vs. manually-ticked in most legacy PMS) | Removes a whole class of "checklist says done but nothing changed" bugs; always reflects real system state | LOW (given existing data) | Differentiator specifically *because* competitors like Opera store checklist state as separate flags that drift from reality |
| Housekeeping status + task board tightly coupled (status flips automatically on task completion, not two disconnected screens) | Many mid-market PMSs (and this project's own dashboard mocks) treat room status and housekeeping tasks as separate manual updates; wiring them transactionally reduces stale-room-board bugs | MEDIUM | Requires the checkout→task and task-complete→status-flip logic to live in one Action, not two independently-triggered endpoints |
| Departure services as a live projection instead of a stored checklist | Avoids a second source of truth that goes stale (a common Opera/legacy complaint: "departure list shows a task that was already done") | LOW-MEDIUM | Genuine architectural differentiator per the project's own key decision |
| Folio disputes as guest-visible workflow (not just staff-internal notes) | Most budget PMSs make guests call the front desk to dispute a charge; a guest-facing dispute-and-track flow is closer to what OTA/fintech apps train guests to expect | MEDIUM | Aligns with this project's guest-app-first posture (Flutter guest app already has folio view) |
| Night audit as "checks and blockers" report rather than opaque black-box batch job | Gives staff visibility into *why* audit can't close (e.g., "3 unassigned arrivals", "1 unsettled departure") instead of a pass/fail | MEDIUM | Genuinely useful differentiator vs. legacy PMS night-audit screens that just say "cannot proceed" |
| Unified reports dashboard combining occupancy + tickets + housekeeping SLA in one view | Most PMS reporting is siloed per module (housekeeping reports separate from revenue reports); a single ops dashboard for a boutique property is a real differentiator | MEDIUM-HIGH | Scope carefully — start with occupancy/revenue/arrivals-departures per PROJECT.md's "reports dashboard endpoint" (singular), don't over-build |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Real digital-key / physical door-lock integration (Assa Abloy, Salto, mobile-key SDKs) | "Digital key" sounds like it means unlocking doors from a phone, which is the industry buzzword | Needs a vendor lock hardware contract, SDK integration, and provisioning infra — explicitly out of scope for this milestone (no such vendor decision made); huge scope and security surface for a backend-gap-closure milestone | Model "digital key" as a system-generated access code/reference shown in the guest app tied to the reservation (a data field), not an actual lock-unlock capability. Document clearly that hardware integration is future work |
| Automatic night-audit date rollover (auto-closing the business day and opening the next) | Feels like "real" night audit automation seen in Mews/Cloudbeds marketing | Auto-rollover interacts with every financial and inventory subsystem (rate changes, no-show billing, room night counters); doing it wrong silently corrupts historical data across the whole system — far riskier than the explicitly scoped "checks and blockers" | Build the read-only checks/blockers report only, as scoped in PROJECT.md; leave actual date-rollover as a manual, later decision |
| Real-time websocket-driven room board / ticket queue | Live PMS boards feel snappier and match user expectations from consumer apps | PROJECT.md explicitly puts this out of scope for the milestone (polling is acceptable); adding sockets now increases infra surface (connection scaling, auth over websockets) for a backend that's mid-gap-closure | Poll-based refresh (existing operations-queue precedent already does this); document expected poll interval for frontend team |
| Guest-visible ticket replies mirrored into the existing chat `Conversation`/`Message` system | Seems convenient — "why have two message systems?" | Council already rejected this for this milestone: no confirmed requirement for guest-visible ticket replies, and duplicating message data (even with a nullable `message_id` bridge) creates an undocumented, hard-to-reason-about coupling between two domains | Keep ticket replies in `ticket_actions` (internal-only log); leave the `message_id` bridge column nullable/reserved but unused until a real guest-facing-reply requirement is validated |
| Full CRM-style guest preference taxonomy (hundreds of preference categories, tagging systems, loyalty tiers) | "More personalization data is always better" | Massive schema and UI surface for a feature that, per PROJECT.md, is scoped to "notes and pre-arrival checklist" plus basic preferences — building a taxonomy engine now is scope creep the milestone explicitly avoids | Small fixed set of preference fields + a free-text notes field; expand later if a loyalty/CRM milestone is scoped |
| Automated rate-shopping / dynamic pricing engine bundled into the rate grid | Rate grids invite "why not add revenue-management automation while we're here" | Dynamic pricing is a distinct product problem (competitor rate scraping, demand forecasting) far beyond "front desk sees a rate grid"; conflating the two turns a LOW/MEDIUM read-only feature into a HIGH-complexity ML/ops project | Ship a plain read-only rate grid off existing CMS rate data; treat dynamic pricing as an explicit future milestone if ever prioritized |
| Housekeeping task auto-scheduling/optimization (route planning, staff load-balancing algorithms) | Larger PMS add-ons (Optii, ALICE) market this as a big value-add | Requires staff shift data, room-distance modeling, and an optimization algorithm — well beyond "list, assign, status" as scoped | Manual/simple round-robin or supervisor-driven assignment; task list + status is enough for this milestone |
| SMS/WhatsApp notifications for every ticket/housekeeping/audit event | Feels like it "completes" the workflow end-to-end | Explicitly out of scope per PROJECT.md (no OTP/notification provider chosen yet); adding ad hoc notification channels per feature multiplies vendor and cost decisions the project has deliberately deferred | Use existing Firebase push + in-app notification patterns already built in P9; do not add new channels |

## Feature Dependencies

```
Room status lifecycle (housekeeping_status column/history)
    └──requires──> existing Room/RoomType models (P3)

Room board (front-desk grid)
    └──requires──> Room status lifecycle
    └──requires──> existing Reservation/Stay state (P4/P5)

Availability grid
    └──requires──> existing CheckAvailabilityAction (P4)

Rate grid
    └──enhances──> Availability grid (same screen, same date range)

Check-in / check-out verbs
    └──requires──> Room status lifecycle (room must be Vacant+Inspected to check in)
    └──requires──> Folio settle logic (P8) (checkout blocked on unsettled balance, unless overridden)
    └──requires──> "Rooms available for reservation" listing (for check-in room assignment)

Housekeeping task auto-generation
    └──requires──> Check-out verb (checkout triggers "needs cleaning" task)
    └──enhances──> Room status lifecycle (task completion flips room status)

Departure services (projection)
    └──requires──> Reservations (departure date) + existing service requests (P7)
    └──conflicts with──> storing departure checklist as its own table (project decision: derive, don't duplicate)

Pre-arrival checklist (computed)
    └──requires──> Folio (payment authorized) + Room assignment + Document upload (ID scan) + Guest preferences
    └──conflicts with──> storing checklist state separately (drift risk)

Online check-in + digital key
    └──requires──> existing document upload pipeline (ID scan reuse)
    └──requires──> Guest directory / preferences (arrival time, preference capture)
    └──enhances──> Pre-arrival checklist (marks ID/payment/preferences items done)

Guest directory (profile + notes + preferences)
    └──requires──> existing Guest model (P1/P7)

Folio line items + payments
    └──requires──> existing Folio view/settle (P8)
    └──enhances──> Check-out verb (balance check)

Folio disputes
    └──requires──> Folio line items (a line item must exist to dispute)

Support tickets (list/create/status)
    └──requires──> existing polymorphic AssignRequestAction/UpdateRequestStatusAction (P7/P10) for assign+status reuse
    └──conflicts with──> merging replies into Conversation/Message (explicit decision against this)

Ticket escalation
    └──requires──> Support ticket status/priority model

Night audit (checks and blockers)
    └──requires──> Folio (unsettled balances check), Reservations (unassigned arrivals, no-shows), Room status (occupancy mismatch check)

Reports dashboard
    └──requires──> Reservations, Folio, Room status, Housekeeping tasks, Support tickets (aggregates across nearly everything else)
    └──requires──> existing reports.view permission (already seeded)

Event checklist + deposit
    └──requires──> existing event inquiry pipeline (P6)
    └──enhances──> Event inquiry pipeline (adds tracking fields, not a new pipeline)

Table reservation listing
    └──requires──> existing event/dining venue models (P6/P3)

Venue menu download
    └──requires──> existing media/document storage (P3)
```

### Dependency Notes

- **Room board requires Room status lifecycle:** the board is a read view over housekeeping status crossed with occupancy; without the status column/history, there is nothing to render beyond raw occupancy.
- **Check-in/check-out require Room status lifecycle and Folio settle logic:** these are the two hard guardrails every PMS enforces (don't sell a dirty room, don't check out with an open balance) — build status lifecycle and confirm folio settle semantics *before* wiring the check-in/check-out Actions, or the guardrails will be bolted on after the fact.
- **Housekeeping task auto-generation enhances Room status lifecycle:** this is the differentiator pairing — build task creation and status-flip-on-completion as one coupled unit (ideally in the same Action/transaction as checkout and task-complete) rather than as two independently-evolving features, to avoid the classic "board says clean, task list disagrees" bug.
- **Departure services and Pre-arrival checklist both conflict with adding new persistent tables for themselves:** both are explicitly scoped (in PROJECT.md and by domain convention) as projections/computations over existing data. Treat any temptation to add a `departure_checklist` or `prearrival_checklist` table as a signal to re-derive instead.
- **Ticket escalation and reply depend on the ticket state model, not on the guest-chat system:** keep `ticket_actions` and `Conversation`/`Message` as separate concerns per the already-logged Key Decision; only revisit if a future milestone explicitly validates guest-visible replies.
- **Reports dashboard is the widest fan-in dependency:** it should be planned last among these features (or built with the narrowest possible v1 scope) since it reads from nearly every other new and existing table; building it early against unstable schemas invites rework.
- **Night audit depends on nearly-final state of Folio and Reservation check-in/out logic:** the "blockers" it reports (unsettled folios, unassigned arrivals, no-shows) are only meaningful once those verbs exist and are stable.

## MVP Definition

### Launch With (v1 — matches PROJECT.md Active scope)

- [ ] Room status lifecycle (dirty/clean/inspected × vacant/occupied + OOO/OOS) — foundation for board, check-in/out, housekeeping
- [ ] Room board (read endpoint over status + occupancy)
- [ ] 14-day availability grid + rate grid (read-only, off existing CMS/availability logic)
- [ ] Reservation notes (add/list)
- [ ] Explicit check-in / check-out verbs with guardrails (room status, folio balance)
- [ ] "Rooms available for reservation" listing
- [ ] Guest directory (profile list/search, notes, basic preferences)
- [ ] Pre-arrival checklist (computed, not stored)
- [ ] Online check-in (arrival time capture) + digital key as a data field (not lock hardware)
- [ ] ID scan tagged onto existing document upload
- [ ] Folio line-item post, payment record, dispute raise/resolve
- [ ] Housekeeping tasks (list/assign/status) wired to room status
- [ ] Service-request board (staff view over existing requests)
- [ ] Departure services (projection over reservations + requests)
- [ ] Guest quick requests mapped to catalogue items
- [ ] Support tickets: list/create/status/assign/reply/escalate, queue claim, staff-list-for-assignment
- [ ] Event inquiry checklist + deposit + notes
- [ ] Table reservation listing
- [ ] Venue menu download
- [ ] Night audit (checks and blockers, per business date — read-only)
- [ ] Reports dashboard (occupancy, arrivals/departures, revenue summary — narrow v1)

### Add After Validation (v1.x)

- [ ] Guest-visible ticket replies (only if a real requirement surfaces — currently deliberately deferred)
- [ ] Rate grid with date-specific overrides/promotions surfaced inline (if base-rate-only proves insufficient)
- [ ] Housekeeping SLA tracking / turnaround time metrics feeding into reports dashboard
- [ ] Expanded reports (department-level breakdowns, export formats)

### Future Consideration (v2+)

- [ ] Real digital-key/lock hardware integration — needs vendor selection, out of scope until then
- [ ] Automatic night-audit date rollover — needs its own dedicated design/migration plan, high blast radius
- [ ] Dynamic pricing / revenue management automation
- [ ] Housekeeping route optimization / staff load-balancing
- [ ] Websocket-based live room board and ticket queue
- [ ] CRM-grade guest preference/loyalty taxonomy

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Room status lifecycle | HIGH | LOW-MEDIUM | P1 |
| Room board | HIGH | MEDIUM | P1 |
| Check-in/check-out verbs + notes | HIGH | MEDIUM | P1 |
| Availability + rate grid | HIGH | MEDIUM | P1 |
| Guest directory + preferences + pre-arrival checklist | MEDIUM-HIGH | LOW-MEDIUM | P1 |
| Online check-in + digital key (data field) | MEDIUM | LOW | P1 |
| Folio line items/payments/disputes | HIGH | MEDIUM | P1 |
| Housekeeping tasks | HIGH | MEDIUM | P1 |
| Departure services (projection) | MEDIUM | LOW-MEDIUM | P1 |
| Support tickets (full lifecycle) | HIGH | MEDIUM | P1 |
| Night audit (checks and blockers) | MEDIUM-HIGH | MEDIUM-HIGH | P1 (but sequence last — depends on everything above) |
| Reports dashboard | MEDIUM | MEDIUM | P1 (narrow v1); wider version P2 |
| Event checklist + deposit + table reservations + menu download | MEDIUM | LOW-MEDIUM | P1 |
| Guest-visible ticket replies | LOW (unvalidated) | MEDIUM | P3 |
| Real digital-key hardware | MEDIUM (future) | HIGH | P3 |
| Automatic night-audit rollover | MEDIUM (future) | HIGH | P3 |

**Priority key:**
- P1: In scope for this milestone (per PROJECT.md Active list)
- P2: Should have, natural next milestone
- P3: Explicitly deferred / out of scope this milestone

## Standard State Machines

### Room Status (housekeeping × occupancy)

```
Occupancy axis:  Vacant ──(check-in)──> Occupied ──(check-out)──> Vacant
Cleanliness axis: Dirty ──(cleaned)──> Clean ──(inspected)──> Inspected ──(next occupancy or re-dirtied)──> Dirty

Exception states (orthogonal, can apply at any point): Out-of-Order (OOO), Out-of-Service (OOS)
```
- A room typically must be **Vacant + Inspected** (or at minimum Vacant + Clean, per property policy) before check-in is permitted.
- Check-out does not by itself clean the room — it flips occupancy to Vacant and (per this project's differentiator) auto-creates a Dirty housekeeping task.
- OOO/OOS rooms are excluded from availability and the room board should visually distinguish them from the cleanliness cycle.

### Housekeeping Task

```
Created (auto on checkout, or manual) → Assigned → In Progress → Completed → (verified/inspected, optional)
                                                                 └──> flips linked room's housekeeping_status
```
- Standard PMS housekeeping apps (Cloudbeds Housekeeping, Opera) support unassigned tasks sitting in a pool for claim, plus supervisor-assigned tasks — support both.

### Reservation Check-in/Check-out (this milestone's added verbs, layered on existing reservation states)

```
Confirmed ──check-in verb──> Checked-in (In-house) ──check-out verb──> Checked-out
                    │                                        │
                    └── blocked if room not Vacant+Inspected └── blocked if folio balance > 0 (unless override)
```

### Support Ticket

```
Open ──assign/claim──> Assigned ──> In Progress ──> Resolved ──> Closed
  │                        │
  └──escalate (any active state)──> Escalated (priority/visibility change, not a separate terminal state)
  └──reopen (from Resolved/Closed, time-boxed per policy)──> Open
```
- "Recovery actions" (per PROJECT.md) map to standard service-recovery patterns: compensation note, comp/discount reference, or escalation — logged as `ticket_actions`, not new states.

### Night Audit (per business date)

```
Not started → Checks running → Checks passed (no blockers) / Blockers present
                                        │
                          Blockers present ──(staff resolves underlying issue, e.g. processes no-show, settles folio)──> re-run checks
```
- This milestone scopes only the check/blocker reporting, not the close-and-roll-forward action — matches PROJECT.md's explicit "checks and blockers" wording, deliberately narrower than full automated audit.

## Sources

- [Room Status Cycle (Diagram) In Housekeeping | Hotels - SetupMyHotel](https://setupmyhotel.com/hotel-staff-training/housekeeping-training/room-status-cycle-diagram-in-housekeeping-hotels/) — MEDIUM confidence (industry training reference, cross-checked against vendor docs)
- [Housekeeping room conditions – Cloudbeds Help Center](https://myfrontdesk.cloudbeds.com/hc/en-us/articles/216540808-Housekeeping-room-conditions) — HIGH confidence (vendor official docs)
- [Housekeeping Automation FAQs — Hotel Room Status Codes & Definitions — Optii](https://help.optiisolutions.com/housekeeping-automation-faqs-hotel-room-status-codes-definitions) — HIGH confidence (vendor official docs)
- [What Is a Night Audit in a Hotel? Steps and Importance | Mews](https://www.mews.com/en/blog/hotel-night-audit-automation) — HIGH confidence (vendor official content)
- [Enhancements to the Night Audit Process in Cloudbeds PMS – Cloudbeds](https://myfrontdesk.cloudbeds.com/hc/en-us/articles/31030047512347-Enhancements-to-the-Night-Audit-Process-in-Cloudbeds-PMS) — HIGH confidence (vendor official docs)
- [The Hotel Night Audit Process – An Essential Guide](https://deliverback.com/blog/night-audit-process/) — MEDIUM confidence (industry blog, corroborates vendor docs)
- [Contactless Check-In Software for Hotels: A Complete Guide — StayNTouch](https://www.stayntouch.com/articles/contactless-check-in-software-hotels) — MEDIUM-HIGH confidence (PMS vendor content)
- [Hotel Mobile Check-In: Complete Guide & Checklist — Canary Technologies](https://www.canarytechnologies.com/post/hotel-mobile-check-in) — MEDIUM-HIGH confidence (guest-tech vendor, cross-checked against StayNTouch)
- `.planning/PROJECT.md` and `.planning/codebase/ARCHITECTURE.md` — HIGH confidence (primary project sources, already-validated scope and conventions)

---
*Feature research for: Hotel PMS backend (Carlton Hotel), subsequent milestone — API gap closure*
*Researched: 2026-09-25*
