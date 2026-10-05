---
phase: 10-loyalty-points-program
plan: 08
subsystem: api
tags: [laravel, loyalty, rewards, recycle-bin, translatable, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-01 LoyaltyReward model, factory, enum, loyalty_rewards table, RecycleBinRetentionTest pin; 10-04 loyalty.* permissions and the cms/loyalty route block"
provides:
  - "Staff rewards CRUD under /api/cms/loyalty/rewards with AR/EN locale maps (LOY-11)"
  - "Recycle bin for rewards inside the existing cms.restore / cms.purge groups"
  - "Guest catalog GET /api/loyalty/rewards (active, non-trashed, sort_order then id) with no program settings required (LOY-12)"
  - "LoyaltyRewardType::label() and reward_types / loyalty_discount_usd_voucher_only strings in five locales"
affects: [10-09 redeem into voucher, 10-15 guides and Postman]

tech-stack:
  added: []
  patterns:
    - "Cross-field rule judged in FormRequest::after() on the effective post-update values (sent input, else the route-bound model)"
    - "Bin routes for a non-cms model reuse the cms.restore/cms.purge permission groups instead of the feature permission"

key-files:
  created:
    - backend/app/Services/Loyalty/LoyaltyRewardService.php
    - backend/app/Filters/LoyaltyRewardFilter.php
    - backend/app/Http/Requests/Loyalty/CreateLoyaltyRewardRequest.php
    - backend/app/Http/Requests/Loyalty/UpdateLoyaltyRewardRequest.php
    - backend/app/Http/Resources/Loyalty/LoyaltyRewardResource.php
    - backend/app/Http/Controllers/Admin/LoyaltyRewardController.php
    - backend/app/Http/Controllers/Api/LoyaltyRewardController.php
    - backend/tests/Feature/Loyalty/LoyaltyRewardTest.php
  modified:
    - backend/app/Enums/LoyaltyRewardType.php
    - backend/routes/api.php
    - backend/lang/en/custom.php
    - backend/lang/ar/custom.php
    - backend/lang/fr/custom.php
    - backend/lang/tr/custom.php
    - backend/lang/es/custom.php

key-decisions:
  - "Reward bin routes live in the three existing cms bin groups, so loyalty.manage alone cannot restore or purge (T-10-31)"
  - "discount_usd/type pairing is an after() hook, not a field rule, so an update that only changes type is judged against the stored discount"
  - "LoyaltyRewardService::store defaults is_active=true and sort_order=0 so the create response never echoes null for omitted columns"

patterns-established:
  - "Update requests that need the bound model read $this->route('reward') defensively (instanceof check) so ValidationMessageLocalizationTest can instantiate them with no route"

requirements-completed: [LOY-11, LOY-12]

coverage:
  - id: D1
    description: "Staff CRUD with AR/EN maps, type-dependent discount rules, 401/403/422"
    requirement: "LOY-11"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyRewardTest.php (create, read, update, 401, 403, 422 groups)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Recycle bin: trashed, restore, force; loyalty.manage-only token gets 403; purge keeps voucher snapshot"
    requirement: "LOY-11"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyRewardTest.php::test_the_bin_lists_restores_and_purges, test_a_loyalty_manager_without_cms_bin_rights_cannot_use_the_bin, test_purging_an_expired_reward_keeps_the_voucher_snapshot"
        status: pass
    human_judgment: false
  - id: D3
    description: "Guest catalog lists only active non-trashed rewards in order, works with no settings row"
    requirement: "LOY-12"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyRewardTest.php::test_guest_catalog_lists_active_rewards_in_order_without_a_program_configured, test_guest_catalog_requires_a_guest_token"
        status: pass
    human_judgment: false

duration: 25min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 08: Rewards Catalog Summary

**Staff-managed AR/EN rewards catalog (three reward types, recycle bin under the existing cms bin permissions) plus a guest listing of active rewards that works before the program is configured.**

## Performance

- **Duration:** about 25 min
- **Completed:** 2026-10-05
- **Tasks:** 2 of 2 (Task 1 RED confirmed failing: 23 failures and 2 errors, all 404 or missing helper; Task 2 GREEN)
- **Files:** 15 in the code commit (8 created, 7 modified)

## Counts and pins (re-read at execution; Phase 9.1 commit 1dac2b9 had already landed)

- `RecycleBinRetentionTest::SOFT_DELETABLE` already held `LoyaltyReward::class` from 10-01 (19 entries including SiteSetting); step 8 was verify-only and the file is untouched.
- Route-file comment "17 of the 18 soft-deletable models" became "18 of the 19" (the pin list has 19 entries, SiteSetting is the one without bin routes).
- Plan verify filter (`LoyaltyRewardTest|RecycleBinRetentionTest|PermissionGuideAccuracyTest|ValidationMessageLocalizationTest|LoyaltyPermissions|LoyaltyLocaleTest|CmsAccessControlTest`): 117 tests, 1153 assertions, pass.
- Full suite `php artisan test`: **2840 tests, 17321 assertions, exit 0** (309 s). Previous plan ended at 2815, so this plan adds 25 tests (all in `LoyaltyRewardTest`). No permission, seeder or controller-count pin changed.
- Routes: `api/cms/loyalty/rewards` lists 8 (list, show, store, update, destroy, trashed, restore, force); `api/loyalty/rewards` lists the guest GET.

## Accomplishments

- Full CRUD on the house base pair (`BaseCRUDController` + `HandlesRecycleBin`, FaqController shape); `name` required in en and ar, `description` optional, max lengths 150 / 1000.
- `discount_usd` is required for `discount_voucher` and answers 422 `custom.validation.loyalty_discount_usd_voucher_only` for the other two types. On update the pairing is judged on the effective type and discount (sent value, else stored), so `PUT {"type":"free_night"}` on a voucher is refused until `discount_usd: null` is sent, and the reverse switch demands a discount.
- Bin routes are declared inside the cms bin groups before any `/{reward}` route; a `loyalty.manage`-only token gets 403 on trashed, restore and force.
- Guest catalog `indexPublic` filters `is_active`, relies on the model soft-delete scope, orders by `sort_order` then `id`, and reads no program settings.
- `cms:purge-bin` force-deletes an expired-retention reward and the voucher keeps its snapshot with `loyalty_reward_id` null (Pitfall 12).

## Task Commits

Both tasks landed in one code commit by project policy (never commit a red state; RED spec written and confirmed failing first):

1. **Tasks 1-2: feat(10-08)** - `db1fb10`

Metadata commit: see the `docs(10-08)` commit that follows.

## Decisions Made

See `key-decisions`. FA-10.08-1 (visibility is `is_active`, Q18 idiom) and FA-10.08-2 (editing a reward never touches issued vouchers, because vouchers snapshot type, name and value) stand as written; 10-15 documents the second.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical functionality] Create response echoed null for omitted defaults**
- **Found during:** Task 2
- **Issue:** `BaseService::store` returns the model without a refresh, so a create that omits `is_active` / `sort_order` serialised both as `null` instead of the column defaults.
- **Fix:** `LoyaltyRewardService::store` merges `is_active => true, sort_order => 0` under the validated data.
- **Files modified:** `backend/app/Services/Loyalty/LoyaltyRewardService.php`
- **Commit:** `db1fb10`

### Plan wording adjusted

- The plan says DELETE answers 200; every bin-backed CRUD controller in this codebase answers **204** through `destroyResponse()`, so the test and the contract follow 204 (identical to FaqController).
- The route parameter is `{reward}` (plan text) and the controller variable is `$reward`; the tests address rewards by uuid.
- Pint was run on the four new files it flagged (it re-aligned `=>` and operator spacing in the two requests, imports in the test, a blank line in the service). The older sibling classes keep their hand-aligned style; only this plan's new files were reformatted.

## Issues Encountered

None. The RED-first spec failed for the intended reason (404 on every route) apart from one test-side slip (a voucher `reward_name` is an array cast, not Spatie-translatable), fixed before GREEN.

## Known Stubs

None.

## Threat Flags

None. T-10-29 to T-10-32 are mitigated and tested: loyalty.manage on writes (403 test), FormRequest bounds plus the effective-type hook (422 tests), bin routes reuse cms.restore/cms.purge (403 test for a manage-only token), and the guest catalog filters inactive and trashed rows (test).

## Next Phase Readiness

10-09 can read rewards through `LoyaltyReward` / `LoyaltyRewardService`, snapshot `type`, `name` and `discount_usd` onto the voucher, and rely on the `loyalty_reward_unavailable` error for inactive or trashed rewards. The guest `loyalty` route block now holds account, ledger and rewards; vouchers and redeem are still to come.

## Self-Check: PASSED

- All 8 created and 7 modified files exist and are in commit `db1fb10`; nothing pushed.
- Full suite exit 0 (2840 tests) before the code commit.
