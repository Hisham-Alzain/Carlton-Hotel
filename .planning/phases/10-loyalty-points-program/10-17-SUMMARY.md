---
phase: 10-loyalty-points-program
plan: 17
subsystem: loyalty
tags: [loyalty, gap-closure, account-deletion, guards, artisan, docs, LOY-23]
status: complete

requires:
  - phase: 10-loyalty-points-program
    provides: "10-16 ForfeitLoyaltyBalanceAction, LoyaltyLedger::forfeit, deletion forfeit"
  - phase: 09.1-guest-account-support
    provides: "GuestAccountDeletedException (guest_account_deleted), Guest::isDeleted(), D-10 re-registration, D-12 staff reads"
provides:
  - "NotifyExpiringLoyaltyPointsAction never warns a deleted account (window filter + isDeleted() under the guest lock)"
  - "AdjustLoyaltyPointsAction refuses deleted accounts with 422 guest_account_deleted under the guest lock, before the replay lookup"
  - "LoyaltyReportService outstanding_points / liability_usd exclude deleted accounts (same single statement, 3-query budget kept)"
  - "ForfeitDeletedGuestBalancesAction + one-off unscheduled `php artisan loyalty:forfeit-deleted`"
  - "Dashboard/mobile guides and mobile changelog describe the forfeit, guards, staff view of a deleted guest, void lifecycle, the command and the app warning"
affects: [Flutter account-deletion confirmation, React dashboard adjust button / loyalty report]

tech-stack:
  added: []
  patterns:
    - "whereDoesntHave('guest', account_status = deleted) as the residue filter"
    - "Per-guest transaction backfill: lock guest, re-check isDeleted(), delegate to ForfeitLoyaltyBalanceAction"

key-files:
  created:
    - backend/app/Actions/Loyalty/ForfeitDeletedGuestBalancesAction.php
    - backend/app/Console/Commands/ForfeitDeletedLoyaltyBalances.php
    - backend/tests/Feature/Loyalty/LoyaltyForfeitDeletedCommandTest.php
  modified:
    - backend/app/Actions/Loyalty/NotifyExpiringLoyaltyPointsAction.php
    - backend/app/Actions/Loyalty/AdjustLoyaltyPointsAction.php
    - backend/app/Services/Loyalty/LoyaltyReportService.php
    - backend/tests/Feature/Loyalty/LoyaltyExpiryWarningTest.php
    - backend/tests/Feature/Loyalty/LoyaltyAdjustTest.php
    - backend/tests/Feature/Loyalty/LoyaltyReportTest.php
    - backend/docs/API_GUIDE_DASHBOARD.md
    - backend/docs/API_GUIDE_MOBILE.md
    - backend/docs/CHANGELOG_MOBILE_API.md

key-decisions:
  - "Deleted check in staff adjust precedes IdempotentWrite, so a replay of a pre-deletion key answers 422, not the stored 200 (FA-10.17-1)"
  - "loyalty:forfeit-deleted is one-off and unscheduled; one transaction per guest, account counted when points were forfeited or a voucher closed"
  - "Changelog entry placed at the top (newest-first) rather than directly above the Phase 10 section, because the quick task 261007-it6 entry now sits between them"

requirements-completed: [LOY-23, LOY-05, LOY-10, LOY-19]

metrics:
  duration: "~35 min"
  completed: 2026-10-07
  tasks: 3
  files: 12
---

# Phase 10 Plan 17: Deleted-account loyalty guards, leftover forfeit command and docs Summary

Deleted accounts now get no expiry push (window filter plus an `isDeleted()` re-check under the guest lock), staff adjustments on them answer `422 guest_account_deleted` before the idempotent replay lookup, and the report's outstanding points and liability leave them out in the same single SQL statement. A one-off idempotent `php artisan loyalty:forfeit-deleted` forfeits leftover points and closes vouchers on accounts deleted before 10-16 shipped, through `ForfeitLoyaltyBalanceAction` under the guest lock. Both API guides and the mobile changelog document the behaviour.

## Tasks

| Task | Name | Commit |
|------|------|--------|
| 1 | Deleted-guest guard specs and command spec (RED) | 00e636d |
| 2 | Guards + ForfeitDeletedGuestBalancesAction + loyalty:forfeit-deleted (GREEN) | fde150d |
| 3 | Guides, changelog, phase gate | 4414067 |

## RED evidence (Task 1)

- Existing methods alone (`--exclude-filter deleted_account`): 47/47 passed.
- Marker methods: 9/9 failed for the intended reasons (adjust 201/200 instead of 422, direct call did not throw, warning job notified the deleted account, report outstanding 1000 instead of 300).
- `LoyaltyForfeitDeletedCommandTest`: 6/6 errored (command / action class did not exist).
- Method-list diff against HEAD: unchanged and in order for all three extended files.

## Verification

- Task 2 filtered suites (warning, adjust, adjust action unit, report, staff view, forfeit-on-deletion, command): 92/92 green; Pint clean; command registered; `routes/console.php` unchanged; all Task 2 greps satisfied.
- Task 3 step 0: 09.1-CONTEXT D-10 still reads "creates a **new** guest ... cannot be re-linked"; `php artisan test --filter=test_signing_in_again` ran both the 9.1 and the 10-16 test, 2/2 passed. Only then was the zero-points re-registration sentence written.
- Docs greps: all acceptance counts met (`guest_account_deleted` in dashboard guide = 6, `loyalty:forfeit-deleted` = 2, `Re-registration:` still 1, `Phase 10 (gap)` = 1).
- Protected paths unmodified vs HEAD: `docs/DASHBOARD_FRONTEND_HANDOFF.md`, `docs/MOBILE_FRONTEND_HANDOFF.md`, `docs/carlton-tree.html`, `backend/docs/MOBILE_API_DESIGN.md`, `backend/docs/postman`, `backend/lang`, `backend/routes`, migrations, `QuoteReservationAction`, `ReleaseExpiredHoldsAction`.
- Pint clean on every PHP file touched by 10-16 and 10-17.
- Full suite `php artisan test`: **3085 passed, 0 failed** (18780 assertions; baseline 3070 + 15 new).

## Deviations from Plan

1. **Changelog placement.** The plan says insert directly above `## 2026-10-05 — Phase 10 — Loyalty Points Program`. Since the plan was written, quick task 261007-it6 added a `## 2026-10-07 — Quick 261007-it6` section above that heading. The new entry was put at the top of the dated sections to keep the file newest-first. Content as specified.
2. **Small additions beyond the plan text (docs only):** the scheduled-jobs paragraph also says a deleted account never gets the expiry push. The mobile **Loyalty** paragraph says a closed voucher is `void`, or `expired` if it was already past its expiry, matching what 10-16 shipped.
3. **Line endings.** The orchestrator brief said to preserve CRLF. The repo stores and checks out these files as LF (`git ls-files --eol`: `i/lf w/lf`, `.gitattributes text=auto eol=lf`), and Pint's `line_ending` fixer requires LF. All new and edited files are LF, the same as the existing files.
4. **Test choice.** The expiry-warning spec has 2 marker methods (the minimum). A third, which called the private `warn()` through reflection to cover the list-to-lock race, was dropped because it tested a private method. The `isDeleted()` re-check under the lock is still in the code, and the window filter is covered.

No auto-fixed bugs. No architectural changes.

## Human-needed

- **Run `php artisan loyalty:forfeit-deleted` once on every environment deployed from origin/main since the 9.1 and Phase 10 commits**, after deploying 10-16 and 10-17. Record the printed line here: *not run yet*. It is safe to re-run and prints zeros when nothing is left.
- From 10-16, still open: deletion racing a staff cancel on MySQL staging (10-VALIDATION.md row). Existing Phase 10 MySQL concurrency rows also still apply.
- Flutter team: add the pre-deletion warning (`available_points`, active voucher count). React team: hide or disable "adjust points" for `account_status: deleted`. Both are documented in the guides and the changelog. No contract change.

## Known Stubs

None.

## Threat Flags

None. The only new entry point is an operator-only artisan command (T-10-76/T-10-77 in the plan's threat model, mitigated and tested). It adds no route, error code, lang key or migration.

## Self-Check: PASSED

- Files exist: ForfeitDeletedGuestBalancesAction.php, ForfeitDeletedLoyaltyBalances.php, LoyaltyForfeitDeletedCommandTest.php.
- Commits exist: 00e636d, fde150d, 4414067.
