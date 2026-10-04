---
phase: 10-loyalty-points-program
plan: 05
subsystem: api
tags: [laravel, loyalty, earn, folio, settlement, idempotency, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-02 LoyaltyProgram (earningEnabled/earnRate/expiresAtFrom) and LoyaltyMath::pointsForSpend; 10-03 LoyaltyLedger::credit/record; 10-01 batch/ledger models and BuildsLoyaltyFixtures"
provides:
  - "App\\Actions\\Loyalty\\EarnLoyaltyPointsAction::handle(Folio $lockedFolio): array - once-only, half-up earn per stay/service bucket"
  - "SettleFolioAction (2 sites) and RecordFolioPaymentAction (auto-settle, 1 site) call it inline under the folio lock"
  - "Ledger key shape earn:folio:{folio_id}:{stay|service}; activity log loyalty.earn_skipped_cancelled on the folio"
  - "25 tests (14 feature, 11 unit) proving the three sites, bucketing, no-ops, once-only, rollback and lock behaviour"
affects: [10-06 through 10-15, loyalty cancellation clawback (10-12), account/ledger endpoints, reports, adjust/expire]

tech-stack:
  added: []
  patterns:
    - "Earn is inline-atomic: called right after the SETTLED update inside the folio-locked transaction, never from a listener or after commit"
    - "Per-bucket savepoint; the only catch is UniqueConstraintViolationException (= already earned); any other error rolls the whole settlement back"
    - "Insert-only under the caller's folio lock: no guest or batch lock is taken (M-6)"

key-files:
  created:
    - backend/app/Actions/Loyalty/EarnLoyaltyPointsAction.php
    - backend/tests/Feature/Loyalty/LoyaltyEarnOnSettleTest.php
    - backend/tests/Unit/Loyalty/EarnLoyaltyPointsActionTest.php
  modified:
    - backend/app/Actions/Folio/SettleFolioAction.php
    - backend/app/Actions/Folio/RecordFolioPaymentAction.php

key-decisions:
  - "Buckets are computed from folio_items in two passes: non-credit lines first (reservation = stay, everything else = service), then credits resolved against that map by reverses_item_id (standalone credit = service); each bucket is clamped at 0.00 before points = halfUp(spend x rate)"
  - "With earning off (no row, null or 0 rate) the action writes nothing and logs nothing (FA-10.05-1); a cancelled reservation logs only loyalty.earn_skipped_cancelled on the folio (Q9)"
  - "The rollback test injects the failure with a LoyaltyEarnBatch::creating listener because LoyaltyLedger is final and cannot be doubled; the exception still originates inside the real LoyaltyLedger::credit() call"

patterns-established:
  - "Payment-replay tests reuse one staff token: IdempotentWrite treats the same key from another desk (recorded_by) as a 409 conflict"
  - "Settle tests add service-side folio lines with FolioItem::factory() then Folio::recalculateTotals() so the balance due includes them"

requirements-completed: [LOY-03]

coverage:
  - id: D1
    description: "Earn at all three settlement statements: nothing-due settle, cash settle, auto-settling payment; partial payment earns nothing"
    requirement: "LOY-03"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyEarnOnSettleTest.php::test_settling_a_prepaid_folio_earns_half_up_points_into_a_stay_batch, test_settling_with_cash_earns_a_stay_batch_and_a_service_batch, test_the_payment_that_auto_settles_earns_and_a_partial_payment_does_not"
        status: pass
    human_judgment: false
  - id: D2
    description: "Bucketing: reservation = stay; service_booking/service_request/manual = service; credits follow the reversed line, standalone credit reduces service, buckets clamp at 0, half-up on the summed bucket"
    requirement: "LOY-03"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/EarnLoyaltyPointsActionTest.php::test_service_booking_service_request_and_manual_lines_share_the_service_bucket_half_up, test_a_credit_reduces_the_bucket_of_the_line_it_reverses_and_rounds_after_summing, test_a_credit_reversing_the_reservation_line_reduces_the_stay_bucket, test_a_standalone_credit_reduces_the_service_bucket, test_a_bucket_driven_below_zero_earns_nothing_and_never_goes_negative"
        status: pass
    human_judgment: false
  - id: D3
    description: "Once only: second settle is 422 folio_settled, payment replay earns nothing, a pre-existing earn key is skipped without error"
    requirement: "LOY-04"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyEarnOnSettleTest.php::test_a_second_settle_answers_folio_settled_and_earns_nothing_more, test_replaying_the_settling_payment_with_the_same_key_earns_nothing_new"
        status: pass
      - kind: unit
        ref: "tests/Unit/Loyalty/EarnLoyaltyPointsActionTest.php::test_an_existing_earn_key_skips_that_bucket_without_error_and_leaves_one_entry"
        status: pass
    human_judgment: false
  - id: D4
    description: "No-ops: no settings row, zero or null rate, guestless reservation write nothing; cancelled reservation writes only the activity-log entry"
    requirement: "LOY-02"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyEarnOnSettleTest.php::test_settling_with_no_program_row_writes_no_loyalty_rows, test_settling_with_a_zero_earn_rate_writes_no_loyalty_rows, test_a_cancelled_reservation_settles_without_earning_and_logs_the_skip, test_a_reservation_without_a_guest_settles_without_earning"
        status: pass
    human_judgment: false
  - id: D5
    description: "Atomicity and locks: a non-unique failure in earn answers 500 and leaves the folio open with no payment or ledger row; a settle locks folios and takes no guests/loyalty_earn_batches lock"
    requirement: "LOY-04"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyEarnOnSettleTest.php::test_a_failure_while_earning_rolls_the_whole_settlement_back, test_a_settle_locks_the_folio_and_takes_no_guest_or_batch_lock"
        status: pass
    human_judgment: false

duration: 40min
completed: 2026-10-04
status: complete
---

# Phase 10 Plan 05: Earn on Settlement Summary

**`EarnLoyaltyPointsAction` credits half-up integer points per stay/service bucket exactly once, inline inside the folio-locked transaction of all three settlement statements, and is a silent no-op for unconfigured programs, guestless reservations and cancelled stays.**

## Performance

- **Duration:** about 40 min
- **Completed:** 2026-10-04
- **Tasks:** 2 (RED specs, GREEN implementation)
- **Files created:** 3, **modified:** 2

## Accomplishments

- One new action. After the status flips to settled it reads the folio lines, buckets them (reservation lines = `stay`; service_booking, service_request, manual = `service`; a credit follows the line it reverses, a standalone credit reduces `service`), clamps each bucket at 0.00 and writes one batch plus one `earn` ledger entry per bucket with points above zero, with key `earn:folio:{id}:{bucket}`.
- Wired at all three sites with a constructor-injected `$earnLoyalty`: the two SETTLED updates in `SettleFolioAction` and the auto-settle branch of `RecordFolioPaymentAction`. No other Folio or Booking file changed (`QuoteReservationAction` and `ReleaseExpiredHoldsAction` are byte-identical).
- The only `catch` is `UniqueConstraintViolationException`, inside a per-bucket savepoint; any other failure propagates and rolls the settlement back (proved: 500, folio still open, no payment row, no ledger row).
- Insert-only: a settle shows a `folios` lock and no lock on `guests` or `loyalty_earn_batches`.

## Task Commits

1. **Task 1 + Task 2 (one commit per project policy, full suite green first):** `0b82bef` feat(10-05): earn loyalty points inline on every folio settlement path

RED was confirmed first (25 tests, only the 4 inactive-program no-op tests passed), then GREEN; no red state was committed.

**Plan metadata:** committed separately as `docs(10-05): complete earn on settlement plan`.

## Verification

- Targeted run `--filter='LoyaltyEarn|EarnLoyaltyPoints|FolioPaymentTest|FolioTest|CheckOutTest|FolioDispute|LoyaltyLedger'`: 167 tests, 1019 assertions, pass.
- Full suite `php artisan test`: **2763 tests, 16769 assertions, exit 0** (327 s).
- Grep gates: `catch (` appears once in the action (`UniqueConstraintViolationException`); `earnLoyalty->handle` appears 2 times in `SettleFolioAction` and 1 time in `RecordFolioPaymentAction`; no manual `new SettleFolioAction` / `new RecordFolioPaymentAction` anywhere.
- Pint clean on the new files.

## Decisions Made

See `key-decisions`. The notable ones: credits are resolved in a second pass so a credit always finds its reversed line regardless of row order, and a negative manual line (FA-10.05-2) simply reduces `service` before the clamp.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] LoyaltyLedger cannot be doubled for the rollback test**
- **Found during:** Task 1
- **Issue:** The plan says to bind a `LoyaltyLedger` double whose `credit()` throws. `LoyaltyLedger` is `final`, so neither Mockery nor an anonymous subclass can replace it.
- **Fix:** The rollback test registers a `LoyaltyEarnBatch::creating` listener that throws `RuntimeException` and flushes it in `finally`. The exception is raised from inside the real `credit()` call, so the same code path (a non-unique error crossing the savepoint) is exercised.
- **Files modified:** `backend/tests/Feature/Loyalty/LoyaltyEarnOnSettleTest.php`
- **Commit:** 0b82bef

**2. [Rule 1 - Bug] Test fixture: payment replay used a fresh staff token**
- **Found during:** Task 1 (first run)
- **Issue:** each `pay()` call minted a new desk user, so the replay with the same Idempotency-Key came from a different `recorded_by` and answered 409.
- **Fix:** one desk token is reused for all payments in a test.
- **Commit:** 0b82bef

**Notes (not deviations):**
- The plan's task-1 filter `LoyaltyEarn` matches only the feature class; the verification run used `LoyaltyEarn|EarnLoyaltyPoints` so the unit class runs too.
- Added beyond the plan: a null-rate no-op via the payment route, a no-service-lines settle, an inactive-program unit test, and a metadata test that batches and entries carry folio, reservation and guest.

**Total deviations:** 1 plan-mechanism substitution, 1 trivial test fix, no scope change.

## Drift from the plan (Phase 9.1)

- Full suite count is now 2763 tests (10-03 recorded 2716). The increase is 10-04, Phase 9.1 (commit 1dac2b9) and this plan's 25 tests.
- `CmsAccessControlTest::$notYetBuilt` contains `loyalty.adjust`; plan 10-07 removes it. This plan neither touched nor depends on it.
- No count pin or permission baseline changed in this plan.

## Issues Encountered

None. No foreign-file failures; `Carlton-hotel-s/`, `backend.zip` and the other session's `STATE.md` paragraph were not touched or staged.

## Known Stubs

None.

## Threat Flags

None beyond the plan's register. T-10-18 (double-credit), T-10-19 (cancelled/post-settle farming) and T-10-20 (swallowed error hides corruption) are mitigated and tested: inline call under the folio lock, `folio_settled` guard, unique ledger key, cancelled no-op, only the unique violation caught.

## Requirements

- **LOY-03** is ticked: every settlement path credits half-up integer points per bucket, atomically, once, and never for a cancelled reservation.
- **LOY-02** and **LOY-04** were already ticked by earlier Phase 10 plans (settings API; earn guard) and this plan adds the settlement-side evidence; they were left as they were.

## Next Phase Readiness

10-06 onward can read earn entries (`earn:folio:{id}:{bucket}`, `folio_id`, `reservation_id`, `batch_id`) for the account and ledger endpoints; 10-12 clawback can take them straight into `LoyaltyLedger::clawback()`. Manual concurrent double-settle remains a MySQL-only check (FA-10.05-3, 10-VALIDATION manual list).

## Self-Check: PASSED

- backend/app/Actions/Loyalty/EarnLoyaltyPointsAction.php, LoyaltyEarnOnSettleTest.php, EarnLoyaltyPointsActionTest.php exist
- commit 0b82bef exists
