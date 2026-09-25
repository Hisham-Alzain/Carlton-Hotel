# Pitfalls Research

**Domain:** Hotel PMS operational features (room status/housekeeping, folio money handling, support tickets, night audit, guest directory/digital key, event deposits, reports) added to an existing Laravel 13 backend
**Researched:** 2026-09-25
**Confidence:** HIGH (grounded in this codebase's own documented incidents in `CONCERNS.md` and its enforced conventions in `CONVENTIONS.md`); MEDIUM on generic hotel-industry night-audit/digital-key claims (web sources, not vendor-verified)

## Critical Pitfalls

### Pitfall 1: Room status conflated with availability

**What goes wrong:**
"Dirty" / "clean" / "inspected" (housekeeping state) gets stored on or merged with the same field that drives whether a room can be assigned/booked. A room marked `dirty` becomes unassignable even though it's vacant and bookable for a future date; or a room that's `clean` gets double-assigned because clean was read as "available."

**Why it happens:**
Both concepts answer "can I put a guest in this room" from a front-desk person's point of view, so it's tempting to model them as one status enum. But they have different lifecycles: availability is date-ranged (reservation_rooms/reservation dates), housekeeping status is point-in-time (current physical state). The existing `AvailabilityService::checkAvailability()` already treats availability as a date-range query, not a room flag — the new room-status feature must not retrofit a flag onto that model.

**How to avoid:**
Model room status (`clean`/`dirty`/`inspected`/`out_of_order`) as its own column/table on `Room` (or a `room_status_log` for history, per this project's "status + history over boolean flags" DB rule), completely independent from the reservation-date-driven availability query. The room board reads both and presents them together; `AssignRoomAction`/availability checks never read housekeeping status as a gate unless a new explicit business rule says so (e.g., "out_of_order" rooms should be excluded from availability — model that as an explicit override, not a repurposed status field).

**Warning signs:** A migration that adds `status` to `rooms` and then `AvailabilityService` or `CheckAvailabilityAction` starts referencing `rooms.status` in a `where()`. Tests that assign a room "because it's clean" instead of "because no reservation overlaps."

**Phase to address:** Front-desk room board / room status phase (first operational feature — sets the pattern others reuse).

---

### Pitfall 2: Folio money handling regresses the already-fixed idempotency and locking invariants

**What goes wrong:**
New folio capabilities (post line items, record payments, raise/resolve disputes) are built as fresh endpoints that don't reuse `GenerateFolioAction`'s hard-won invariants — leading to duplicate line items on retry, double-settlement races, or a dispute that "resolves" while a payment is mid-flight, corrupting the balance.

**Why it happens:** This exact bug class already happened once in this codebase (P8: TOCTOU double-settle, fixed with `lockForUpdate()` inside a transaction) and once more subtly (settled-folio drift on regeneration). New folio writers are a different code path than `GenerateFolioAction`, so the fix doesn't automatically apply — each new mutation (post line item, record payment, open/resolve dispute) needs its own transaction + row lock, not inherited protection.

**How to avoid:**
- Every folio-mutating action wraps in `DB::transaction()` and takes `lockForUpdate()` on the folio row (or reservation row) before checking/changing balance state — mirror the exact pattern already in `GenerateFolioAction`.
- Payments and disputes must be idempotent under retry: generate a client-safe idempotency key (or dedupe on `(folio_id, reference)`) so double-submits (double-tap on a slow network, guest app retry) don't double-charge or double-file a dispute.
- Disputes need an explicit state machine (`open → under_review → resolved/rejected`) with only one dispute open per line item at a time — enforce via a unique partial constraint or an application-level lock, not just UI prevention.
- All monetary columns stay `DECIMAL`, never float — this is already a project constraint (per PROJECT.md), but it's worth a lint/reviewer checklist item for every new `*_usd` column.

**Warning signs:** A new folio-related migration or action that does not call `DB::transaction` or `lockForUpdate`; a payment or dispute endpoint with no way to detect a duplicate submission; a `float`/`double` column for money.

**Phase to address:** Folio line items / payments / disputes phase. Verify with a concurrency test (two simultaneous payment requests against the same folio) analogous to `ConcurrencyTest.php`.

---

### Pitfall 3: Night audit date boundary uses wall-clock "today" instead of the hotel's business date

**What goes wrong:**
Night audit runs against `Carbon::today()` (server wall-clock) instead of "the day after the last closed business date." This causes: a business date getting skipped or double-closed if the audit is run late/early, transactions posted after midnight but before audit landing in the wrong day, or an audit that "closes" a date containing a guest who hasn't been checked out yet.

**Why it happens:** The system already stores all timestamps as UTC and has no global timezone assumption (per `CONCERNS.md`), which is correct for storage but wrong for the *business date* concept — night audit is fundamentally about a property-local calendar day, not a UTC timestamp. Naively reusing `now()` or `today()` silently uses server/UTC time, not hotel-local time, and doesn't track "last closed date" as its own piece of state.

**How to avoid:**
- Model business date as explicit persisted state (e.g., a `business_dates` or `audit_runs` table with `date`, `status`, `closed_at`), not derived from `now()`. The audit process is "close the day after the last closed one," never "close whatever today is."
- Define (even if hardcoded for one property, matching existing single-timezone assumption) a hotel-local timezone constant for date-boundary math, and reuse the project's already-solved `whereDate()` pattern (documented in `CONCERNS.md`) for any date-range query the audit performs — never raw string comparison on datetime columns.
- Build explicit blockers into the audit: unresolved arrivals (no-shows not marked), un-checked-out departures, and open folios/disputes should block or flag the close, not be silently skipped.
- Audit must be re-run-safe (running it twice for the same date should not double-post) — same idempotency discipline as folios.

**Warning signs:** Night audit code calling `Carbon::now()`/`today()` directly to determine "the date to close"; no persisted "last closed business date" row; a close endpoint that can be called for any arbitrary date rather than only the next unclosed one.

**Phase to address:** Night audit phase — should be sequenced *after* folio and room-status phases exist (it depends on both for its "checks and blockers").

---

### Pitfall 4: Housekeeping task duplication from multiple triggers

**What goes wrong:**
A housekeeping task gets created more than once for the same event — e.g., checkout fires a "clean room" task, and a separate manual trigger or a retried webhook/queue job fires another, leaving two open tasks for the same room and confusing the assignment board (double-counted workload, or a staff member marking one done while the other lingers "pending" forever).

**Why it happens:** This codebase already has a documented pattern for this exact class of bug — the operations queue merges `service_requests` and `tickets` from two tables in-memory (`CONCERNS.md`), and elsewhere actions are built to be idempotent on retry (`GenerateFolioAction`). Housekeeping tasks are new and will be tempting to spawn from several places (checkout action, manual staff action, possibly a scheduled job) without a shared dedupe rule.

**How to avoid:** Housekeeping tasks are a real table (per PROJECT.md decision) — enforce uniqueness at the source: one open task per `(room_id, task_type)` combination via a unique index or an application check inside a transaction before insert. Whichever action creates the task (checkout, manual "flag dirty", etc.) should check for an existing open task first, not blindly create.

**Warning signs:** No unique constraint on `(room_id, task_type, status)` for open tasks; task-creation logic duplicated across `CheckOutAction`/manual controller/any future scheduled job without a shared "ensure task exists" helper.

**Phase to address:** Housekeeping tasks phase.

---

### Pitfall 5: Support ticket escalation loops with no termination condition

**What goes wrong:**
Escalation logic ("if unresolved after N hours, escalate") re-fires every time a scheduled check runs, re-escalating an already-escalated ticket repeatedly, spamming staff and inflating history; or two staff both "claim" the same queue item because the claim isn't atomic, leading to double work or a race where the second claimer's write silently overwrites the first.

**Why it happens:** Escalation is a scheduled/polling concern (project explicitly defers websockets — "polling is acceptable"), and polling-driven state transitions are the classic source of duplicate side effects unless each transition checks "have I already done this" before acting. The claim action is a concurrent-write hotspot analogous to the folio settle race already fixed in P8.

**How to avoid:**
- Escalation only fires on a state transition (`status` changes from `open` to `escalated` exactly once), guarded by checking current status before acting, ideally inside `lockForUpdate()`.
- Ticket escalation is itself a `status` + history change (per the project's "status + history over boolean flags" rule), so query "which tickets need escalating" should exclude tickets already in `escalated` status, and the escalation action should be safe to run twice (idempotent).
- "Claim" is a single atomic update (`UPDATE ... WHERE assigned_to IS NULL` pattern, or `lockForUpdate` + check) so two staff can't both succeed in claiming the same item — the loser gets a clear "already claimed" domain exception, not a silent overwrite.

**Warning signs:** An escalation job that re-runs a full status query without excluding already-escalated tickets; a "claim" action that does a plain `find()` + `update()` without a lock or atomic conditional update.

**Phase to address:** Support tickets phase.

---

### Pitfall 6: "Digital key" for online check-in is treated as a real security boundary without being one

**What goes wrong:**
The guest-facing "digital key" (per PROJECT.md, tied to online check-in — arrival time + digital key, not a BLE/NFC lock integration in this milestone) is implemented as a predictable or long-lived code, displayed/stored in a way that lets anyone with the reservation UUID or a shared screenshot access it, or never expires/rotates after checkout — so a former guest (or anyone who saw their screen) retains a working credential.

**Why it happens:** Because there's no physical lock hardware in scope, it's easy to treat the "digital key" as just another guest-resource field rather than a credential, skipping the security discipline actual key/token issuance requires (short expiry, single active key per reservation, invalidation on checkout/cancellation, no plaintext exposure in logs or unauthenticated resources).

**How to avoid:**
- Generate the key server-side as a high-entropy token, never guessable from the reservation UUID or guest data.
- Bind it to the reservation's `GuestEntitlement` lifecycle (per the existing invariant in `CONCERNS.md`: identities resolve server-side, never trust client-supplied IDs) — the key is only valid while `EnsureIsCheckedIn`/active-stay entitlement holds, and is explicitly invalidated on checkout, cancellation, or reservation change.
- Never log the key value; never return it in any admin/staff-facing resource that isn't the guest's own.
- If this is a placeholder for future real lock integration, document that explicitly so a later milestone doesn't assume today's "digital key" already has lock-grade security.

**Warning signs:** Digital key value derived from reservation UUID, guest phone, or any client-visible ID; no expiry/invalidation on checkout; key value present in `activity_log` or a staff-facing resource.

**Phase to address:** Guest directory / online check-in / digital key phase.

---

### Pitfall 7: PII leakage through free-text guest notes and preferences

**What goes wrong:**
Guest notes (staff directory notes, preferences) become a dumping ground for sensitive personal data (health conditions, ID numbers, complaints referencing other guests) that then gets exposed too broadly — visible to every staff role via a shared "notes" endpoint, included in exports/reports, or retained indefinitely with no redaction path — creating a GDPR/PII exposure larger than the guest ever consented to.

**Why it happens:** Free-text fields are the easiest way to ship a "notes" feature fast, and this codebase already has one precedent for under-scoped sensitive data exposure (guest documents on the public disk, per `CONCERNS.md`) — the same "convenient but under-guarded" pattern is likely to repeat for notes/preferences unless explicitly designed against.

**How to avoid:**
- Gate guest notes behind a specific permission (e.g., `guests.notes.view`), not the general `guests.view` permission — not every staff role that can see a guest profile should see internal notes.
- Exclude notes from any bulk export/report resource by default; require an explicit, logged reason to include them.
- Do not let PII from notes appear in `activity_log` diffs verbatim if the model uses `LogsActivity` — consider redacting the notes field from logged attribute diffs.
- Apply the same authenticated-access discipline already flagged for `GuestDocument`: guest directory endpoints follow the server-side entitlement resolution pattern (staff sees only guests they have permission for; guest sees only their own record).

**Warning signs:** A `notes` text column with no dedicated permission gate; notes included in `GuestResource` for any staff role by default; notes visible in a CSV/report export without a separate flag.

**Phase to address:** Guest directory phase; reports phase (ensure notes are excluded from reports by default).

---

### Pitfall 8: N+1 queries in grids/boards (room board, availability/rate grid, reports dashboard)

**What goes wrong:**
The room board (all rooms × current status + current/next reservation), the 14-day availability/rate grid (rooms × 14 days), and the reports dashboard each naturally want per-cell data, which invites lazy-loading a relation per row/cell — turning a single page load into hundreds of queries that are fine in dev (few rooms, SQLite) but fall over in production (more rooms, MySQL, real network latency).

**Why it happens:** This project's convention layer already defends against this for standard CRUD (`$with` eager-load arrays on services, `whenLoaded()` in resources — see `CONVENTIONS.md`), but grid/board endpoints are not standard CRUD — they're grid/matrix data (room × date, room × status) that doesn't fit `BaseService`'s single-model eager-load pattern and is easy to build ad hoc with a loop that queries per cell.

**How to avoid:**
- Build board/grid data with a small number of set-based queries (e.g., one query for all rooms, one query for all reservations overlapping the 14-day window, one for current housekeeping status), then assemble the grid in PHP/array logic — never a query inside a loop over rooms or dates.
- Apply the same discipline the project already used to fix `AvailabilityService`'s date-boundary bug: date-range queries must be set-based and tested against SQLite and MySQL alike.
- Treat the reports dashboard the same way — pre-aggregate with `groupBy`/`selectRaw` at the DB level, not by loading models and counting in PHP.

**Warning signs:** A `foreach ($rooms as $room) { $room->reservations()->... }` pattern in a new board/grid service; a report endpoint that loads full model collections and reduces them in PHP instead of aggregating in SQL; page load time that scales linearly with room count in manual testing.

**Phase to address:** Front-desk room board / availability grid phase (sets the pattern); reports phase (reuses it).

---

### Pitfall 9: Permission sprawl across seven new operational domains

**What goes wrong:**
Each new domain (room board, housekeeping, folio disputes, tickets, guest directory, event deposits, reports) invents its own ad hoc permission names/shapes, producing a permission list that's inconsistent (`housekeeping.tasks.assign` vs `assign-ticket` vs `can_view_reports`), hard to reason about, and prone to accidentally granting more than intended when a role is assigned — compounded by the existing known gap that this RBAC has no deny/negative-permission semantics (`CONCERNS.md`), so an overly broad grant can't be locally revoked without a new role.

**Why it happens:** Seven domains means seven opportunities to freehand new permission strings without a shared naming convention, especially when each is planned/built somewhat independently across phases (and this milestone deliberately parallelizes work via `council-build`).

**How to avoid:**
- Fix a permission naming convention up front (e.g., `{domain}.{action}`: `folio.dispute.resolve`, `housekeeping.task.assign`, `tickets.escalate`, `reports.view` — the last one already exists per PROJECT.md, reuse it as the template) and apply it identically across every phase.
- Prefer coarser role-appropriate permissions over one-permission-per-button; the existing `StaffPolicy`/`spatie/permission` model already assumes reasonably-grained but role-shaped permissions — don't fragment further than the existing CMS/booking permissions did.
- Since deny semantics don't exist, avoid designing any feature that *requires* per-user exceptions to a role's permission (e.g., "everyone on Front Desk role except this one person can void charges") — flag it as needing the deferred `staff_permission_denials` table instead of working around it with a hack.

**Warning signs:** A new permission string that doesn't match `{domain}.{action}`; a role needing an exception carved out mid-phase.

**Phase to address:** Cross-cutting — call out in each phase's plan, verify at roadmap review before phases start.

---

### Pitfall 10: New endpoints subtly break the existing API contract

**What goes wrong:**
A new/adjacent endpoint reuses an existing route path with a different shape, changes an `error_code` value that a client already branches on, or adds a required field to an existing request — breaking the React dashboard or Flutter app without a compile-time signal (JSON contracts don't fail loudly).

**Why it happens:** Several new features sit adjacent to existing ones (folio disputes extend the existing folio; housekeeping links to existing room/reservation flow; service-request board extends existing `service_requests`) — the temptation is to "just add a field" to an existing resource/response rather than version or clearly separate the addition, especially under time pressure across many parallel phases.

**How to avoid:**
- Treat every existing `error_code` and field name as frozen; new capabilities are additive fields/new endpoints only (this is already a stated project constraint — enforce it as a phase-plan checklist item, not just a principle).
- Any breaking change requires the explicit sign-off + note to the Flutter/React teams called out in PROJECT.md — never assume "it's a small change."
- Add feature tests for existing endpoints' contract shape (existing 250 green tests are the regression net) before extending a shared resource — run the full suite, not just new tests, before every phase commit (already a stated constraint; the pitfall is skipping it under time pressure).

**Warning signs:** A diff that renames or repurposes an existing field/`error_code`; a shared `Resource` class modified for a new feature without a `whenLoaded()`/conditional guard so old consumers are unaffected; full test suite not run before commit.

**Phase to address:** Every phase — enforced via the mandatory full-suite-green-before-commit rule already in PROJECT.md; specifically flag folio (extends existing folio), housekeeping (touches room/reservation), and service-request board (extends existing `service_requests`) as highest-risk for contract drift.

---

### Pitfall 11: Event deposits repeat the folio money-handling mistakes in a second code path

**What goes wrong:**
Event inquiry deposits are built as a separate, parallel money-handling path (their own transaction/idempotency logic, or none) instead of reusing the folio/payment discipline — leading to the same double-charge/race class of bug as Pitfall 2, just in event inquiries instead of room folios.

**Why it happens:** Event deposits look like "just a field on the event inquiry" rather than "a payment," so they're less likely to get the transactional rigor already proven necessary for folios — but a deposit is money, with the same idempotency and locking requirements.

**How to avoid:** Reuse the same payment-recording action/pattern used for folio payments (even if it posts against a different owning entity) rather than writing bespoke deposit-handling logic. Same `DECIMAL`, same transaction + lock, same idempotency-on-retry requirement as Pitfall 2.

**Warning signs:** A deposit field/column added directly to the event inquiry table with ad hoc update logic instead of a dedicated payment record; no transaction wrapper around deposit recording.

**Phase to address:** Event inquiries checklist/deposit/notes phase.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|-----------------|------------------|
| Storing guest preferences/digital-key data on the same public disk pattern as CMS media (mirroring the P7 guest-document mistake) | Fast to ship, reuses `FileTrait` defaults | PII/security exposure identical to the already-flagged guest-document issue | Never — use a private disk from day one for anything guest-identity-adjacent |
| Ad hoc in-PHP aggregation for reports instead of DB-level `groupBy` | Faster to write against unfamiliar report requirements | Breaks at realistic data volumes, same class of issue as the hand-paginated operations queue | Only for a first throwaway spike, never merged |
| Skipping a unique constraint on housekeeping "one open task per room" and relying on UI to prevent duplicates | Simpler migration | Duplicate tasks whenever two triggers race (checkout + manual flag) | Never |
| Reusing `service_requests` without adding a price field for anything priced (e.g., paid housekeeping extras) | No migration needed right now | Folio aggregation silently excludes it (already true today per `CONCERNS.md`) — a "charge" that never bills | Acceptable only while all housekeeping/service items truly stay unpriced; add `amount_usd` (nullable) the moment any priced item appears |
| Building ticket escalation as a stateless polling job (`re-check everything each run`) | Simple job, no extra state | Duplicate escalations, log spam, race with staff claim actions | Only with the status-guard fix in Pitfall 5 already in place |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|-----------------|-------------------|
| Digital key ↔ existing `GuestEntitlement`/checked-in gate | Treating the key as a standalone field, not lifecycle-bound to the stay | Bind key validity to `EnsureIsCheckedIn`/entitlement state; invalidate on checkout/cancel, same server-side-resolution discipline as every other guest-facing feature |
| Night audit ↔ existing date-cast pattern (`whereDate()` fix in `AvailabilityService`) | Re-deriving date-range logic from scratch for audit checks, reintroducing the string-vs-date SQLite/MySQL mismatch | Reuse the same `whereDate()`/Carbon-cast pattern already proven correct across SQLite and MySQL |
| Housekeeping ↔ operations queue (existing merged `service_requests`+`tickets` queue) | Adding housekeeping tasks as a third hand-merged source, compounding the already-flagged in-memory merge/pagination bottleneck | Either keep housekeeping on its own dedicated board (per PROJECT.md's `/housekeeping` top-level path decision) or, if it must join the merged queue, fix the underlying query to a DB-level `UNION` first rather than adding a third in-memory merge |
| Reports dashboard ↔ folio/reservation data | Building report queries directly against transactional tables with no index/aggregation plan, discovered slow only after the phase ships | Design report queries with explicit indexes for the group-by/filter columns used; verify with realistic row counts, not the SQLite dev DB's near-empty tables |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|-----------------|
| In-PHP room board assembly with per-room queries | Room board load time grows linearly with room count | Set-based queries (all rooms, all overlapping reservations, all housekeeping statuses) assembled in-memory once | Noticeable above roughly a few dozen rooms; hard failure at hundreds |
| Hand-merging a third data source into the existing operations queue (per `CONCERNS.md`'s already-documented pattern) | Slow queue load as ticket/service_request/housekeeping-task counts grow | DB-level `UNION` query instead of in-memory merge-and-sort | Already documented as breaking past "several thousand rows combined" |
| Reports dashboard computing aggregates in PHP after loading full collections | Dashboard endpoint slow/times out as reservation/folio history grows | `selectRaw`/`groupBy` aggregation at the DB layer | Breaks well before production data volume if built the naive way — verify early, not after ship |
| 14-day availability/rate grid re-running `AvailabilityService::checkAvailability()` once per room per day in a loop | Grid endpoint issues N×14 queries | Single batched query for all rooms × the 14-day window, same discipline as Pitfall 8 | Breaks immediately with more than a handful of rooms |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Digital key value derivable from reservation UUID or guest-visible data | Anyone who intercepts a reservation link/UUID gets room access | High-entropy server-generated token, never derived from client-visible IDs; bind to entitlement lifecycle |
| Guest notes/preferences visible to any staff role that can view the guest | Excess PII exposure beyond least-privilege | Dedicated `guests.notes.view` permission separate from general guest-view |
| Folio dispute resolution and payment recording without a lock, mirroring the already-fixed P8 TOCTOU bug | Double-settlement, balance corruption, or a dispute "resolved" while a conflicting payment lands | `lockForUpdate()` + transaction on every folio-mutating action, not just `GenerateFolioAction` |
| Support ticket "claim" as a non-atomic read-then-write | Two staff both claim the same item; second write silently overwrites the first | Atomic conditional update or `lockForUpdate` on claim |
| Treating "digital key" feature as low-risk because there's no physical lock in this milestone | Under-invests in token security now, and a future lock-integration milestone inherits a weak credential design | Design the token/expiry/invalidation model as if it already gated a real lock |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-------------------|
| Room board shows housekeeping status as if it were booking availability (Pitfall 1 surfacing in the UI) | Front desk staff refuse to assign a bookable-but-dirty room, or double-book a "clean" room that's actually reserved | Show both states clearly and independently on the board; never let one drive the other implicitly |
| Folio dispute raised by guest with no visible status/timeline | Guest resubmits the same dispute repeatedly, or escalates to a support ticket unnecessarily | Explicit dispute status shown to the guest (`open`/`under_review`/`resolved`), consistent with the ticket status pattern already used elsewhere |
| Ticket escalation with no visible reason/history | Staff don't understand why a ticket suddenly jumped priority, erodes trust in the system | Log escalation as a status-history event (per the project's "status + history" rule) with a visible reason, not a silent flag flip |

## "Looks Done But Isn't" Checklist

- [ ] **Room status feature:** Often missing a real independent-of-availability data model — verify `AssignRoomAction`/`AvailabilityService` never reads housekeeping status as a gate unless explicitly designed to.
- [ ] **Folio line items/payments/disputes:** Often missing transaction + `lockForUpdate()` on the *new* endpoints (only `GenerateFolioAction` has it today) — verify every new folio mutation is wrapped and has a concurrency test.
- [ ] **Night audit:** Often missing persisted "last closed business date" state and idempotent re-run safety — verify it can't skip or double-close a date, and blocks on unresolved arrivals/departures/open disputes.
- [ ] **Housekeeping tasks:** Often missing a uniqueness guard against duplicate open tasks per room — verify a unique constraint or transactional check exists.
- [ ] **Support tickets escalation/claim:** Often missing atomicity on claim and a guard against re-escalating already-escalated tickets — verify both with a concurrency test.
- [ ] **Digital key:** Often missing token entropy, expiry, and invalidation-on-checkout — verify it can't be derived from client-visible IDs and is dead the moment the stay ends.
- [ ] **Guest notes/preferences:** Often missing a dedicated view permission and exclusion from exports/reports — verify `GuestResource` and any report/export path guard notes separately from general guest visibility.
- [ ] **Reports dashboard:** Often missing DB-level aggregation — verify no report query loads full collections into PHP to reduce/count.
- [ ] **Every new domain's permissions:** Often missing a consistent `{domain}.{action}` naming scheme — verify against the existing `reports.view` precedent before merging.
- [ ] **Every phase touching an existing resource (folio, service_requests, room/reservation):** Often missing a re-run of the full existing test suite, not just new tests — verify all 250+ existing tests stay green.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|----------------|------------------|
| Room status conflated with availability | MEDIUM | Split the fields/tables post hoc, backfill housekeeping status from any existing flag, add explicit tests that assignment logic ignores housekeeping status unless intentionally gated |
| Folio/deposit double-processing bug reaches production | HIGH | Reconcile affected folios/deposits manually, add the missing transaction+lock retroactively (same fix already applied once for P8), add a regression concurrency test before re-deploying |
| Night audit skips or double-closes a date | HIGH | Manual reconciliation of the affected business date's transactions/reports; add the persisted "last closed date" state and re-run guard before running audit again |
| Duplicate housekeeping tasks in production | LOW | Deduplicate existing open tasks by `(room_id, task_type)`, add the missing unique constraint, backfill history |
| Permission naming inconsistency discovered late | LOW–MEDIUM | Rename permissions in a migration + update seeders/role assignments; low cost if caught before multiple phases build on the inconsistent names, higher if roles are already assigned in production |
| Digital key security gap discovered post-ship | MEDIUM | Rotate/invalidate all issued keys, reissue with the corrected token scheme, audit access logs for misuse during the exposure window |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|-------------------|----------------|
| Room status vs availability conflation | Front-desk room board phase | Test that `AssignRoomAction`/availability queries are unaffected by housekeeping-status changes |
| Folio money-handling regressions | Folio line items/payments/disputes phase | Concurrency test per new mutation (payment, dispute) mirroring `ConcurrencyTest.php`/P8's settle-race test |
| Night audit date-boundary/timezone bugs | Night audit phase | Test audit against consecutive business dates, a skipped-run scenario, and a double-run-same-date scenario |
| Housekeeping task duplication | Housekeeping tasks phase | Test that triggering the same event twice (checkout + manual flag) yields one open task |
| Ticket escalation loops / claim races | Support tickets phase | Test escalation job run twice yields one escalation event; test concurrent claim yields exactly one winner |
| Digital key security | Guest directory/online check-in phase | Test key is high-entropy, invalid before checked-in, and invalid after checkout/cancel |
| PII exposure in guest notes | Guest directory phase; reports phase | Test that a role without `guests.notes.view` cannot see notes; test notes excluded from export/report payloads |
| N+1 in grids/boards | Front-desk room board phase (pattern-setting); reused in reports phase | Query-count assertion (e.g., `assertQueryCount` or DB query log) on board/grid/report endpoints |
| Permission sprawl | Cross-cutting, verified at roadmap/phase-plan review | Checklist review of every new permission string against the `{domain}.{action}` convention before merge |
| Breaking the existing API contract | Every phase | Full existing test suite green before every commit (already a stated project rule) plus explicit diff review of any shared `Resource`/`error_code` |
| Event deposit money-handling | Event inquiries checklist/deposit phase | Same concurrency + idempotency test pattern as folio payments |

## Sources

- This project's own documented incidents and invariants: `.planning/codebase/CONCERNS.md` (P8 TOCTOU double-settle fix, folio idempotency design, `whereDate()` SQLite/MySQL date bug, hand-paginated merged operations queue, no negative permissions, guest documents on public disk, `GuestEntitlement` server-side resolution invariant, service_requests lacking price data)
- This project's enforced conventions: `.planning/codebase/CONVENTIONS.md` (transactionality pattern, eager-loading pattern, status+history DB rule from `PROJECT.md`)
- [Night Audit - Introduction, RoomKeyPMS Support Centre](https://support.roomkeypms.com/a/429811-night-audit-introduction) — business-date-not-wall-clock framing
- [Hotel Night Audit: Process, Checklist & Best Practices, docmx.io](https://docmx.io/hotel-night-auditing/) — premature rollover / exception tracking failure modes
- [Unlocking Mobile App Vulnerabilities in Hotel Room Keys, NowSecure](https://www.nowsecure.com/blog/2019/10/23/unlocking-mobile-app-vulnerabilities-in-hotel-room-keys/) — mobile-key credential/token security concerns
- [How Secure Is Your Hotel's Mobile Room Key?, NerdWallet](https://www.nerdwallet.com/article/credit-cards/hotel-mobile-keys-safe-hackers) — replay/cloning risk framing applied here to token design, not hardware (out of scope this milestone)

---
*Pitfalls research for: Hotel PMS operational features on existing Laravel backend*
*Researched: 2026-09-25*
