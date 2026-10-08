---
phase: 10
reviewers: [claude]
reviewed_at: 2026-10-07T10:17:00Z
plans_reviewed: [10-16-PLAN.md, 10-17-PLAN.md]
---

# Cross-AI Plan Review — Phase 10

Scope: only the pending gap-closure plans 10-16 and 10-17 (LOY-23). Plans 10-01..10-15 are already executed.

Reviewer notes: OpenCode failed (internal DB error `no such column: name`), Qwen failed (`402` balance too low). The Claude CLI ran in a separate session at the user's request; it is the same model family as the orchestrator, so this review is less independent than a multi-vendor one.

## Claude Review


**Verdict:** Both plans close the gap and can run once the items marked "Fix before execution" are addressed. Overall risk is LOW-MEDIUM. The two biggest open items are an earn path the plans never check and a test scenario that may not be reachable.

---

## Plan 10-16: forfeit on deletion, re-forfeit after a reversal

### Summary
The plan is well designed. It routes every point move through `LoyaltyLedger::expire()`, so the existing `expire:batch:{id}` key makes the forfeit and the daily sweep idempotent against each other. It keeps a single writer for points, puts the forfeit after `assertDeletable` and before any scrub write, and runs it inside `withoutLogging` so there is still exactly one audit row. It also covers a path the verifier missed: a later cancel giving points or a voucher back to a deleted account. The test list is thorough: boundary, ordering, rollback, lock order and report effect. The main gap is that it closes only one of the paths that could put points back on a deleted account.

### Strengths
- It does not reuse `spendable()`, so expired-but-unswept batches are forfeited too. The plan explains this and tests it.
- The forfeit is the first statement in the `withoutLogging` closure. Three things follow from that placement:
  - a blocked deletion forfeits nothing;
  - lock order is guest → batches → vouchers, before any other write;
  - a failed forfeit happens before `pruneDocuments` or any file side effect.
- The rollback test swaps in a subclass of the action. It checks atomicity without patching the action's internals.
- Re-forfeit sits in `ReverseLoyaltyForReservationAction` behind `isDeleted()` on the already-locked guest. This handles the delete/cancel race correctly whichever transaction commits first.
- Deleted vouchers split by time: past expiry becomes `expired`, still valid becomes `void`. Both are existing enum cases, so no contract change.
- Scope discipline: no migration, route, lang key or error_code.

### Concerns
- **MEDIUM-HIGH: the earn path is not guarded.** The must_have says "a deleted account never ends a transaction with spendable points", but only the reversal path is closed. `EarnLoyaltyPointsAction` runs at three settle sites. The 9.1 guard blocks deletion only on an "open folio". If any folio state counts as not open but can still be settled, a deleted guest earns a fresh batch. Examples to check: a disputed folio, a folio reopened by staff, or a deposit-only folio. The plan neither checks the folio-status set nor adds a guard.
- **MEDIUM: the cancel-after-deletion fixture may be unreachable.** If a points booking creates its folio at booking time, a past confirmed reservation probably still has an open folio. The deletion guard then blocks the deletion and the test cannot set up its scenario. If so, the executor finds out mid-task and either bends the fixture with direct writes, which proves nothing, or drops the test. The plan should name the real-world state that reaches this path: no-show, unsettled-but-closed, or something else.
- **MEDIUM: the deadlock (FA-10.16-6) is acknowledged but left open.**
  - Deletion locks guest, then writes `reservations` (the phone null-out).
  - Cancel locks reservation → folio → guest.
  - A concurrent cancel of an old booking and a deletion of the same guest is a lock-order inversion. The forfeit makes the deletion transaction longer, which widens the window.
  - The race is rare, because the guard only allows non-live bookings. But it ends in a MySQL deadlock with no retry, so one of the two requests returns a 500. It is not on the human-verification list.
- **LOW: points history after a deleted guest's cancel looks odd.** Clawback runs before the re-forfeit and takes from the originating batch, then from other active batches FIFO. Those can include the fresh `refund` batch. Refunded points can therefore show up as `clawed_back` instead of `expired`. The balance still ends at zero, but the per-category report figures for that guest are hard to read. Nothing breaks.
- **LOW: the revive path is not tested explicitly.** Under Q3, a `depleted` batch that has not passed its expiry revives on refund. The re-forfeit then expires it under `expire:batch:{id}` for the first time, which is fine. Whether the cancel-after-deletion test hits this path or the "new refund batch" path depends on the fixture.
- **LOW: the staff ledger does not say why points ended.** Forfeit entries are plain `expire` rows with no reason and no distinct key. Finance cannot split expiry breakage from deletion forfeits except by joining `guests.account_status` and timestamps.
- **LOW: the "additive only" check on `LoyaltyLedger.php` is fragile.** It counts removed lines in the diff. If Pint reformats any existing line, the acceptance check fails even though the logic is untouched.
- **LOW: the window between the two plans.** Between the 10-16 and 10-17 commits, staff adjust can still award points to a deleted account. This is harmless as long as nothing ships between the two waves.

### Suggestions
1. **Fix before execution:** guard `EarnLoyaltyPointsAction` the same way it already treats `cancelled`: skip when the guest `isDeleted()` and log `loyalty.earn_skipped_deleted` (counts only). Add one test: settle a folio of a deleted guest and assert no batch is created. Alternatively, prove with a test that no folio can be settled after deletion, and cite that test.
2. **Fix before execution:** in Task 1, name the reservation and folio state the cancel-after-deletion test uses. Have the QA step confirm the deletion guard lets it through before writing the assertions.
3. Add "concurrent cancel of a past booking and account deletion for one guest" to the MySQL human-verification list. Or move the 9.1 reservation phone null-out into a `lockForUpdate` that runs before the guest lock.
4. Make the cancel-after-deletion test cover both refund outcomes: a revived depleted batch and a new `refund` batch.
5. Change the "additive" check to a test or grep on `function expire` and `function spendable`, so Pint reformatting cannot fail it.

---

## Plan 10-17: guards for the warning job, staff adjust and the report, plus docs

### Summary
The plan is small and well bounded. It reuses `GuestAccountDeletedException` and its existing lang keys, puts the adjust check under the guest lock and before the replay lookup, and keeps the report at its pinned three queries. It also re-checks inside `warn()` to cover the gap between listing guests and locking one. The docs are additive and say there is no contract change.

### Strengths
- Running the adjust check before `IdempotentWrite` is the right call. Replaying an old success would misreport a forfeited balance (FA-10.17-1).
- The query-budget test is extended with residue data, so the filter is proven to stay one statement.
- Award, deduction, Arabic locale, replay and a direct action call are all tested. The existing 401 and 403 tests stay untouched.
- The protected files (Postman, tree, handoff, `MOBILE_API_DESIGN`) are checked as byte-unmodified.

### Concerns
- **MEDIUM: an unverified behaviour goes into the Flutter docs.** "Signing in again creates a new account with zero points" is a 9.1 behaviour (the phone is scrubbed, so an OTP login creates a new guest). The plan writes it as fact without a read_first or check against `09.1-CONTEXT.md`. If it is wrong, Flutter builds the wrong UX copy.
- **LOW: the report figures do not reconcile for residue.** `outstanding_points` leaves residue out. When the sweep later expires that residue, `expired_points` counts it, and `issued` always included it. So issued − redeemed − expired ≠ outstanding.
- **LOW: whether residue exists at all is not checked.** If 9.1 has never reached production (the owner pushes manually), no residue exists. In that case the report filter and FA-10.17-3 guard against an empty set, while a one-off forfeit would remove the question entirely.
- **LOW: the RED step is weak.** The verify step only checks that the run exits non-zero. It does not prove the old tests still pass, which the action text claims. A new test that breaks shared setup would still count as RED.
- **LOW: staff visibility of a deleted guest's loyalty page is not addressed.** It is unclear whether `GET /cms/loyalty/guests/{uuid}` should follow the 9.1 `DeletedGuestVisibilityTest` rules (404 or redacted). The plan does not say either way.
- **LOW: `void` is not documented for the dashboard.** If the dashboard has a staff voucher view, React needs a label for `void`. 10-17 documents it only in the mobile guide.

### Suggestions
1. **Fix before execution:** add `09.1-CONTEXT.md` (sign-in after deletion) to Task 3's read_first, and require the docs sentence to quote the verified behaviour.
2. Check whether 9.1 is deployed anywhere with deleted accounts. If not, record "residue = 0 at ship" in FA-10.17-3. If so, add an idempotent `loyalty:forfeit-deleted` command (about 20 lines) that loops deleted guests through `ForfeitLoyaltyBalanceAction`. That removes residue outright instead of relying on filters.
3. In the dashboard guide, note that `outstanding_points` and `issued − redeemed − expired` can differ for pre-forfeit residue.
4. Strengthen Task 1's verify step: run the pre-existing methods separately, for example by filtering on method names, and require them to pass.
5. Add one line to the dashboard handoff about the voucher `void` status label.

---

## Cross-plan risk assessment

| Area | Assessment |
|---|---|
| Goal coverage | All four gaps in VERIFICATION.md are covered: forfeit, PII-free audit, warning/adjust guards, report. One extra regain path (earn) is not covered. |
| Dependency order | Correct (10-16 then 10-17). 10-17's code does not depend on 10-16's except one regression test, so the waves are stricter than they need to be, which is harmless. |
| Scope creep | None. The re-forfeit in the reversal is justified, not gold-plating. |
| Security/privacy | Good: counts-only audit, no notifications to erased accounts, staff writes refused. The earn gap is the only open privilege-type hole. |
| Performance | Forfeit cost grows with the guest's active batches (one `FOR UPDATE` each). Fine for realistic volumes. The report adds a NOT EXISTS subquery but no extra statement. |
| Concurrency | Lock order is documented and asserted. The 9.1 deletion still writes reservations after locking the guest, the reverse of cancel's order, and nobody is assigned to fix it. |

**Overall risk: LOW-MEDIUM.** The design follows the phase's own rules (single writer, idempotency keys, lock order) and is heavily tested. Before execution, settle three things: the earn-path guard, how the cancel-after-deletion fixture is reached, and a check of the 9.1 sign-in-after-deletion claim before it goes into the docs. The deadlock should go on the MySQL human-verification list even if it is not fixed now.

---

## Consensus Summary

Single reviewer, so there is no cross-reviewer agreement; these are the reviewer's own priorities.

### Agreed Strengths
- Every point move goes through `LoyaltyLedger::expire()` with the existing `expire:batch:{id}` key, so the forfeit and the daily sweep are idempotent against each other.
- The forfeit runs first inside the deletion transaction: a blocked deletion forfeits nothing, and lock order is fixed (guest, then batches, then vouchers).
- The re-forfeit after a cancel refund closes a path the verifier missed.
- The adjust guard runs before the idempotency replay, so an old success cannot be replayed onto a forfeited balance.
- No contract change: no migration, route, lang key or error_code.

### Agreed Concerns
1. MEDIUM-HIGH (10-16): `EarnLoyaltyPointsAction` has no guard for deleted accounts. If a folio can be settled after deletion, the guest earns a new batch.
2. MEDIUM (10-16): the cancel-after-deletion test fixture may be unreachable, because the deletion guard may block it.
3. MEDIUM (10-16): a cancel and a deletion for the same guest take locks in opposite orders, which can deadlock (FA-10.16-6). It is not on the MySQL human-verification list.
4. MEDIUM (10-17): the docs state "signing in again creates a new account with zero points" without checking `09.1-CONTEXT.md`.

### Divergent Views
None (one reviewer).
