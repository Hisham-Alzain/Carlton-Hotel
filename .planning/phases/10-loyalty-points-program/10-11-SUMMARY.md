---
phase: 10-loyalty-points-program
plan: 11
subsystem: api
tags: [laravel, loyalty, booking, idempotency, pessimistic-locks, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-03 LoyaltyLedger (consume/record), 10-09 guest vouchers, 10-10 PriceLoyaltyRedemptionAction shared with the preview"
provides:
  - "POST /api/reservations accepts loyalty_points or voucher_code, atomically and idempotently (LOY-16)"
  - "ApplyLoyaltyToReservationAction: the persisting half (FIFO consume + redeem entry, or voucher use, plus the application row)"
  - "ReservationResource.loyalty block on every reservation read"
affects: [10-12 cancel reversals (reads the application row and redeem entry), 10-14 earn on net total, 10-15 guides and Postman]

tech-stack:
  added: []
  patterns:
    - "Replay-before-availability under the room_type then guest locks: an identical retry answers 200 from the stored application, a differing one 409 idempotency_conflict"
    - "Guest lock taken only when loyalty fields are present, so a plain booking has the same lock footprint as before"

key-files:
  created:
    - backend/app/Actions/Loyalty/ApplyLoyaltyToReservationAction.php
    - backend/tests/Feature/Loyalty/LoyaltyBookingTest.php
    - backend/tests/Unit/Loyalty/ApplyLoyaltyToReservationActionTest.php
  modified:
    - backend/app/Actions/Booking/CreateReservationAction.php
    - backend/app/Http/Requests/Booking/StoreReservationRequest.php
    - backend/app/Http/Resources/Booking/ReservationResource.php
    - backend/app/Services/Booking/ReservationService.php

key-decisions:
  - "Replay compares stored facts (first room's room type, dates, payment method, promo id, points, voucher code) instead of a request hash (FA-10.11-1); the promo is resolved by code only, so a replay still matches after the promo is exhausted"
  - "voucher_code is normalised twice on purpose: in the request (upper-case, no whitespace) and, for the replay comparison, again in CreateReservationAction using the same rule as PriceLoyaltyRedemptionAction"
  - "A loyalty booking with no idempotency_key reaching the action is a LogicException (programming error); the request layer makes it unreachable from HTTP"
  - "ReservationResource guards the voucher with relationLoaded so the resource never queries"

patterns-established:
  - "Booking-time loyalty = price (read-only action) then apply (write action) inside the one booking transaction, so a failed availability or refusal rolls every write back"

requirements-completed: [LOY-16]

coverage:
  - id: D1
    description: "Booking with points: net total, redeem entry (key redeem:reservation:{id}, discount_usd), application row, loyalty block"
    requirement: "LOY-16"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyBookingTest.php::test_booking_with_points_reduces_the_total_and_records_everything"
        status: pass
    human_judgment: false
  - id: D2
    description: "Booking with a discount, free-night and room-upgrade voucher: voucher used with reservation_id/used_at, upgrade_requested flag, code normalisation"
    requirement: "LOY-16"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyBookingTest.php (voucher happy, free night, room upgrade, case/space tests)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Preview equals booking for points, each voucher type, promo + points and no loyalty"
    requirement: "LOY-16"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyBookingTest.php::test_the_booking_total_equals_the_preview_for_identical_inputs"
        status: pass
    human_judgment: false
  - id: D4
    description: "Idempotency: replay 200 with no new rows (also after the last room is taken, after the voucher is used, promo not double counted), 409 on any differing field, key required with loyalty fields, key ignored without them, per-guest keys"
    requirement: "LOY-16"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyBookingTest.php (replay, conflict, key rules and per-guest tests)"
        status: pass
    human_judgment: false
  - id: D5
    description: "Atomicity: every refusal and a no-availability booking leave reservations, promo used_count, vouchers, ledger and batches unchanged"
    requirement: "LOY-16"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyBookingTest.php::test_refusals_roll_everything_back, ::test_no_availability_spends_no_points"
        status: pass
    human_judgment: false
  - id: D6
    description: "Staff and public OTP bookings never apply loyalty (Q17); apply refuses pending_verification and held reservations (M-5); a voucher used or expired between pricing and apply is refused"
    requirement: "LOY-16"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyBookingTest.php (staff, OTP tests); tests/Unit/Loyalty/ApplyLoyaltyToReservationActionTest.php (6 tests)"
        status: pass
    human_judgment: false
  - id: D7
    description: "Lock order room_types, guests, loyalty_earn_batches (points) and room_types, guests, loyalty_vouchers (voucher); a plain booking takes no guest or loyalty lock; the reservation list stays constant-query"
    requirement: "LOY-16"
    verification:
      - kind: integration
        ref: "tests/Feature/Loyalty/LoyaltyBookingTest.php (three lock tests, ::test_the_guest_list_does_not_query_per_reservation)"
        status: pass
    human_judgment: false

duration: 45min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 11: Booking with Loyalty Points or a Voucher Summary

**A guest pays part of a booking with free-form points or one voucher on `POST /api/reservations` in one atomic, `Idempotency-Key`-guarded transaction whose total always equals the 10-10 preview, and every reservation read shows what was applied.**

## Performance

- **Duration:** about 45 min
- **Completed:** 2026-10-05
- **Tasks:** 2 of 2 (Task 1 RED confirmed failing: 34 tests, 9 passing only because they asserted refusals/absence, 21 failures and 4 errors on the missing class and unmodified endpoint; Task 2 GREEN passed 34/34 on the first run)
- **Files:** 7 in the code commit (3 created, 4 modified)

## Counts and pins (re-read at execution; Phase 9.1 commit 1dac2b9 had already landed)

- Targeted run `LoyaltyBooking|ApplyLoyaltyToReservation`: 34 tests (28 in `LoyaltyBookingTest`, 6 in `ApplyLoyaltyToReservationActionTest`).
- Booking regression set from the plan's verify (`LoyaltyBooking|ApplyLoyaltyToReservation|LoyaltyPreview|ReservationTest|GuestBookingTest|ConcurrencyTest|RoomAssignmentAtBookingTest|CheckOutTest|ValidationMessageLocalizationTest`): 170 tests green.
- Full suite `php artisan test`: **2930 tests, 17842 assertions, exit 0** (311 s). 10-10 ended at 2896, so this plan adds exactly 34. No query-count, route-count, permission or controller-count pin moved from the `$with` addition, so no re-pin was needed (the plan's step 6 had nothing to do).
- `QuoteReservationAction.php` and `ReleaseExpiredHoldsAction.php` are byte-unmodified (`git diff --quiet HEAD` exits 0). Phase 9.1 did not drift anything this plan touches.

## Accomplishments

- `ApplyLoyaltyToReservationAction::handle(Reservation, Guest $lockedGuest, array $redemption, string $key)` refuses a `pending_verification` reservation or any non-null `hold_expires_at` (`ReservationStateException`), consumes points FIFO and writes one `redeem` entry (`-points`, `reservation_id`, `discount_usd`, key `redeem:reservation:{reservation_id}`), re-locks and re-checks the voucher (active, unexpired, owned) before marking it used, then writes the `applied` application row.
- `CreateReservationAction` locks `room_types`, then the guest row only when `loyalty_points` or `voucher_code` is present, looks up `(guest_id, idempotency_key)` and answers an identical retry with 200 and the stored reservation before the availability check; any difference throws `IdempotencyConflictException` (409). Otherwise it prices through `PriceLoyaltyRedemptionAction` and writes `total_usd = net_total_usd` in `Reservation::create` (no post-create update, so the activity log carries the real total).
- `StoreReservationRequest` adds `loyalty_points` (int 1..100000000), `voucher_code` (max 16, normalised upper-case/no whitespace) and `idempotency_key` (`required_with` them). No money field is accepted; `discount_usd`, `total_usd` and `points_discount_usd` in the body are ignored (tested).
- `ReservationResource.loyalty` is `{points_redeemed, points_discount_usd, voucher{code,type}|null, voucher_discount_usd, upgrade_requested, status}` or `null`, behind `whenLoaded('loyaltyApplication')`; `ReservationService::$with` and the action's final load carry `loyaltyApplication.voucher`, and the guest list issues the same number of queries for 1 and 5 reservations.
- `storeAsGuest()` and `adminStore()` are untouched: their explicit arrays carry no loyalty keys, and the tests prove extra `loyalty_points`/`voucher_code` on the staff and public OTP endpoints write no application row and spend no points.

## Task Commits

Both tasks landed in one code commit by project policy (never commit a red state; RED specs were written and confirmed failing first):

1. **Tasks 1-2: feat(10-11)** - `e3f6e7f`

Metadata commit: see the `docs(10-11)` commit that follows.

## Decisions Made

See `key-decisions`. FA-10.11-1 (replay compares stored facts), FA-10.11-2 (replay answers 200 before the availability check) and FA-10.11-3 (a key sent without loyalty fields is ignored) stand as written and are tested.

## Deviations from Plan

### Plan wording adjusted

- **No-availability status:** the plan text says `422 no_availability`; the existing `NoAvailabilityException` already answers **409** `no_availability`, and the plan says "existing code", so the tests assert the existing 409 and the contract is unchanged.
- **Missing-key response shape:** the plan says "422 `idempotency_key_required`"; like redeem (10-09) this is the shared `validation_failed` envelope with `errors.idempotency_key.0` = `custom.errors.idempotency_key_required`. No new error code or translation key was needed (all strings already exist in all five locales).
- **Lock order assertion:** `findFreeRoom` also takes a `for update` on `rooms` after the guest lock; the lock tests assert the plan's three-table order (room_types, guests, batches or voucher) as an ordered subsequence.

### Auto-fixed Issues

None.

## Issues Encountered

None. Pint was run on the new files (it reordered imports in the two test files); the four edited pre-existing files were not reformatted because they were never Pint-clean (aligned-arrow style and CRLF) and a reformat would bury the real change.

## Known Stubs

None.

## Threat Flags

None. T-10-41 (required key, `(guest_id, key)` unique index, replay under the room_type and guest locks; replay/conflict tests), T-10-42 (no money field; fake-field test), T-10-43 (only `ReservationService::store` forwards loyalty; M-5 guard; Q17 tests), T-10-44 (voucher re-locked and re-checked under the guest lock; used/expired-between tests; unique `loyalty_vouchers.reservation_id`) and T-10-45 (fixed lock order; three lock-order tests) are mitigated and tested.

## Acceptance gates (all clean)

- `priceRedemption->handle` and `applyLoyalty->handle` each appear once in `CreateReservationAction`.
- `grep -nE "discount_usd|total_usd"` over `StoreReservationRequest.php` prints nothing (no client-supplied money field).
- No line touching `storeAsGuest` or `adminStore` in the `ReservationService` diff.

## Next Phase Readiness

10-12 can reverse a cancelled booking from `loyalty_reservation_applications` (one row per reservation, `redeem_entry_id` for the points refund, `voucher_id` to restore the voucher) and the redeem entry key `redeem:reservation:{reservation_id}`. 10-14 earns on `folios` totals that already use the net `total_usd`. LOY-16 is fully delivered and ticked.

## Self-Check: PASSED

- All 3 created and 4 modified files exist and are in commit `e3f6e7f`; nothing pushed; foreign files (`Carlton-hotel-s/`, `backend.zip`) and the foreign STATE.md paragraph untouched and unstaged.
- Full suite exit 0 (2930 tests) before the code commit.
