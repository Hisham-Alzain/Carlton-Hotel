# Phase 10 gap plans 10-16 / 10-17 (LOY-23): review

**Reviewer:** user-supplied review (pasted 2026-10-07), addressed to Hisham. Requested: update the plans before running them.

## Must fix before running

1. **10-16: a deleted account must not earn points.** The plan blocks points returning after a cancel, but not points from a stay being settled.
   - In `EarnLoyaltyPointsAction`, skip when `$guest->isDeleted()`, like the existing cancelled skip, and log `loyalty.earn_skipped_deleted` (counts only).
   - Add a test: settle a folio for a deleted guest and assert no batch is created.
   - If you can prove a folio can never be settled after deletion, cite the test that shows it instead.
2. **10-16: say exactly how the "cancel after deletion" test reaches its starting state.** The deletion guard blocks guests with an open folio, so this setup may be impossible.
   - Name the reservation and folio state the test uses (for example a no-show, or a folio closed without settlement), and confirm deletion is allowed in that state before writing the assertions.
   - Do not force the state with direct DB writes; that would prove nothing.
3. **10-17: check the sign-in rule before writing it in the docs.** Add `09.1-CONTEXT.md` to Task 3's `read_first`. Confirm that "signing in again creates a new account with zero points" is really how 9.1 works, and quote it in the docs only after confirming.

## Should do

4. **10-16: deadlock risk.** Deletion locks the guest and then writes reservations; cancel locks reservation -> folio -> guest. A cancel and a deletion for the same guest can deadlock and return a 500. Either add this case to the MySQL manual verification list, or lock the reservations before the guest in the deletion.
5. **10-16:** make the cancel-after-deletion test cover both refund outcomes: a revived depleted batch and a new refund batch.
6. **10-16:** replace the "additive only" check on `LoyaltyLedger.php` (it counts removed diff lines) with a test or a grep on `function expire` and `function spendable`. Pint reformatting would otherwise fail the check.
7. **10-17: leftover points on deleted accounts.** If 9.1 has run anywhere that already has deleted accounts, add an idempotent `loyalty:forfeit-deleted` command (about 20 lines) that runs each deleted guest through `ForfeitLoyaltyBalanceAction`. If not, record "no leftover points at ship" in FA-10.17-3.
8. **10-17:** strengthen the Task 1 "test fails first" check: run the existing test methods on their own and require them to pass, not just that the run exits non-zero.

## Nice to have

9. Dashboard guide: note that `outstanding_points` will not equal issued - redeemed - expired while leftover points exist.
10. Dashboard handoff: add a label for the voucher `void` status.
11. Decide what staff see on `GET /cms/loyalty/guests/{uuid}` for a deleted guest (a 404 or redacted data, following the 9.1 rules), and write it down.
