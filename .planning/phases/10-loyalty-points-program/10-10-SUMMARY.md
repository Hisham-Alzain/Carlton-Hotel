---
phase: 10-loyalty-points-program
plan: 10
subsystem: api
tags: [laravel, loyalty, bcmath, pricing, preview, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-02 LoyaltyMath/LoyaltyProgram and the eight loyalty exceptions; 10-03 LoyaltyLedger::available; 10-09 guest-owned LoyaltyVoucher rows and the guest loyalty route block"
provides:
  - "PriceLoyaltyRedemptionAction: the single read-only booking discount calculator (points or one voucher, Q5/Q12) shared by preview and, in 10-11, booking"
  - "PreviewLoyaltyAction and GET /api/loyalty/preview (auth:guests, throttle:30,1): quote + loyalty discount + net total + usable points + earn estimate, zero writes (LOY-15)"
affects: [10-11 booking with points or voucher, 10-12 cancel reversals, 10-15 guides and Postman]

tech-stack:
  added: []
  patterns:
    - "Read-only pricing action returning ['data' => ..., 'code' => 200]; takes no lock and writes nothing, so the caller owns locking"
    - "Float boundary only at LoyaltyMath::fromQuote: QuoteReservationAction floats become 2dp strings once, every later step is bcmath"
    - "Uniform guest-scoped voucher lookup: unknown, foreign, used, void and expired share one exception with no context"

key-files:
  created:
    - backend/app/Actions/Loyalty/PriceLoyaltyRedemptionAction.php
    - backend/app/Actions/Loyalty/PreviewLoyaltyAction.php
    - backend/app/Http/Requests/Loyalty/LoyaltyPreviewRequest.php
    - backend/app/Http/Resources/Loyalty/LoyaltyPreviewResource.php
    - backend/tests/Unit/Loyalty/PriceLoyaltyRedemptionActionTest.php
    - backend/tests/Feature/Loyalty/LoyaltyPreviewTest.php
  modified:
    - backend/app/Services/Loyalty/LoyaltyAccountService.php
    - backend/app/Http/Controllers/Api/LoyaltyController.php
    - backend/routes/api.php

key-decisions:
  - "Check order is conflict, inactive, below minimum, over cap, insufficient balance (Q5): the cap-limited max_points is what an over-cap error reports; the preview body's max_points is additionally limited by the balance (FA-10.10-3)"
  - "Voucher codes are upper-cased and stripped of all whitespace before the guest-scoped lookup"
  - "program in the preview body is LoyaltyProgram::capabilities() (earning, points_discount, rewards); rates stay on GET /loyalty/account"
  - "No test asserts QuoteReservationAction is git-unmodified (a test depending on working-tree git state is fragile); the plan's verify command carries that gate"

patterns-established:
  - "Preview and booking share one calculator, so the drift test in 10-11 compares identical-input totals rather than reimplementing the rules"

requirements-completed: [LOY-15]

coverage:
  - id: D1
    description: "Pricing rules: points happy path, exact cap edge 11109/11110, minimum, balance, inactive, conflict-first precedence, estimate"
    requirement: "LOY-16"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/PriceLoyaltyRedemptionActionTest.php (points, cap edge, minimum, balance, inactive and conflict tests)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Voucher semantics: discount_voucher min(value, gross), free_night min(daily, gross), room_upgrade 0.00 plus upgrade_requested, five invalid cases uniform with no context, code normalisation, min/cap ignored"
    requirement: "LOY-16"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/PriceLoyaltyRedemptionActionTest.php (voucher and invalid-voucher tests); tests/Feature/Loyalty/LoyaltyPreviewTest.php::test_another_guests_voucher_is_indistinguishable_from_an_unknown_one"
        status: pass
    human_judgment: false
  - id: D3
    description: "GET /api/loyalty/preview contract, post-promo cap base, no writes, no locks, 401/422, domain error codes, throttle:30,1"
    requirement: "LOY-15"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyPreviewTest.php (12 tests); tests/Unit/Loyalty/PriceLoyaltyRedemptionActionTest.php::test_pricing_writes_nothing_and_locks_nothing"
        status: pass
    human_judgment: false
  - id: D4
    description: "Points discount switched off gives max_points null and a loyalty_program_inactive error only when points are sent"
    requirement: "LOY-22"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyPreviewTest.php::test_points_are_refused_while_the_points_discount_is_off; tests/Unit/Loyalty/PriceLoyaltyRedemptionActionTest.php::test_points_while_the_points_discount_is_off_are_inactive_and_none_means_a_null_max"
        status: pass
    human_judgment: false

duration: 25min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 10: Loyalty Redemption Pricing and Booking Preview Summary

**One read-only `PriceLoyaltyRedemptionAction` computes every booking discount (points or one voucher) in exact cents, and `GET /api/loyalty/preview` exposes it with the real quote and zero side effects, so preview and the 10-11 booking cannot drift.**

## Performance

- **Duration:** about 25 min
- **Completed:** 2026-10-05
- **Tasks:** 2 of 2 (Task 1 RED confirmed failing: 37 tests, 0 passing, all "class does not exist" or 404 on the missing route; Task 2 GREEN passed first run)
- **Files:** 9 in the code commit (6 created, 3 modified)

## Counts and pins (re-read at execution; Phase 9.1 commit 1dac2b9 had already landed)

- Targeted run `LoyaltyPreview|PriceLoyaltyRedemption`: 37 tests, 182 assertions (25 in `PriceLoyaltyRedemptionActionTest`, 12 in `LoyaltyPreviewTest`).
- Full suite `php artisan test`: **2896 tests, 17629 assertions, exit 0** (311 s). 10-09 ended at 2859, so this plan adds exactly 37. No permission, seeder, route-count or controller-count pin moved (the new route is one more `auth:guests` GET inside the existing `loyalty` block and no existing pin asserts the guest route count), so no re-pin was needed.
- `QuoteReservationAction.php` and `ReleaseExpiredHoldsAction.php` are byte-unmodified (`git diff --quiet HEAD` exits 0). No phase 9.1 drift affected this plan.

## Accomplishments

- `PriceLoyaltyRedemptionAction::handle(Guest, quote, input)` takes the gross from the post-promo `total_usd` and the daily rate from `daily_rate_usd` through `LoyaltyMath::fromQuote`, then applies conflict, inactive, minimum, cap and balance checks in that order. The cap edge is exact: with a 33.33% cap on 333.33 the cap is 111.09, 11109 points pass and 11110 throw `loyalty_over_cap` with `max_points` 11109.
- Vouchers are looked up by code AND `guest_id`. Unknown, another guest's, used, void and expired (`expires_at` at or before now) all throw the same `LoyaltyVoucherInvalidException` with empty context, and the feature test compares the foreign and unknown responses field by field.
- `net_total_usd` is gross minus discount floored at zero; `max_points` is `min(available, maxPointsForCap)` when paying with points is on and null otherwise; `points_earnable_estimate` is `pointsForSpend(net, rate)` when earning is on and null otherwise.
- `PreviewLoyaltyAction` resolves the room type, calls `QuoteReservationAction` unchanged, then `$this->priceRedemption`, and adds the `quote` block. `LoyaltyPreviewRequest` mirrors the `StoreReservationRequest` booking rules and adds `loyalty_points` (int 1..100000000) and `voucher_code` (max 16). The client cannot supply a discount or total: unknown query keys are ignored by `validated()` (tested with `discount_usd`, `total_usd`, `points_discount_usd`).
- Purity: a row-count check over all seven loyalty tables, reservations and `promo_codes.used_count` is unchanged after five preview calls, and `lockedSelects()` around the action is empty.

## Task Commits

Both tasks landed in one code commit by project policy (never commit a red state; RED specs were written and confirmed failing first):

1. **Tasks 1-2: feat(10-10)** - `b3f5f59`

Metadata commit: see the `docs(10-10)` commit that follows.

## Decisions Made

See `key-decisions`. FA-10.10-1 (read-only half is its own action), FA-10.10-2 (same field names as `POST /reservations`) and FA-10.10-3 (over-cap `max_points` is cap-limited, preview `max_points` is also balance-limited) stand as written.

## Deviations from Plan

### Plan wording adjusted

- **Error context location:** the plan's must_haves describe context in the error body loosely; the handler (bootstrap/app.php) returns machine details under `context`, so the tests assert `context.max_points`, `context.available_points` and so on, and `context` is null for the voucher error.
- **QuoteReservationAction unmodified check:** the plan allows asserting this "in the test or in the verify step"; it lives in the verify command only (see key-decisions).
- **Requirements:** LOY-15 is fully delivered here and is ticked. LOY-16 is only half delivered (booking with points/voucher is 10-11) and is NOT ticked. LOY-22 was already ticked by an earlier plan. The traceability-table row for LOY-15 could not be matched by `requirements.mark-complete` (table format has no per-requirement row), so only the checkbox changed.

### Auto-fixed Issues

None.

## Issues Encountered

None.

## Known Stubs

None.

## Threat Flags

None. T-10-37 (only `loyalty_points` and `voucher_code` are read; extra keys tested as ignored), T-10-38 (guest-scoped lookup, one uniform error with no context, `throttle:30,1` asserted on the route), T-10-39 (cap floored to cents, exact edge test at 11109/11110) and T-10-40 (read-only actions, no `lockForUpdate`, row-count and lock-recording tests) are mitigated and tested.

## Acceptance gates (all clean)

- `(float)` / `round(` in the two new actions: none. `lockForUpdate` in either: 0.
- `PriceLoyaltyRedemptionAction` is referenced in `app/` by `PreviewLoyaltyAction` (10-11 adds `CreateReservationAction`).
- Pint passes on all new and edited PHP files.

## Next Phase Readiness

10-11 calls `PriceLoyaltyRedemptionAction::handle($guest, $quote, $input)` inside its guest lock with the same `loyalty_points` / `voucher_code` names and can assert booking net equals preview net for identical inputs. The calculator returns the voucher model, `points_redeemed` and both discount figures, which is everything `ApplyLoyaltyToReservationAction` needs to persist.

## Self-Check: PASSED

- All 6 created and 3 modified files exist and are in commit `b3f5f59`; nothing pushed; foreign files (`Carlton-hotel-s/`, `backend.zip`) untouched.
- Full suite exit 0 (2896 tests) before the code commit.
