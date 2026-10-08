---
phase: 10-loyalty-points-program
plan: 16
subsystem: loyalty
tags: [loyalty, gap-closure, account-deletion, ledger, privacy, LOY-23]
status: complete

requires:
  - phase: 10-loyalty-points-program
    provides: "10-03 LoyaltyLedger (expire, refund Q3), 10-12 reversals, 10-13 expiry sweep, 10-15 docs"
  - phase: 09.1-guest-account-support
    provides: "DeleteGuestAccountAction (D-05..D-09), deletion guard D-07, staff reads of deleted guests D-12, re-sign-in D-10"
provides:
  - "LoyaltyLedger::forfeit(int $guestId): array{points, batches} - expires every active batch of one guest through expire(), FIFO"
  - "ForfeitLoyaltyBalanceAction::handle(Guest $lockedGuest) - forfeit + close active vouchers (void / expired), counts only"
  - "Account deletion forfeits the balance in its transaction; audit entry gains loyalty{forfeited_points, expired_batches, closed_vouchers}"
  - "Reversal for a deleted guest re-forfeits; settlement for a deleted guest earns nothing (loyalty.earn_skipped_deleted)"
  - "10-VALIDATION.md manual row: deletion racing a staff cancel (MySQL deadlock window)"
affects: [10-17 guards and docs, Flutter mobile guide (account deletion), React dashboard (loyalty view of a deleted guest)]

tech-stack:
  added: []
  patterns:
    - "Forfeit = existing `expire` entries with key expire:batch:{id}; no new entry type, enum case, lang key or error_code"
    - "Counts-only activity for skips on erased accounts (causedByAnonymous, no uuid)"

key-files:
  created:
    - backend/app/Actions/Loyalty/ForfeitLoyaltyBalanceAction.php
    - backend/tests/Unit/Loyalty/LoyaltyLedgerForfeitTest.php
    - backend/tests/Unit/Loyalty/ForfeitLoyaltyBalanceActionTest.php
    - backend/tests/Feature/Loyalty/LoyaltyForfeitOnDeletionTest.php
  modified:
    - backend/app/Support/LoyaltyLedger.php
    - backend/app/Actions/Guest/DeleteGuestAccountAction.php
    - backend/app/Actions/Loyalty/ReverseLoyaltyForReservationAction.php
    - backend/app/Actions/Loyalty/EarnLoyaltyPointsAction.php
    - backend/tests/Feature/Guests/DeleteGuestAccountActionTest.php
    - .planning/phases/10-loyalty-points-program/10-VALIDATION.md

key-decisions:
  - "Forfeit runs as the first statement inside the deletion's withoutLogging closure: after assertDeletable, locks guest -> batches -> vouchers before any other write, voucher changes add no activity row"
  - "The earn skip for a deleted guest uses causedByAnonymous() so the entry has no causer even when staff settle (activitylog otherwise resolves the authenticated staff user)"
  - "Deletion vs staff cancel deadlock accepted (FA-10.16-6), recorded as a MySQL manual verification row"

requirements-completed: [LOY-23, LOY-09, LOY-17]

metrics:
  duration: "~45 min"
  completed: 2026-10-07
  tasks: 3
  files: 10
---

# Phase 10 Plan 16: Forfeit loyalty balance on guest account deletion Summary

Deleting a guest account now forfeits the whole points balance as `expire:batch:{id}` ledger entries via `LoyaltyLedger::forfeit()` and closes every active voucher (void while valid, expired at or past expiry) inside the 9.1 deletion transaction, audited with integer counts only. A later staff cancel re-forfeits whatever the reversal gives back, and a later settlement earns nothing (`loyalty.earn_skipped_deleted`, `{skipped_points}` only).

## Tasks

| Task | Name | Commit |
|------|------|--------|
| 1 | Forfeit-on-deletion specs (RED) | 40969f9 |
| 2 | LoyaltyLedger::forfeit and ForfeitLoyaltyBalanceAction (GREEN, unit) | f38d9e7 |
| 3 | Wire into deletion, reversal and earn; VALIDATION row (GREEN, feature) | c37d38a |

## RED evidence (Task 1)

- `DeleteGuestAccountActionTest`: the other 12 methods passed; only `test_activity_log_keeps_no_pii_and_records_the_deletion` failed (missing `loyalty` block).
- `LoyaltyLedgerForfeitTest`: 9/9 errored with `Call to undefined method LoyaltyLedger::forfeit()`.
- `ForfeitLoyaltyBalanceActionTest`: 6/6 errored with `Target class [ForfeitLoyaltyBalanceAction] does not exist`.
- `LoyaltyForfeitOnDeletionTest`: 12 of 14 failed for the intended reasons (no expire entries, report still 130, no batch lock, earn batch written for a deleted guest, the deleted account still holding its 500 points after re-sign-in (the new account already showed 0, as 9.1 D-10 predicts), missing audit `loyalty` key after the cancel-case preconditions all held). The 2 that passed are non-regression guards that hold before the change too (blocked deletion changes nothing; repeat deletion changes nothing).

## Verification

- Task 2 filtered suites: 57/57 green; Pint clean; signature greps and the function-scoped diff of `expire()` / `spendable()` against HEAD show no change; `forfeit()` does not call `spendable(`.
- Task 3 filtered suites (deletion, guard, visibility, reversal incl. the pinned 7-query cancel, report, expiry, ledger, earn): 212/212 green.
- Full suite `php artisan test`: **3070 passed, 0 failed** (18694 assertions; baseline 3041 + 29 new).
- `QuoteReservationAction` / `ReleaseExpiredHoldsAction` byte-unmodified; no change under `backend/lang`, `backend/routes`, `backend/database/migrations`.

## Deviations from Plan

### Acceptance-grep miscounts (no code deviation)

1. **`grep -c "earn_skipped_cancelled" EarnLoyaltyPointsAction.php` "is still 1"** - it is 2, exactly as at HEAD (docblock + `log()` call; `git show HEAD:... | grep -c` = 2). Unchanged, which is the intent of "still".
2. **`grep -c "earn_skipped_deleted" EarnLoyaltyPointsAction.php` "is 1"** - it is 2, because Task 3 item 3 itself requires the docblock sentence naming `loyalty.earn_skipped_deleted`; the code writes it once (one `log()` call).

### Test-interpretation note

- **Audit test "no activity row contains the guest uuid"**: the voucher code, old phone and old email are checked against every activity row; the guest uuid is checked against the rows written by the deletion. Earlier rows (e.g. the guest's own `created` audit, written before the deletion) may legitimately hold the uuid, which is a public id and not PII under 9.1 D-08 (the redaction list does not include it), so asserting its absence from pre-existing rows would test 9.1, not this plan.

### Auto-fixed Issues

None. `causedByAnonymous()` on the earn-skip entry is the implementation of the plan's "no causer" requirement (activitylog otherwise attaches the authenticated staff user during the settle request).

## Human-needed (MySQL staging, before release)

- **Deletion racing a staff cancel of the same guest** (new 10-VALIDATION.md row, LOY-23 / LOY-17): run the forced (a) and natural (b) procedures on MySQL. **Outcome: not run yet** (no MySQL staging in this environment); record the result here when done.
- Existing Phase 10 MySQL rows still apply (true concurrent double-redeem / double-settle).

## Known Stubs

None.

## Threat Flags

None. No new route, endpoint, auth path or schema change; the only new activity description is `loyalty.earn_skipped_deleted` (integer properties only, T-10-68 / T-10-69 mitigated and tested).

## Self-Check: PASSED

- Files exist: ForfeitLoyaltyBalanceAction.php, LoyaltyLedgerForfeitTest.php, ForfeitLoyaltyBalanceActionTest.php, LoyaltyForfeitOnDeletionTest.php.
- Commits exist: 40969f9, f38d9e7, c37d38a.
