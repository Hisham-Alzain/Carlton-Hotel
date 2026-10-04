---
phase: 10-loyalty-points-program
plan: 04
subsystem: api
tags: [laravel, loyalty, settings, permissions, spatie, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-01 LoyaltySetting model and BuildsLoyaltyFixtures; 10-02 LoyaltyProgram (current, capabilities, settings) and loyalty error strings"
provides:
  - "GET /api/cms/loyalty/settings (loyalty.view|loyalty.manage) and PUT /api/cms/loyalty/settings (loyalty.manage)"
  - "UpdateLoyaltySettingsAction: present-key singleton upsert under lockForUpdate, audited via LogsActivity"
  - "Permissions loyalty.view, loyalty.manage, loyalty.adjust (guard users, in no preset)"
  - "LoyaltyPermissionsTest: dynamic route-gate contract for every api/cms/loyalty and api/loyalty route"
affects: [10-05 through 10-15, loyalty rewards CRUD, guest ledger views, manual adjustments, loyalty reports]

tech-stack:
  added: []
  patterns:
    - "Singleton settings write: select singleton=1 FOR UPDATE; on a missing row insert inside a savepoint and on UniqueConstraintViolationException re-select under the lock"
    - "Route-contract tests walk Route::getRoutes() by uri prefix, so later plans' loyalty routes are checked without editing the test"

key-files:
  created:
    - backend/app/Actions/Loyalty/UpdateLoyaltySettingsAction.php
    - backend/app/Services/Loyalty/LoyaltySettingService.php
    - backend/app/Http/Requests/Loyalty/UpdateLoyaltySettingsRequest.php
    - backend/app/Http/Resources/Loyalty/LoyaltySettingsResource.php
    - backend/app/Http/Controllers/Admin/LoyaltySettingController.php
    - backend/tests/Feature/Loyalty/LoyaltySettingsTest.php
    - backend/tests/Feature/Loyalty/LoyaltyPermissionsTest.php
  modified:
    - backend/routes/api.php
    - backend/database/seeders/RolesAndPermissionsSeeder.php
    - backend/tests/Feature/SeederTest.php
    - backend/tests/Feature/Staff/PermissionsGroupedTest.php
    - backend/tests/Feature/Cms/CmsAccessControlTest.php
    - backend/lang/en/custom.php
    - backend/lang/ar/custom.php
    - backend/lang/fr/custom.php
    - backend/lang/tr/custom.php
    - backend/lang/es/custom.php

key-decisions:
  - "PUT applies only the keys present; an explicit null clears a nullable value and an absent key never clears (FA-10.04-1)"
  - "The first save builds the new row with the submitted values, so the audit trail has one created entry rather than created plus updated"
  - "LoyaltySettingService is a plain service (no BaseService): the singleton has no list, filter or route-bound model"
  - "Settings stay out of site_settings and out of any cache; the resource wraps LoyaltyProgram, which reads fresh"

patterns-established:
  - "Permission baseline is measured before the re-pin: 30 permissions and 13 groups, then 33 and 14"

requirements-completed: [LOY-01]

coverage:
  - id: D1
    description: "GET returns the six values, capability flags and updated_at; with no row it returns nulls, 24, 30 and program {false, false, true} and writes nothing"
    requirement: "LOY-02"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltySettingsTest.php::test_get_with_no_row_returns_nulls_and_defaults_and_writes_nothing, test_get_returns_the_stored_values"
        status: pass
    human_judgment: false
  - id: D2
    description: "PUT upserts the singleton with present-key semantics, sets updated_by, is audited with old and new values, and respects LOY-22 capability rules"
    requirement: "LOY-01"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltySettingsTest.php::test_put_creates_the_singleton_row_and_answers_with_the_new_values, test_put_is_recorded_in_the_activity_log_with_old_and_new_values, test_put_applies_only_the_keys_present_and_an_explicit_null_clears, test_an_empty_put_returns_200_with_unchanged_values, test_a_redeem_value_without_a_cap_leaves_points_discount_off, test_an_earn_rate_of_zero_leaves_earning_off"
        status: pass
    human_judgment: false
  - id: D3
    description: "Validation bounds return localized 422; 401 for no token and guest tokens; 403 for viewers and non-holders; site_settings untouched"
    requirement: "LOY-21"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltySettingsTest.php (3 invalid-value tests, boundary and Arabic 422 tests, 401 and 403 tests, test_the_public_site_settings_table_is_untouched)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Three loyalty.* permissions seeded in no preset, shown as one loyalty group, with every preset role getting 403 and a route-gate contract over all loyalty routes"
    requirement: "LOY-20"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyPermissionsTest.php (6 tests); SeederTest, PermissionsGroupedTest, CmsAccessControlTest, RolePresetsTest (untouched)"
        status: pass
    human_judgment: false

duration: 40min
completed: 2026-10-04
status: complete
---

# Phase 10 Plan 04: Loyalty Settings API and Permissions Summary

**Staff configure the six loyalty program values through `GET/PUT /api/cms/loyalty/settings` (audited, present-key upsert under a row lock) behind three new `loyalty.*` permissions that no preset holds, with a dynamic route-gate contract test for every later loyalty route.**

## Performance

- **Duration:** about 40 min
- **Completed:** 2026-10-04
- **Tasks:** 3 (RED specs, GREEN endpoints, permissions and re-pins)
- **Files:** 17 in the code commit (7 created, 10 modified)

## Test counts

- Task 1 RED confirmed: 22 tests, 20 failing or erroring (the two passing were the "no role holds a loyalty permission" check and the still-empty `api/loyalty` guest contract).
- Targeted run (settings, permissions, SeederTest, PermissionsGroupedTest, CmsAccessControlTest, RolePresetsTest, PermissionGuideAccuracyTest): 68 passed.
- **Full suite `php artisan test`: 2738 tests, 2738 passed, 16668 assertions, exit 0 (338 s)** before the code commit. 10-03 ended at 2716, so this plan adds 22 tests.
- Pint clean on all new files.

## Permission catalogue (Task 3 measurement)

| | Before (post-Phase-9.1) | After |
|---|---|---|
| Permissions (guard users) | N = 30 | 33 |
| `GET /api/permissions` groups | G = 13 | 14 |

Phase 9.1 (`1dac2b9`) added no permission, so the baseline matched the plan's expectation exactly. The seeder diff is append-only (0 removed lines) and `RolePresetsTest` is byte-unchanged and green.

## Accomplishments

- `UpdateLoyaltySettingsAction` selects the `singleton = 1` row `FOR UPDATE`; with no row it inserts inside a savepoint and, on a lost first-insert race (`UniqueConstraintViolationException`), re-selects under the lock. It fills only the submitted keys, always stamps `updated_by`, and saves only when dirty so unchanged saves write no audit row.
- `UpdateLoyaltySettingsRequest` enforces the column-matched bounds with rule names already mapped in `BaseRequest::messages()`; a 422 in Arabic carries Arabic `message` and `errors`.
- `LoyaltySettingsResource` wraps `LoyaltyProgram` (no queries): rates as stored decimal strings (`'1.2500'`, `'50.00'`) or null, `program` from `capabilities()`.
- Route block `cms/loyalty` placed after the existing staff blocks; GET admits `loyalty.view|loyalty.manage`, PUT needs `loyalty.manage`.
- `LoyaltyPermissionsTest` checks seeding, no role holding any `loyalty.*`, the picker group, the 403 matrix over all seven presets plus a `reports.view`-only user (super_admin gets 200), and walks the router so every `api/cms/loyalty` route needs `auth:users` plus a `loyalty.*`/`cms.restore`/`cms.purge` permission and every `api/loyalty` route needs `auth:guests`.

## Task Commits

By project policy all code landed in one commit after the full suite passed (RED specs were run and confirmed failing first):

1. **Tasks 1-3: feat(10-04)** - `2f89639`

Metadata commit follows as `docs(10-04)`.

## Decisions Made

See `key-decisions` above.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] A fourth catalogue pin broke: `SeederTest::test_every_seeded_permission_is_reachable_through_some_role`**
- **Found during:** Task 3 (first targeted run)
- **Issue:** the test fails for any permission granted by no preset; the plan listed only the count pins and `$notYetBuilt`, but this test also encodes the "assigned per account" exemption list (`staff.manage`, `pricing.edit`, `reports.view`, `night_audit.manage`).
- **Fix:** added `loyalty.view`, `loyalty.manage`, `loyalty.adjust` to that exemption list with a Phase 10 comment. `SeederTest.php` was already in the plan's file list, so no new file is touched.
- **Files modified:** `backend/tests/Feature/SeederTest.php`
- **Commit:** 2f89639

### Drift from the plan's stated baselines (project override 10)

- `CmsAccessControlTest::$notYetBuilt` was `[]` at execution, not `['pricing.edit']` as the plan's interfaces note said (Phase 9.1 consumed `pricing.edit`). It is now `['loyalty.adjust']`, with the comment that plan 10-07 removes it.
- `SeederTest::test_all_30_permissions_seeded` was renamed `test_all_33_permissions_seeded`; no other test referenced the name.
- Permission count 30 and group count 13 matched the plan. No route-count pin exists in the suite, so none needed re-pinning.

Beyond the plan's list, extra tests were added: boundary acceptance (`9999.9999`, `999999.9999`, 120, 365, `100000000`, `100`, and `0.01`/1 minimums), `adjust`-only and reception 403 on PUT, `expiry_months` null and `1.5`, and `redeem_value_usd` with five decimals.

**Total deviations:** 1 auto-fixed pin, 2 baseline drifts noted, no scope change.

### Shared-file staging (project override, not a plan deviation)

`.planning/STATE.md` carries the concurrent session's uncommitted Phase 9.1 paragraph. Only this plan's hunks are staged in the metadata commit; the foreign paragraph stays unstaged in the working tree. No `backend/` file had foreign edits, so the code commit staged whole files.

## Issues Encountered

None. A stray `rg`-fallback hang in one exploratory shell grep was moved to the background and discarded.

## Known Stubs

None. The settings are fully wired to `loyalty_settings`.

## Threat Flags

None beyond the plan's register. T-10-14 (403 matrix and route middleware), T-10-15 (422 bounds, null cap means off), T-10-16 (`updated_by` plus activity log old/new asserted) and T-10-17 (dedicated table, `site_settings` count unchanged, no `SiteSetting`/`Cache::` in the action or service) are mitigated and tested.

## Requirements

- **LOY-01 ticked:** staff read and update the six values, audited.
- **LOY-20 left unticked (partial):** the permissions are seeded, grouped and no preset changed, but `loyalty.adjust` is enforced by no route until plan 10-07 (listed in `$notYetBuilt`), and rewards/guest/report routes arrive later.
- LOY-02, LOY-21 and LOY-22 were already ticked by earlier plans; this plan adds tests toward them but changes no checkbox. LOY-21 (docs, Postman, tree) completes in 10-15.

## Next Phase Readiness

Later plans add routes to the `cms/loyalty` block under `auth:users` with a `loyalty.*` permission middleware and to an `auth:guests` block under `api/loyalty`; `LoyaltyPermissionsTest` checks them automatically. Plan 10-07 must remove `loyalty.adjust` from `CmsAccessControlTest::$notYetBuilt`.

## Self-Check: PASSED

- All 7 created files and 10 modified files exist and are in commit `2f89639`.
- Commit `2f89639` exists on `main`; nothing pushed.
- Full suite exit 0 (2738 tests) before the code commit.
