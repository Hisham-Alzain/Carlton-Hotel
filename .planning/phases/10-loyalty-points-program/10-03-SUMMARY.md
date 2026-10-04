---
phase: 10-loyalty-points-program
plan: 03
subsystem: api
tags: [laravel, loyalty, ledger, fifo, clawback, refund, expiry, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-01 batch/ledger/allocation models, enums, BuildsLoyaltyFixtures; 10-02 LoyaltyProgram::expiresAtFrom and LoyaltyInsufficientPointsException"
provides:
  - "App\\Support\\LoyaltyLedger: available, balances, credit, consume, record, refund (Q3), clawback (Q2), expire"
  - "Ledger key shapes refund:{redeem_entry_id}, clawback:{earn_entry_id}, expire:batch:{batch_id}"
  - "33 unit tests proving FIFO order, the expiry boundary, both reversal policies and idempotency"
affects: [10-04 through 10-15, loyalty earn, redeem, booking apply, cancellation reversal, expiry command, account endpoint, reports]

tech-stack:
  added: []
  patterns:
    - "Single final support class owns every point movement; callers hold the guest lock, the class locks batches (guest -> batches, M-6)"
    - "Availability is a query filter (status active AND expires_at > now()), never the expiry sweep"
    - "Reversals are idempotent by lookup (reverses_entry_id) with the unique index as backstop; a clawback of spent points writes a zero entry plus shortfall_points instead of failing"

key-files:
  created:
    - backend/app/Support/LoyaltyLedger.php
    - backend/tests/Unit/Loyalty/LoyaltyLedgerFifoTest.php
    - backend/tests/Unit/Loyalty/LoyaltyLedgerReversalTest.php
  modified: []

key-decisions:
  - "Mutating methods (consume, record, refund, clawback, expire) each open their own nested DB::transaction as a safety net; the caller's guest lock and outer transaction remain the real contract (documented in the class docblock)"
  - "An active-or-depleted clawback origin is reversed and drawn on even when it is past expiry but unswept, so those points leave the ledger once (clawback) rather than twice (clawback plus sweep); an expired or reversed origin is left untouched and only other unexpired batches are drawn on (FA-10.03-2)"
  - "refund() accepts only a redeem entry and clawback() only an earn entry (LogicException otherwise), so adjust/expire entries can never be reversed through these paths"
  - "clawback entries carry batch_id null (allocations name the batches); expire entries carry batch_id of the expired batch; refund entries carry the new refund batch id or null"
  - "credit() whitelists folio_id, awarded_by and reason only; status, points_remaining and earned_at cannot be injected"

patterns-established:
  - "Tests call the ledger exactly as real callers will: DB::transaction after locking the guest row, then lockedSelects() to assert a for update on loyalty_earn_batches"
  - "Boundary tests use travelTo with whole-second instants (-1s, 0, +1s) because timestamp columns store seconds"

requirements-completed: []

coverage:
  - id: D1
    description: "Availability and balances: SUM over active unexpired batches, gone at expires_at exactly, balances in one query"
    requirement: "LOY-08"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/LoyaltyLedgerFifoTest.php::test_a_batch_is_gone_at_its_expiry_instant_even_while_its_status_is_still_active, test_balances_returns_available_expiring_soon_and_next_expiry_in_one_query, test_other_guests_and_inactive_batches_are_never_counted_or_touched"
        status: pass
    human_judgment: false
  - id: D2
    description: "FIFO consume by expires_at then id, insufficient points 422 context, batch row locks"
    requirement: "LOY-08"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/LoyaltyLedgerFifoTest.php::test_consume_drains_the_earliest_expiry_first_even_when_created_out_of_order, test_equal_expiry_is_consumed_in_id_order, test_insufficient_points_throws_with_context_and_changes_nothing, test_consume_locks_the_guests_batches_for_update"
        status: pass
    human_judgment: false
  - id: D3
    description: "Refund per Q3 (restore into unexpired active/depleted batches, new full-term refund batch otherwise), idempotent"
    requirement: "LOY-13"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/LoyaltyLedgerReversalTest.php::test_refund_* (6 tests) and test_the_unique_reverses_entry_id_index_is_the_backstop"
        status: pass
    human_judgment: false
  - id: D4
    description: "Clawback per Q2 (origin then FIFO, floored at zero, shortfall_points, never negative), idempotent; expire writes one entry and is a no-op on a stale batch"
    requirement: "LOY-17"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/LoyaltyLedgerReversalTest.php::test_clawback_* (6 tests), test_expire_* (2 tests), test_refund_clawback_and_expire_lock_batch_rows_for_update"
        status: pass
    human_judgment: false

duration: 35min
completed: 2026-10-04
status: complete
---

# Phase 10 Plan 03: LoyaltyLedger Summary

**`App\Support\LoyaltyLedger` is now the only code that moves loyalty points: FIFO consume over unexpired batches, Q3 refund, Q2 clawback floored at zero with `shortfall_points`, and idempotent expiry, all proven by 33 unit tests before any caller exists.**

## Performance

- **Duration:** about 35 min
- **Completed:** 2026-10-04
- **Tasks:** 2 (RED specs, GREEN implementation)
- **Files created:** 3

## Accomplishments

- Availability and `balances()` always filter `status = active AND expires_at > now()` themselves; a batch is gone at its `expires_at` instant even while the sweep has not run (tested at -1s, 0 and +1s). `balances()` returns available, expiring soon and next expiry in one query.
- `consume()` locks the guest's active unexpired batches `FOR UPDATE`, orders `expires_at, id`, pre-checks the sum (`LoyaltyInsufficientPointsException` with `available_points` and `requested_points`), drains FIFO and returns `[{batch_id, points}]`.
- `refund()` implements Q3: unexpired active/depleted batches take points back and become active, the rest becomes one new `refund` batch with a full term from `LoyaltyProgram::expiresAtFrom(now)`; expired and reversed batches never revive.
- `clawback()` implements Q2: origin first, then other active unexpired batches FIFO, never below zero, one entry with `points = -taken` and `shortfall_points`; spent points never block or throw.
- `expire()` re-reads under lock and writes nothing for a stale, swept, depleted or empty batch.
- `record()` rejects allocations whose sum differs from `abs(points)` before writing anything.

## Task Commits

1. **Task 1 + Task 2 (one commit per project policy, full suite green first):** `30a2439` feat(10-03): LoyaltyLedger with FIFO consume, refund, clawback and expire

RED was confirmed first (33 errors, "Target class App\Support\LoyaltyLedger does not exist"), then GREEN; no red state was committed.

**Plan metadata:** committed separately as `docs(10-03): complete loyalty ledger plan`.

## Verification

- `php artisan test --filter='LoyaltyLedger|LoyaltySchemaTest|LoyaltyProgramTest'`: 90 tests, 499 assertions, pass.
- Full suite `php artisan test`: **2716 tests, 16463 assertions, exit 0** (343 s).
- Grep gates: 8 `lockForUpdate` / FIFO `orderBy('expires_at')` matches; no `->update([` or `DB::table(` in the class.
- Pint clean on the three files.

## Decisions Made

See `key-decisions` above. The notable one: the clawback origin is drawn on whenever it is active or depleted (expiry-unswept included) so the same points are never removed twice.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test passed a mutable Carbon to `credit()`**
- **Found during:** Task 2 (first GREEN run)
- **Issue:** `test_credit_and_consume_reject_non_positive_points` passed `now()->addDay()` (Illuminate Carbon) to `credit()`, whose signature per the plan takes `CarbonImmutable`
- **Fix:** the test now passes `CarbonImmutable::now()->addDay()`
- **Files modified:** `backend/tests/Unit/Loyalty/LoyaltyLedgerFifoTest.php`
- **Commit:** 30a2439

**2. [Rule 1 - Bug] Test fixture ordering in the unswept-other-batch clawback test**
- **Found during:** Task 1 (before first run)
- **Issue:** creating the 1-day batch before the 500-point redeem would make FIFO spend it first, defeating the scenario
- **Fix:** redeem first, then create the soon-to-expire batch
- **Commit:** 30a2439

Beyond the plan's specification, additional tests were added (equal-expiry, exact-balance, expired-unswept skip in consume and clawback, non-positive guards, reservation/voucher copy, folio/reservation copy on clawback, locks on refund/clawback/expire).

**Total deviations:** 2 trivial test fixes, no scope change.

## Issues Encountered

None. The other session's Phase 9.1 work was committed (`1dac2b9`) before the full suite ran, so no foreign failures occurred.

## Known Stubs

None.

## Threat Flags

None. The class adds no endpoint or trust boundary; T-10-10 to T-10-13 are mitigated and tested (locks asserted, `expires_at > now()` on every availability query, idempotent reversals, zero-floor clawback).

## Requirements

`requirements-completed` is empty on purpose. This plan delivers the ledger primitives behind LOY-08 (expiry gate), LOY-13 (refund on cancel) and LOY-17 (idempotent reversal), but their user-visible behaviour (expiry command, cancellation actions, endpoints) lands in later Phase 10 plans, so none is ticked here.

## Next Phase Readiness

10-04 onward can call `LoyaltyLedger` inside `DB::transaction` after locking the guest row. Callers record earn entries themselves via `credit()` plus `record()` (earn idempotency keys are the caller's), and must pass redeem/earn entries of the matching type to `refund()`/`clawback()`.

## Self-Check: PASSED

- backend/app/Support/LoyaltyLedger.php, LoyaltyLedgerFifoTest.php, LoyaltyLedgerReversalTest.php exist
- commit 30a2439 exists
