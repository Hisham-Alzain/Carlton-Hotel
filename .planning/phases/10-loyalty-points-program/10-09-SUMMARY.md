---
phase: 10-loyalty-points-program
plan: 09
subsystem: api
tags: [laravel, loyalty, vouchers, idempotency, fifo, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-03 LoyaltyLedger consume/record; 10-06 guest loyalty route block and filtered listing shape; 10-07 IdempotentWrite + ReadsIdempotencyKey pattern; 10-08 LoyaltyReward catalog"
provides:
  - "POST /api/loyalty/rewards/{reward}/redeem: idempotent, transactional reward -> voucher redemption, FIFO spend (LOY-13)"
  - "GET /api/loyalty/vouchers: the caller's own vouchers, newest first, filter by status/type/points_spent (LOY-14)"
  - "LoyaltyVoucher contract shape (LoyaltyVoucherResource) and LOY- voucher codes"
affects: [10-10 preview/apply voucher, 10-11 booking with voucher, 10-12 cancel reversals, 10-15 guides and Postman]

tech-stack:
  added: []
  patterns:
    - "Reward re-read under the guest lock inside the write callback, so a stale route-bound model cannot redeem a just-deactivated reward"
    - "Replay finds the voucher through the redeem ledger entry's idempotency key (ledger is the idempotency store, no key column on vouchers)"

key-files:
  created:
    - backend/app/Actions/Loyalty/RedeemRewardAction.php
    - backend/app/Http/Requests/Loyalty/RedeemLoyaltyRewardRequest.php
    - backend/app/Services/Loyalty/LoyaltyVoucherService.php
    - backend/app/Filters/LoyaltyVoucherFilter.php
    - backend/app/Http/Resources/Loyalty/LoyaltyVoucherResource.php
    - backend/tests/Feature/Loyalty/LoyaltyRedeemTest.php
    - backend/tests/Unit/Loyalty/RedeemRewardActionTest.php
  modified:
    - backend/app/Http/Controllers/Api/LoyaltyRewardController.php
    - backend/app/Http/Controllers/Api/LoyaltyController.php
    - backend/routes/api.php
    - backend/lang/en/custom.php
    - backend/lang/ar/custom.php
    - backend/lang/fr/custom.php
    - backend/lang/tr/custom.php
    - backend/lang/es/custom.php

key-decisions:
  - "Voucher expiry is HotelClock::today()->addDays(voucher_valid_days)->endOfDay() converted to UTC (Q13, FA-10.09-1)"
  - "The redeem ledger entry carries no source (a redeem has no originating batch source; the column is nullable)"
  - "reward_name is returned as the whole AR/EN locale map, consistent with the rewards catalog resource"

patterns-established:
  - "Atomicity of a multi-step loyalty write is tested by throwing from a LoyaltyLedgerEntry::creating listener (LoyaltyLedger is final, so it cannot be partial-mocked)"

requirements-completed: [LOY-13, LOY-14]

coverage:
  - id: D1
    description: "Redeem spends FIFO, issues a LOY- voucher with snapshot and hotel-local end-of-day expiry; one redeem entry with allocations"
    requirement: "LOY-13"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyRedeemTest.php::test_redeeming_a_voucher_spends_fifo_and_issues_the_voucher, test_free_night_and_room_upgrade_vouchers_have_no_value"
        status: pass
    human_judgment: false
  - id: D2
    description: "Replay 200 same voucher / other reward 409 / missing key 422; refusals (insufficient, inactive, trashed); no min-redeem or settings dependency; atomic rollback; guest->batches lock order; throttle"
    requirement: "LOY-13"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyRedeemTest.php (replay, conflict, key, insufficient, inactive, trashed, settings, atomicity, locks, throttle tests)"
        status: pass
      - kind: unit
        ref: "tests/Unit/Loyalty/RedeemRewardActionTest.php"
        status: pass
    human_judgment: false
  - id: D3
    description: "Voucher listing scoped to the caller, newest first, filters, int cast 422, 401 for no token and staff token"
    requirement: "LOY-14"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyRedeemTest.php::test_the_voucher_list_is_scoped_to_the_caller_newest_first_with_every_key, test_a_used_voucher_shows_its_reservation, test_the_voucher_list_filters_by_status_type_and_points, test_a_non_integer_points_filter_is_422, test_redeem_and_vouchers_need_a_guest_token"
        status: pass
    human_judgment: false

duration: 25min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 09: Redeem Into Voucher Summary

**Guests turn catalog rewards into LOY-XXXXXXXX vouchers exactly once per Idempotency-Key, spending points FIFO inside one transaction under the guest lock, and list only their own vouchers.**

## Performance

- **Duration:** about 25 min
- **Completed:** 2026-10-05
- **Tasks:** 2 of 2 (Task 1 RED confirmed failing: 16 failures and 2 errors, all 404 / missing class; the single early pass was the trashed-reward 404, trivially true before the route existed)
- **Files:** 15 in the code commit (7 created, 8 modified)

## Counts and pins (re-read at execution; Phase 9.1 commit 1dac2b9 had already landed)

- Plan verify filter (`LoyaltyRedeem|RedeemRewardAction|LoyaltyReward|LoyaltyAccount|LoyaltyPermissions|LoyaltyLocaleTest|ValidationMessageLocalizationTest`): 119 tests, 1254 assertions, pass.
- Full suite `php artisan test`: **2859 tests, 17444 assertions, exit 0** (315 s). Previous plan ended at 2840, so this plan adds exactly 19 tests (17 in `LoyaltyRedeemTest`, 2 in `RedeemRewardActionTest`). No permission, seeder, route-count or controller-count pin changed, so no re-pin was needed.
- `route:list --path=api/loyalty -v`: the redeem route carries `Authenticate:guests` and `ThrottleRequests:30,1`; `GET /vouchers` carries `Authenticate:guests`. `CreateReservationAction.php` is untouched (`git diff --quiet` exits 0).

## Accomplishments

- `RedeemRewardAction` locks the guest row, then `IdempotentWrite::run` on `redeem:reward:{guest_id}:{client_key}`. The write re-reads the reward (inactive or trashed gives `loyalty_reward_unavailable`), calls `LoyaltyLedger::consume` (insufficient gives `loyalty_insufficient_points` with `available_points` / `requested_points` in `context`), creates the voucher (`LOY-` + 8 Crockford chars, regenerated on collision, snapshot type / reward_name / value_usd / points_spent, status active) and records one `redeem` entry (-cost, voucher_id) with the allocations. Replay finds the voucher through the ledger entry and answers 200; a different reward under the same key is a 409 `idempotency_conflict`.
- `value_usd` is the reward's `discount_usd` for `discount_voucher` and null for `free_night` / `room_upgrade`. Catalog redeem ignores the min-redeem setting and works with no settings row (Q4, Q5).
- `LoyaltyVoucherService::indexForGuest` is guest-scoped by `guest_id` (no param can widen it), newest first, filtered by `LoyaltyVoucherFilter` (status eq/in, type eq/in, `points_spent` gte/lte with an int cast so `abc` gives 422).
- `LoyaltyVoucherResource` exposes uuid, code, type, type_label, reward_name (locale map), value_usd, points_spent, status, expires_at, used_at and `reservation{uuid, booking_code}|null`; never an id.
- Lang key `loyalty_reward_redeemed` appended at the end of `messages` in all five locales.

## Task Commits

Both tasks landed in one code commit by project policy (never commit a red state; RED spec written and confirmed failing first):

1. **Tasks 1-2: feat(10-09)** - `186183b`

Metadata commit: see the `docs(10-09)` commit that follows.

## Decisions Made

See `key-decisions`. FA-10.09-1 (hotel-local end-of-day expiry) stands as written. FA-10.09-2 stands: true concurrent redeem with one key is MySQL-only; on SQLite the guest lock is asserted via `RecordsRowLocks` (guests before loyalty_earn_batches) and the unique ledger key is the backstop.

## Deviations from Plan

### Plan wording adjusted

- **Missing key:** the plan says 422 `idempotency_key_required`. The shared `ReadsIdempotencyKey` pattern (already pinned by `LoyaltyAdjustTest`) answers `error_code: validation_failed` with `errors.idempotency_key.0 = custom.errors.idempotency_key_required`; the redeem follows that existing contract rather than introducing a second error shape.
- **Insufficient-points context:** the envelope carries machine details under `context` (bootstrap/app.php handler), not `data`; the test asserts `context.available_points` / `context.requested_points`.
- **Atomicity test:** the plan says `partialMock(LoyaltyLedger::class)` with `record()` throwing. `LoyaltyLedger` is `final` and cannot be mocked, so the test throws from a `LoyaltyLedgerEntry::creating` listener for `redeem` entries. This still fires after `consume()` and the voucher insert, which is the point of the test.
- **Redeem entry `source`:** none set (the nullable column snapshots a batch source; a redeem has no originating batch).
- Pint was run on this plan's new and edited PHP files and passed.

### Auto-fixed Issues

None.

## Issues Encountered

None beyond the test-side `context` vs `data` slip fixed before GREEN completed.

## Known Stubs

None.

## Threat Flags

None. T-10-33 (double-submit: required key, guest lock, IdempotentWrite, unique ledger key, replay test), T-10-34 (guest -> batches lock order asserted), T-10-35 (listing scoped to `auth('guests')`, staff token gets 401, 32^8 code space) and T-10-36 (`throttle:30,1` asserted on the route) are mitigated and tested.

## Next Phase Readiness

10-10 can price a voucher against a booking: vouchers carry `status`, `expires_at`, `value_usd`, `type`, `reservation_id` / `used_at` (null until applied) and the guest-scoped lookup shape. The guest `loyalty` block now holds account, ledger, rewards, redeem and vouchers; preview is still to come.

## Self-Check: PASSED

- All 7 created and 8 modified files exist and are in commit `186183b`; nothing pushed; foreign files (`Carlton-hotel-s/`, `backend.zip`) untouched.
- Full suite exit 0 (2859 tests) before the code commit.
