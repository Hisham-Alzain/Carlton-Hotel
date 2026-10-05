---
phase: 10-loyalty-points-program
plan: 12
subsystem: api
tags: [laravel, loyalty, booking, cancellation, reversal, pessimistic-locks, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-03 LoyaltyLedger refund/clawback, 10-05 EarnLoyaltyPointsAction, 10-09 guest vouchers, 10-11 loyalty_reservation_applications"
provides:
  - "Cancelling a reservation reverses every loyalty effect once: spent points refunded, used voucher restored, folio earnings clawed back with recorded shortfall (LOY-17)"
  - "ReverseLoyaltyForFolioAction: standalone, unit-tested folio clawback seam for the future folio-refund flow (LOY-18)"
  - "CancelReservationAction re-checks the status under the reservation row lock (M-3)"
affects: [10-13 expiry jobs, 10-14 reports (refund/clawback entry types and loyalty.clawback_shortfall), 10-15 guides and Postman]

tech-stack:
  added: []
  patterns:
    - "Cancel lock order: reservation -> folio -> guest -> application/batches/voucher, asserted by a lock-order test"
    - "Shortfall is logged only for clawback entries written by the current call (wasRecentlyCreated), so a replay logs nothing"

key-files:
  created:
    - backend/app/Actions/Loyalty/ReverseLoyaltyForReservationAction.php
    - backend/app/Actions/Loyalty/ReverseLoyaltyForFolioAction.php
    - backend/tests/Feature/Loyalty/LoyaltyReversalOnCancelTest.php
    - backend/tests/Unit/Loyalty/ReverseLoyaltyForFolioActionTest.php
  modified:
    - backend/app/Actions/Booking/CancelReservationAction.php

key-decisions:
  - "The status check moved wholly inside the transaction onto the locked row (the old outside-the-transaction check is gone, not duplicated), so a stale instance cannot cancel a checked-in stay"
  - "A reservation with no folio still takes a folio-keyed lock select, so the lock order is the same on every path and the query cost of a plain cancel is a fixed 7"
  - "restoreVoucher restores only a voucher that is used by this very reservation; any other state is left untouched and reported voucher_restored=false"
  - "The caller's Reservation instance keeps the old in-place status update (setAttribute + syncOriginalAttribute) instead of a refresh query"

patterns-established:
  - "Reversal = refund then restore then clawback, all inside the cancel transaction; every ledger write is keyed so a replay is a no-op"

requirements-completed: [LOY-18]

coverage:
  - id: D1
    description: "Points refund to the original batch, or one fresh refund batch with a full term when the source batch expired (Q3)"
    requirement: "LOY-17"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyReversalOnCancelTest.php::test_cancelling_a_points_booking_refunds_the_points_to_the_original_batch, ::test_an_expired_source_batch_is_not_revived_and_a_fresh_refund_batch_takes_the_points"
        status: pass
    human_judgment: false
  - id: D2
    description: "Voucher restored (active, reservation_id and used_at cleared, expiry kept) and, when already expired, given the hotel-local end-of-day grace (Q13, M-9)"
    requirement: "LOY-17"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyReversalOnCancelTest.php::test_cancelling_restores_a_used_voucher_with_its_expiry_unchanged, ::test_a_voucher_restored_after_its_expiry_gets_the_grace_period"
        status: pass
    human_judgment: false
  - id: D3
    description: "Folio earnings clawed back; partly or fully spent earnings floor at zero with shortfall_points and a loyalty.clawback_shortfall activity row, never a negative balance and never a blocked cancel (Q2)"
    requirement: "LOY-17"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyReversalOnCancelTest.php (settled stay, partly spent, fully spent, combined refund-then-clawback tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Idempotency and M-3: a second DELETE is 422 reservation_state with no write; the reversal action run twice writes nothing (not even a second shortfall log); the status is re-checked under the row lock; the 204 return is unchanged"
    requirement: "LOY-17"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyReversalOnCancelTest.php::test_a_second_cancel_is_a_422_that_writes_nothing, ::test_the_reversal_action_writes_nothing_the_second_time, ::test_the_status_is_rechecked_under_the_row_lock, ::test_the_cancel_action_keeps_its_return_contract"
        status: pass
    human_judgment: false
  - id: D5
    description: "Folio seam: every earn entry clawed back, replay writes nothing, empty for a folio with no earn or no guest, lock order folio -> guest -> batches, TODO naming the refund flow"
    requirement: "LOY-18"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/ReverseLoyaltyForFolioActionTest.php (6 tests)"
        status: pass
    human_judgment: false
  - id: D6
    description: "Staff and guest cancel both reverse; 401/403 kept; plain cancel writes no loyalty row at a pinned 7 queries; hold release touches no loyalty table; reservation -> folio -> guest -> batches lock order"
    requirement: "LOY-17"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyReversalOnCancelTest.php::test_staff_cancel_performs_the_same_reversal, ::test_cancel_endpoints_still_require_authentication_and_the_cancel_permission, ::test_a_cancel_without_loyalty_writes_no_loyalty_row_and_adds_a_pinned_number_of_queries, ::test_releasing_expired_holds_touches_no_loyalty_table, ::test_lock_order_for_a_cancel_is_reservation_folio_guest_batches"
        status: pass
    human_judgment: false

duration: 40min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 12: Cancel Reversals and the Folio-Refund Seam Summary

**Cancelling a reservation now refunds spent points (original or fresh batch), restores a used voucher and claws back folio earnings with a recorded shortfall, exactly once, in one lock order and never below zero; the folio clawback is a tested seam for the future refund flow.**

## Performance

- **Duration:** about 40 min
- **Completed:** 2026-10-05
- **Tasks:** 2 of 2 (Task 1 RED confirmed failing: 24 tests, 4 passing only because they assert refusals or absence; Task 2 GREEN passed 24/24)
- **Files:** 5 in the code commit (4 created, 1 modified)

## Counts and pins (re-read at execution; Phase 9.1 commit 1dac2b9 had already landed)

- Targeted run `LoyaltyReversal|ReverseLoyalty`: 24 tests (18 in `LoyaltyReversalOnCancelTest`, 6 in `ReverseLoyaltyForFolioActionTest`).
- Plan verify set (`LoyaltyReversal|ReverseLoyalty|LoyaltyBooking|LoyaltyLedger|ReservationTest|GuestBookingTest|DigitalKeyLifecycleTest|ConcurrencyTest` plus `SubmitOnlineCheckIn`, the other caller of the cancel action): 126 tests green.
- Full suite `php artisan test`: **2954 tests, 17970 assertions, exit 0** (343 s). 10-11 ended at 2930, so this plan adds exactly 24. No route-, permission-, controller- or query-count pin elsewhere moved, so no re-pin was needed.
- `QuoteReservationAction.php` and `ReleaseExpiredHoldsAction.php` are byte-unmodified (`git diff --quiet HEAD` exits 0).
- No route, permission or controller count moved. Pinned query count of a plain (no loyalty) cancel: 7 = reservation lock + status update + one further reservation read made while updating the status + key-revoke lock + folio lock + guest lock + application lock (it was 3 before Phase 10). It is the only pin this plan adds.

## Accomplishments

- `CancelReservationAction` now locks the reservation row inside the transaction, re-checks `isCancellable()` on the locked row (same `ReservationStateException`), updates the status, revokes the digital key as before and calls `ReverseLoyaltyForReservationAction`. It still returns `['data' => null, 'code' => 204]`.
- `ReverseLoyaltyForReservationAction::handle(Reservation $locked)` locks folio, guest and application in that order; for an `applied` application it refunds the redeem entry through `LoyaltyLedger::refund`, restores the voucher and flips the application to `reversed` with `reversed_at`; then it delegates the folio's earn entries to the folio action and writes `loyalty.clawback_shortfall` on the guest when a freshly written clawback has `shortfall_points > 0`. A reservation without a guest is a no-op.
- `ReverseLoyaltyForFolioAction::handle(Folio)` locks folio, guest, then the folio's earn entries (`lockForUpdate`, ordered by id) and claws each back; returns `['data' => [], 'code' => 200]` for no earn or no guest. Carries `// TODO(refund flow)`. No route, no refund writer, no folio reopen.

## Task Commits

Both tasks landed in one code commit by project policy (never commit a red state; the RED specs were written and confirmed failing first):

1. **Tasks 1-2: feat(10-12)** - `4750a80`

Metadata commit: the `docs(10-12)` commit that follows.

## Decisions Made

See `key-decisions`. FA-10.12-1 (hotel-local grace days) is implemented and tested. FA-10.12-2 (earn entries selected `lockForUpdate`, so a cancel that waited behind a concurrent settle sees the committed earn) is implemented but MySQL-only behaviour; SQLite ignores row locks, so it is covered only by the recorded-lock-clause tests and belongs with the manual checks.

## Deviations from Plan

### Plan wording adjusted

- **Staff test covers points and voucher separately:** the plan's staff scenario implies one booking, but a booking cannot carry points and a voucher together (`LoyaltyDiscountConflictException`, 10-10). The staff test cancels one points booking and one voucher booking; the "idempotent twice" test uses points only.
- **Hold-release byte check:** the `git diff --quiet HEAD -- ...ReleaseExpiredHoldsAction.php ...QuoteReservationAction.php` assertion is in the verify command only, not in a PHPUnit test, because it would turn into a false failure the day a later phase legitimately edits either file. The test still runs `booking:release-holds` and pins all seven loyalty tables.
- **Query pin is 7, not a plan number:** the plan asked to pin the count without giving it; 7 is measured (see above).

### Auto-fixed Issues

None.

## Issues Encountered

None. Pint ran on the four new files (it only normalised line endings in the feature test); `CancelReservationAction.php` was rewritten as LF to match the file's previous style and the diff is the intended change only.

## Known Stubs

None.

## Threat Flags

None. T-10-46 (status re-checked under the reservation lock; application status plus unique `reverses_entry_id`; double-cancel and replay tests), T-10-47 (floor at zero, shortfall in the ledger and activity log; partly and fully spent tests), T-10-48 (voucher restored under lock with `reservation_id` nulled, application reversed once), T-10-49 (fixed lock order; lock-order tests on both actions) are mitigated and tested. T-10-50 is accepted: the hold-release test proves no loyalty row is touched.

## Acceptance gates (all clean)

- `lockForUpdate` appears once and `'code' => 204` once in `CancelReservationAction.php`.
- `TODO` present in `ReverseLoyaltyForFolioAction.php`; `grep -rn "Refund::create\|FolioStatus::OPEN" app/Actions/Loyalty` prints nothing.
- `reversal ok` printed: quote and hold actions unmodified.

## Next Phase Readiness

10-13 (expiry jobs) can rely on `refund` batches and reversed/depleted origin batches written here being ordinary batches. 10-14 reports now see `refund` and `clawback` entry types produced by real cancels, and `loyalty.clawback_shortfall` rows on the guest subject. LOY-18 is delivered and ticked; LOY-17 was already ticked and is now fully delivered end to end.

## Self-Check: PASSED

- All 4 created and 1 modified file exist and are in commit `4750a80`; nothing pushed; foreign files (`Carlton-hotel-s/`, `backend.zip`) and the foreign STATE.md paragraph untouched and unstaged.
- Full suite exit 0 (2954 tests) before the code commit.

