---
phase: 10-loyalty-points-program
plan: 14
subsystem: api
tags: [laravel, loyalty, reports, hotel-clock, permissions, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-01 loyalty_ledger_entries (type, occurred_at) index and BuildsLoyaltyFixtures; 10-02 LoyaltyMath and LoyaltyProgram; 10-04 cms/loyalty route block and the LoyaltyPermissionsTest contract"
provides:
  - "GET /api/cms/loyalty/reports?date_from&date_to (auth:users, permission:loyalty.view)"
  - "LoyaltyReportService::report(?string, ?string): per-type point totals over a hotel-local period plus outstanding points and USD liability"
  - "custom.validation.loyalty_report_period_too_long in en/ar/fr/tr/es"
affects: [10-15 docs, Postman and phase gate]

tech-stack:
  added: []
  patterns:
    - "Report window is HotelClock::dayWindow(date_from)[0] to dayWindow(date_to)[1] over occurred_at, half-open UTC, no SQL date functions"
    - "One grouped ledger aggregate keyed by every LoyaltyEntryType value, credited and debited side by side"

key-files:
  created:
    - backend/app/Services/Loyalty/LoyaltyReportService.php
    - backend/app/Http/Requests/Loyalty/LoyaltyReportRequest.php
    - backend/app/Http/Resources/Loyalty/LoyaltyReportResource.php
    - backend/app/Http/Controllers/Admin/LoyaltyReportController.php
    - backend/tests/Feature/Loyalty/LoyaltyReportTest.php
  modified:
    - backend/routes/api.php
    - backend/tests/Feature/Loyalty/LoyaltyPermissionsTest.php
    - backend/lang/en/custom.php
    - backend/lang/ar/custom.php
    - backend/lang/fr/custom.php
    - backend/lang/tr/custom.php
    - backend/lang/es/custom.php

key-decisions:
  - "The report is a plain service (no BaseService): there is no model, list or filter; it returns ['data','code'] like LoyaltySettingService"
  - "Order and span checks live in an after() hook that runs only when both dates are valid strings, so an array date_from cannot hit after_or_equal's TypeError (Phase 9 pattern, no Phase 9 class used)"
  - "liability_usd is null unless the stored redeem value is set and above zero; with a value and zero outstanding it is '0.00'"

patterns-established:
  - "Query budget pinned at 3 (settings row, grouped ledger sum, outstanding sum) and asserted equal with 0 and with 50 entries"

requirements-completed: [LOY-19]

coverage:
  - id: D1
    description: "Exact per-type totals over a hotel-local period; refunds never counted as issued; adjust split into issued (positive) and adjusted_out (negative)"
    requirement: "LOY-19"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyReportTest.php::test_every_type_is_summed_into_its_own_bucket_and_refunds_are_not_issued, test_the_response_carries_exactly_the_contract_keys_as_integers, test_an_empty_period_is_all_zeros"
        status: pass
    human_judgment: false
  - id: D2
    description: "Half-open hotel-local window: 23:59:59 in, next-day 00:00:00 out, DST short day, default is hotel-local today"
    requirement: "LOY-19"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyReportTest.php::test_the_window_is_hotel_local_and_half_open, test_a_period_crossing_the_dst_change_keeps_entries_in_the_short_day, test_without_params_both_bounds_are_the_hotel_local_today"
        status: pass
    human_judgment: false
  - id: D3
    description: "Outstanding points (active unexpired batches, not windowed) and liability (half-up cents, null when redeem value unset)"
    requirement: "LOY-19"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyReportTest.php (4 outstanding/liability tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Localized 422 for single date, malformed/impossible dates, reversed order and a 367-day period (366 allowed); fixed 3-query budget"
    requirement: "LOY-19, LOY-21"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyReportTest.php (validation tests, test_the_query_budget_is_fixed_and_does_not_grow_with_the_ledger)"
        status: pass
    human_judgment: false
  - id: D5
    description: "loyalty.view only: 401 no/guest token; 403 for every preset, reports.view-only, loyalty.manage-only and loyalty.adjust-only; super_admin and loyalty.view get 200"
    requirement: "LOY-20"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyReportTest.php::test_no_token_and_a_guest_token_are_401, test_users_without_loyalty_view_are_403; LoyaltyPermissionsTest::test_the_loyalty_report_is_403_for_every_preset_and_for_reports_view_or_manage_only"
        status: pass
    human_judgment: false

duration: 30min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 14: Loyalty Reports Summary

**Staff with `loyalty.view` read issued, redeemed, expired, refunded, clawed-back and adjusted-out points for any validated hotel-local period (up to 366 days), plus current outstanding points and their USD liability, in exactly three queries.**

## Performance

- **Duration:** about 30 min
- **Completed:** 2026-10-05
- **Tasks:** 2 (RED specs, GREEN implementation)
- **Files:** 5 created, 7 modified

## Test counts

- Task 1 RED confirmed: 25 tests in the filtered run, 19 failing or erroring (404 for the missing route, "target class does not exist" for the service); the 6 passing were the existing permission tests.
- Targeted run (`LoyaltyReport|LoyaltyPermissions|LoyaltyLocaleTest|ValidationMessageLocalizationTest|CmsAccessControlTest`): 86 passed.
- **Full suite `php artisan test`: 2992 tests, 2992 passed, 18222 assertions, exit 0 (327 s)** before the code commit. 10-13 ended at 2973, so this plan adds 19 (18 in `LoyaltyReportTest`, 1 in `LoyaltyPermissionsTest`).
- Pint clean on all new files (it removed one unused closure import and added a return type in the new test).

## Accomplishments

- `LoyaltyReportService::report()` defaults both bounds to `HotelClock::today()`, builds the window from `dayWindow(from)[0]` and `dayWindow(to)[1]`, and runs one `GROUP BY type` query (credited and absolute-debited sums per type, `whereIn('type', all six)` so the `(type, occurred_at)` index is usable). Buckets: issued = earn credited + adjust credited; redeemed = redeem debited; expired = expire debited; refunded = refund credited; clawed_back = clawback debited; adjusted_out = adjust debited.
- Outstanding is `SUM(points_remaining)` over `status = active AND expires_at > now()` (point in time, not windowed); liability is `LoyaltyMath::discountForPoints(outstanding, redeem_value_usd)`, null while the value is unset or zero.
- `LoyaltyReportRequest`: both-or-none strict `Y-m-d`; order and the 366-day inclusive cap in an `after()` hook. The cap error is `custom.validation.loyalty_report_period_too_long`, added at the end of `validation` in all five locales.
- Route `GET /reports` inside the existing `permission:loyalty.view` group of the `cms/loyalty` block; `route:list` shows `PermissionMiddleware:loyalty.view`. The dynamic route-gate contract in `LoyaltyPermissionsTest` picked it up with no edit.

## Task Commits

By project policy all code landed in one commit after the full suite passed (RED was run and confirmed failing first):

1. **Tasks 1-2: feat(10-14)** - `0979410`

Metadata commit follows as `docs(10-14)`.

## Decisions Made

See `key-decisions` above.

## Deviations from Plan

### Auto-fixed Issues

None needing a rule. Small shape choices the plan left open:

- The request adds `bail`, `nullable` and `string` ahead of `date_format` (Phase 9 pattern) so an array value is a 422, not a 500; the reversed-order check moved from `after_or_equal` to the `after()` hook for the same reason (its text reuses `custom.validation.after_or_equal`).
- Extra tests beyond the plan: the key-set/integer contract, half-up liability rounding, liability null with no settings row, `loyalty.adjust`-only 403, guest-token 401, and a 365/366-day acceptance check.

### Count and pin drift (Phase 9.1, commit 1dac2b9)

No pin was affected: 2973 tests at 10-13 versus 2992 now matches this plan's 19 additions exactly. No route-count pin exists, so the extra route needed no re-pin.

**Total deviations:** 0 rule-driven, no scope change.

### Shared-file staging (project override, not a plan deviation)

`.planning/STATE.md` carries a concurrent session's uncommitted Phase 9.1 paragraph. Only this plan's hunks are staged in the metadata commit. `backend/database/database.sqlite`, `Carlton-hotel-s/` and `backend.zip` were left untouched and unstaged.

## Issues Encountered

None.

## Known Stubs

None. The endpoint reads live ledger and batch data.

## Threat Flags

None beyond the plan's register. T-10-55 (dedicated `loyalty.view`, 403 matrix including reports.view-only), T-10-56 (366-day cap, fixed 3-query budget, indexed type/occurred_at scan) and T-10-57 (HotelClock half-open UTC bounds, 23:59:59, midnight and DST edge tests) are mitigated and tested.

## Requirements

- **LOY-19 ticked:** staff report points issued (earn + positive adjust), redeemed, expired, refunded, clawed back and adjusted out, plus outstanding points and liability.
- LOY-20 and LOY-21 were already ticked by earlier plans; this plan adds the report route to the 403 matrix and one validation string x5 but changes no checkbox. Docs, Postman and tree for LOY-21 complete in 10-15.

## Next Phase Readiness

10-15 should document `GET /api/cms/loyalty/reports` (query `date_from`/`date_to`, max 366 days, both or neither, hotel-local), that `outstanding_points` and `liability_usd` are point in time rather than as of `date_to` (FA-10.14-2), that refunds are reported separately and never counted as issued (FA-10.14-1), and the new `loyalty_report_period_too_long` validation key.

## Self-Check: PASSED

- All 5 created files and 7 modified files exist and are in commit `0979410`.
- Commit `0979410` exists on `main`; nothing pushed.
- Full suite exit 0 (2992 tests) before the code commit.
