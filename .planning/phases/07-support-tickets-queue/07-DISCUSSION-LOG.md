# Phase 7: Support Tickets & Queue - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered, the council's positions, and the dissent.

**Date:** 2026-09-27
**Phase:** 07-support-tickets-queue
**Mode:** fully automatic (owner instruction, carried over from Phase 6 and standing memory: never ask the owner mid-milestone; route every gate decision to the Fable consultant/council and log the outcome)

## How the decisions were made

1. A Fable 5.1 consultant agent inspected the codebase read-only (ticket schema, queue actions, permission seeder, staff endpoints — see "Ground truth found in code" in CONTEXT.md) and produced a full decision set (D-01 through D-27) plus roadmap wording fixes, Claude's Discretion, and a Deferred list — all captured verbatim in CONTEXT.md.
2. The consultant flagged eight decisions `convene: true` (high-stakes or reversibility concerns): **D-01, D-02, D-07, D-09, D-10, D-15, D-22, D-23.**
3. One ai-council ran over those eight (motion: adopt the consultant's Phase 7 decision set). It returned ten binding amendments (A1–A10), which are folded into the affected decisions in CONTEXT.md and marked `(council A#)`.
4. Per the standing project instruction, no decision in this list was routed to the human owner. The consultant and council are the decision authority for this phase; the owner receives the finished record (this log + CONTEXT.md) after the fact.

## The council

**Motion:** adopt the Fable consultant's Phase 7 decisions as written in `07-consultant-decisions.md`, with particular scrutiny on the eight flagged items.
**Members and positions:**

| Member | Confidence | Position |
|---|---|---|
| Architect | 78 | Adopt. Added independent points (folded as part of A8): `ticket_actions.meta` should be whitelisted per `TicketActionType` rather than left as a free-form JSON bag; the timeline table, not the `tickets` columns, should be canonical for history reads; `TicketRecovery.amount_usd` should carry a code comment that it is a snapshot of a frozen folio item; queue claim should be structured as per-type adapters behind a `ClaimGuard` rather than one large conditional. |
| Skeptic / Red Team | 72 | Adopt with reservations. Raised: (a) a deactivated user might keep a live bearer token, undermining D-22's assumption that eligibility-at-claim-time is sufficient — **resolved by the Chair, see below**; (b) wanted per-request `EnsureUserIsActive` middleware as defense in depth — **rejected**, see Dissent; (c) wanted the single `recovery_total_usd` field split so a ledger-backed total isn't confused with a merely-recorded one — **adopted in modified form as A7**. |
| Risk & Security | 78 | Adopt. Flagged that `RecordsRowLocks`-style tests only prove the SQL contains `FOR UPDATE`; on SQLite (used in the test suite) `lockForUpdate()` is a documented no-op, so claim-race serialization is **only actually verified against MySQL**, not by the test suite itself — folded as **A9**, a caveat to record in the guide (same posture as a Phase 5 precedent). |

**Chair:** orchestrator. **Council confidence: 76.** No member voted to reject any decision in the set.

### Fact resolved by the Chair

The Skeptic's claim (b above) rested on an assumption: that deactivating a staff user does not immediately invalidate their existing bearer token, meaning D-22's "the claimer implicitly passes eligibility because the work permission was checked" could be defeated by a token issued before deactivation.

The Chair checked this against the actual code rather than debating it further:
- `app/Services/StaffService.php:54` — deactivation deletes **all** of the user's tokens.
- `app/Services/AuthStaffService.php:21` — login itself checks `is_active`.

**Conclusion: D-22's premise holds.** There is no live-token gap. No per-request `EnsureUserIsActive` middleware is needed (the Skeptic's ask in (b) is rejected on this basis — see Dissent). In its place, the council required a regression test: deactivating a user revokes their tokens, and a deactivated user's *old* bearer token gets 401 on the claim route. This is folded into D-27 in CONTEXT.md as `DeactivatedTokenClaimTest`.

## Amendments (binding, adopted into CONTEXT.md)

| # | Affects | Summary |
|---|---|---|
| A1 | D-22 (crux) | Drop the new `queue_item_closed` code. Claim delegates to each type's writer under its lock; the writer's existing closed code passes through (`ticket_closed`, `housekeeping_task_closed`, …). Document codes per queue type in the API guide. The service-request arm gains `lockForUpdate()` and a closed check (reuse an existing SR closed/transition exception if one exists, else add `service_request_closed` — additive, 5 locales). |
| A2 | D-07 | The ticket arm of `UpdateRequestStatusAction`/assign requires a non-null `$actor`; throws a `LogicException` if absent. Legacy null-actor callers stay valid for non-ticket arms only. |
| A3 | D-09 | The handoff must list, per queue type, which existing roles become un-assignable — a concrete table, not a generic "additive 422". |
| A4 | D-10 | Keep `tickets.*` as the guest-relations permission set. Document the full blast radius: reception/concierge gain read of `/cms/conversations` (guest chat PII) and `/cms/event-inquiries` (RFP leads, contact PII, budgets); concierge with `tickets.assign` can re-status/assign event inquiries. Pin these in `RolePresetsTest`. Add a PROJECT.md debt entry: split into `support_tickets.*` if the owner objects. |
| A5 | D-23 | `GET /operations/staff` — the `permission` filter accepts only the queue registry work permissions (422 otherwise). Response returns `departments[]` (array, tested as array) and `type`; drop `roles[]`. Document that `departments` derives from role names (a role rename is a silent filter/response change). |
| A6 | D-03/D-01 (schema) | `Ticket` uses `logExcept(['description'])` so complaint text stays out of `activity_log` diffs. `TicketAction` stays outside the activity log. Actor FKs on `ticket_actions` (`user_id`, `target_user_id`) use `restrictOnDelete()` — **this reverses the consultant's original `nullOnDelete()` choice on those same two columns.** |
| A7 | D-15 | Reason enum gains `no_stay` — 422 `ticket_recovery_folio_invalid {reason: no_stay}` when a `folio_credit` recovery is recorded on a ticket with neither reservation nor guest. Ticket resource exposes `folio_credit_total_usd` (ledger-backed, sum of `abs(credit)`) and `recorded_value_usd` (all recoveries) — **replacing** the consultant's single `recovery_total_usd` field from D-13. Phase 9 note: only `type=folio_credit` is ledger-backed. Tests: `abs()` on the negative credit row; no delete route exists for tickets or recoveries. |
| A8 | Architect (general) | `ticket_actions.meta` keys whitelisted per `TicketActionType`; the timeline (`ticket_actions`) is canonical over ticket columns for history; comment on `TicketRecovery` that `amount_usd` is a snapshot of a frozen folio item; queue claim implemented as per-type adapters behind a `ClaimGuard`. |
| A9 | Risk | SQLite `lockForUpdate` is a no-op; claim-race serialization is MySQL-only verified (`RecordsRowLocks` proves the SQL only). Record as a caveat like Phase 5. |
| A10 | Gate | Phase 7 context/discussion docs may be written now. Research/planning start only after Phase 6 is committed with its SUMMARY and the full suite green. |

## Dissent (recorded, not adopted)

- **Skeptic:** wanted a per-request `EnsureUserIsActive` middleware on `auth:users`. **Rejected** — deactivation already revokes all tokens (Chair's fact resolution above); the gap the Skeptic worried about does not exist. Covered instead by the `DeactivatedTokenClaimTest` regression test (D-27).
- **Skeptic:** wanted `recovery_total_usd` split into a ledger-backed figure and an all-recoveries figure. **Adopted, in modified form, as A7** (`folio_credit_total_usd` + `recorded_value_usd`) rather than the Skeptic's original naming.

No dissent was overridden without either a factual resolution (the token question) or a modified adoption (the recovery split). No member's position was rejected outright without a stated reason.

## Contradiction found between the consultant file and the council synthesis (flagged for the caller)

Two places where the two source documents disagree, beyond ordinary "amendment supersedes original text":

1. **`queue_item_closed` (D-22 / D-25 vs. A1).** The consultant's own decision text (D-22) *and* its own error-code table (D-25) both define `queue_item_closed` as a real 422 code with context `{type, status}` — it is not a stray mention, it is stated twice as settled. Council amendment A1 removes this code outright and replaces it with per-type existing codes. Per the task's own precedence rule ("where an amendment overrides the consultant text, the amendment wins"), A1 is authoritative in CONTEXT.md and `queue_item_closed` does not appear as a live code anywhere in the Phase 7 contract. This is a direct contradiction, not a refinement — it is called out at both D-22 and D-25 in CONTEXT.md so a future reader doesn't reconstruct the dropped code from the consultant file alone.

2. **`/cms/event-inquiries` permission gate (A4) is not corroborated by the consultant's own ground truth.** The consultant's code-inspection summary states only that `tickets.view|respond` gates the staff chat inbox (`/cms/conversations`) — it does not mention `/cms/event-inquiries` anywhere, including in the "Ground truth found in code" section that lists exactly what was inspected. Council amendment A4 asserts that `/cms/event-inquiries` is *also* gated by `tickets.*`, and builds a specific claim on top of it (concierge with `tickets.assign` can re-status/assign event inquiries). This isn't a case of the amendment overriding a stated fact — it's a claim with no corroborating ground-truth entry in the consultant file at all. A4 is still adopted as binding per the precedence rule, but CONTEXT.md flags it explicitly (under D-10) as needing verification against the actual route middleware during Phase 7 research, before it is written into the SUMMARY or the API guide as settled fact. **Resolved (Chair, 2026-09-27):** `backend/routes/api.php:518-526` confirms `/cms/event-inquiries` index/show use `permission:tickets.view` and status/assign use `permission:tickets.assign`. A4's blast-radius claim is correct.

---

*Phase: 07-support-tickets-queue*
*Discussion logged: 2026-09-27*
