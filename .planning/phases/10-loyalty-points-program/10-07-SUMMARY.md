---
phase: 10-loyalty-points-program
plan: 07
subsystem: api
tags: [laravel, loyalty, manual-adjust, idempotency, ledger, activity-log, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-03 LoyaltyLedger credit/consume/record; 10-02 LoyaltyProgram::expiresAtFrom; 10-04 cms/loyalty block and loyalty.adjust seeded; 10-06 LoyaltyAccountService, LoyaltyGuestController, LoyaltyLedgerEntryResource staff variant"
provides:
  - "POST /api/cms/loyalty/guests/{guest}/adjustments (auth:users, permission:loyalty.adjust, Idempotency-Key required)"
  - "App\\Actions\\Loyalty\\AdjustLoyaltyPointsAction::handle(Guest, int, string, User, string)"
  - "App\\Http\\Requests\\Loyalty\\AdjustLoyaltyPointsRequest (ReadsIdempotencyKey)"
  - "LoyaltyAccountService::adjust(), LoyaltyGuestController::adjust()"
  - "custom.messages.loyalty_points_adjusted in all five locales"
  - "all three loyalty.* permissions route-enforced; CmsAccessControlTest::$notYetBuilt is [] again"
affects: [10-09 vouchers, 10-12 clawback, 10-14 reports (issued points), 10-15 docs]

tech-stack:
  added: []
  patterns:
    - "Manual money-like write = guest row lock, IdempotentWrite keyed adjust:{guest_id}:{client_key}, actor inside the replay payload"
    - "Domain-error guard before the transaction for values the HTTP layer cannot express as a field rule (zero, over-cap)"

key-files:
  created:
    - backend/app/Actions/Loyalty/AdjustLoyaltyPointsAction.php
    - backend/app/Http/Requests/Loyalty/AdjustLoyaltyPointsRequest.php
    - backend/tests/Feature/Loyalty/LoyaltyAdjustTest.php
    - backend/tests/Unit/Loyalty/AdjustLoyaltyPointsActionTest.php
  modified:
    - backend/app/Services/Loyalty/LoyaltyAccountService.php
    - backend/app/Http/Controllers/Admin/LoyaltyGuestController.php
    - backend/routes/api.php
    - backend/tests/Feature/Cms/CmsAccessControlTest.php
    - backend/lang/en/custom.php
    - backend/lang/ar/custom.php
    - backend/lang/fr/custom.php
    - backend/lang/tr/custom.php
    - backend/lang/es/custom.php

key-decisions:
  - "Zero and over-magnitude points are the domain error loyalty_adjustment_invalid (context max_adjust_points), raised in the action before the transaction; the request validates only presence/type (FA-10.07-1)"
  - "The actor is part of the idempotency payload: the same key from another staff member is 409 idempotency_conflict, not a replay (FA-10.07-2)"
  - "A missing or blank Idempotency-Key answers the standard 422 validation_failed with errors.idempotency_key (the shared ReadsIdempotencyKey behaviour), not a top-level error_code"
  - "activity_log entry loyalty.points_adjusted is written only on a fresh write, never on a replay"

patterns-established:
  - "Staff adjustments reuse LoyaltyLedgerEntryResource unchanged; it already emits reason and performed_by for a staff caller"

requirements-completed: [LOY-05, LOY-20]

coverage:
  - id: D1
    description: "Award: 201, manual batch with awarded_by, reason and program expiry (24-month default with no settings row), adjust entry with performed_by, activity_log row on the guest"
    requirement: "LOY-05"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAdjustTest.php::test_an_award_creates_a_manual_batch_and_an_adjust_entry, test_an_award_works_with_no_settings_row, test_an_award_is_audited_in_the_activity_log"
        status: pass
    human_judgment: false
  - id: D2
    description: "Deduction consumes FIFO with allocations; over-deduction (and an expired batch) answers 422 loyalty_insufficient_points with available/requested context and writes nothing"
    requirement: "LOY-05"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAdjustTest.php::test_a_deduction_consumes_fifo_and_records_the_allocations, test_an_over_deduction_is_refused_and_writes_nothing, test_a_deduction_never_touches_an_expired_batch"
        status: pass
      - kind: unit
        ref: "tests/Unit/Loyalty/AdjustLoyaltyPointsActionTest.php::test_insufficient_points_leave_the_batches_untouched"
        status: pass
    human_judgment: false
  - id: D3
    description: "Guards: zero and over-cap are 422 loyalty_adjustment_invalid; reason, non-integer points and missing key are 422 field errors"
    requirement: "LOY-05"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAdjustTest.php::test_zero_and_over_magnitude_points_are_a_domain_error, test_the_maximum_magnitude_itself_is_allowed, test_reason_and_points_are_validated, test_a_missing_or_blank_key_is_422"
        status: pass
    human_judgment: false
  - id: D4
    description: "Idempotency: replay 200 with the same entry and no new rows; different body or another staff member 409; the same client key for another guest is a separate write"
    requirement: "LOY-05"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAdjustTest.php::test_the_same_key_and_body_replays_the_same_entry, test_a_replayed_deduction_does_not_consume_twice, test_the_same_key_with_a_different_body_is_a_conflict, test_the_same_key_from_another_staff_member_is_a_conflict, test_the_same_client_key_for_another_guest_is_a_separate_write"
        status: pass
      - kind: unit
        ref: "tests/Unit/Loyalty/AdjustLoyaltyPointsActionTest.php::test_a_replay_answers_200_with_the_same_entry_and_writes_nothing, test_a_different_payload_under_the_same_key_conflicts"
        status: pass
    human_judgment: false
  - id: D5
    description: "Access and locking: 401 (no token, guest token), 403 (view+manage only, no permission), 404 unknown guest; guests locked before loyalty_earn_batches"
    requirement: "LOY-20"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyAdjustTest.php::test_it_requires_a_staff_token, test_view_and_manage_holders_cannot_adjust, test_an_unknown_guest_is_404, test_a_deduction_locks_the_guest_before_the_batches"
        status: pass
    human_judgment: false
  - id: D6
    description: "All three loyalty permissions are enforced by route middleware; CmsAccessControlTest::$notYetBuilt back to []"
    requirement: "LOY-20"
    verification:
      - kind: feature
        ref: "tests/Feature/Cms/CmsAccessControlTest.php::test_every_seeded_permission_is_enforced_somewhere"
        status: pass
    human_judgment: false

duration: 25min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 07: Manual Adjust Summary

**Staff with `loyalty.adjust` award or deduct points with a mandatory reason, once per Idempotency-Key, under the guest lock and FIFO consume, recorded in the immutable ledger and the activity log, and never below a zero balance.**

## Performance

- **Tasks:** 2 (RED specs, GREEN implementation)
- **Files created:** 4, **modified:** 9
- **Tests added:** 25 (19 feature, 6 unit)

## Accomplishments

- `POST /api/cms/loyalty/guests/{guest}/adjustments` in the `cms/loyalty` block behind `permission:loyalty.adjust`; answers 201 (fresh) or 200 (replay) with the staff ledger-entry resource and `custom.messages.loyalty_points_adjusted`.
- `AdjustLoyaltyPointsAction`: magnitude guard, then one transaction that locks the guest, runs `IdempotentWrite` keyed `adjust:{guest_id}:{client_key}`, and either credits a `manual` batch (`awarded_by`, `reason`, `LoyaltyProgram::expiresAtFrom(now)`) or consumes FIFO, then records the `adjust` entry with `performed_by`, `reason` and allocations; logs `loyalty.points_adjusted` on a fresh write only.
- `loyalty.adjust` is now enforced by a route, so all three seeded loyalty permissions are enforced and `CmsAccessControlTest::$notYetBuilt` is `[]`, identical to its post-Phase-9.1 content.

## Task Commits

1. **Task 1 + Task 2 (one commit per project policy, full suite green first):** `da88cbf` feat(10-07): staff manual loyalty award/deduct, idempotent and audited

**Plan metadata:** committed separately as `docs(10-07): complete manual adjust plan`.

## Verification

- RED confirmed first: 18 failures and 6 errors across the two new files before implementation.
- Plan verify filter (`LoyaltyAdjust|AdjustLoyaltyPoints|LoyaltyAccount|LoyaltyPermissions|CmsAccessControlTest|LoyaltyLocaleTest|ValidationMessageLocalizationTest`): 119 tests, 1165 assertions, pass.
- Full suite `php artisan test`: **2815 tests, 17143 assertions, exit 0** (324 s).
- Pint on all touched PHP files: pass.
- Acceptance: `permission:loyalty.adjust` appears once in `routes/api.php`; `lockForUpdate` appears once in the action; `CmsAccessControlTest.php` has no diff against commit 1dac2b9 (post-Phase-9.1).

## Deviations from Plan

None - plan executed as written, with these small choices inside its latitude:

- The plan's acceptance line `git diff --quiet HEAD -- CmsAccessControlTest.php` cannot hold before the commit (the revert is the change). It was checked against `1dac2b9` instead, where the file is identical.
- A missing or blank Idempotency-Key is the shared `ReadsIdempotencyKey` 422: `error_code: validation_failed` with `errors.idempotency_key[0]` = `custom.errors.idempotency_key_required` (the same shape the folio payment and event deposit routes return), rather than a top-level `idempotency_key_required` error code.
- The route uses its own `permission:loyalty.adjust` group, mirroring the `view` and `manage` groups already in the block.

## Drift from the plan (Phase 9.1)

- Plan-time `$notYetBuilt` had a Phase 9.1 baseline of `[]`; 10-04 temporarily added `loyalty.adjust` plus a comment, and this plan removed both. No other entry or count pin moved (permission catalogue 33, as set by 10-04).
- Suite size is now 2815 (10-06 recorded 2790; +25 from this plan).

## Issues Encountered

None. `Carlton-hotel-s/`, `backend.zip` and the other session's paragraph in `.planning/STATE.md` were not touched or staged. One test needed a fix on first GREEN run: the DB drops microseconds from `expires_at`, so the expiry assertion compares to the second.

## Known Stubs

None.

## Threat Flags

None beyond the plan's register. T-10-25 (separate permission, 403 for a view+manage holder), T-10-26 (ledger row with `performed_by` and `reason` plus the activity log entry), T-10-27 (required key, guest lock, unique ledger key, replay/conflict tests) and T-10-28 (FIFO consume pre-check, magnitude cap) are mitigated and tested.

## Requirements

- **LOY-05** and **LOY-20** are ticked: manual award/deduct is complete, and all three `loyalty.*` permissions are seeded, route-enforced and shown in the picker (no preset changes).
- **LOY-21** was already ticked; this plan adds its 401/403/404/422 evidence and the new message key in all five locales.

## Notes for the docs plan (10-15)

- FA-10.06-2: a staff member who adjusts points must also hold `loyalty.view`, because the adjust response is the staff ledger-entry resource and reading the balance needs the view routes. State it in the guide.
- Request contract for Postman and docs: header `Idempotency-Key` (required, max 64), body `points` (signed integer, 0 and over `config('loyalty.max_adjust_points')` = 1000000 rejected as `loyalty_adjustment_invalid`), `reason` (string, 3 to 500). Replay with the same key, points, reason and staff member returns 200 with the same entry uuid; any difference returns 409 `idempotency_conflict`.

## Next Phase Readiness

10-08 (rewards CRUD) is next. Manual batches now exist with source `manual`, which the 10-14 reports can count as issued points.

## Self-Check: PASSED

- All four created files exist; commit da88cbf exists.
