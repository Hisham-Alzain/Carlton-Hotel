---
phase: 10-loyalty-points-program
plan: 13
subsystem: api
tags: [laravel, loyalty, scheduler, console, notifications, expiry, phpunit]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-02 LoyaltyProgram::expiryWarningDays/expiresAtFrom; 10-03 LoyaltyLedger::expire and the expires_at > now() availability filter; 10-01 expiry_warned_at column and BuildsLoyaltyFixtures"
provides:
  - "loyalty:expire-points (ExpireLoyaltyBatchesAction): idempotent daily sweep of batches (expire ledger entry + allocation) and vouchers"
  - "loyalty:notify-expiring (NotifyExpiringLoyaltyPointsAction): one localized push per guest per run, each batch warned once"
  - "Schedule entries: daily 01:00 and 09:00 in hotel timezone, withoutOverlapping"
  - "NotificationType::LOYALTY_POINTS_EXPIRING and custom.notifications.loyalty_points_expiring.{title,body} in en/ar/fr/tr/es"
affects: [10-14 reports (expired points), 10-15 docs and Postman, loyalty account endpoint]

tech-stack:
  added: []
  patterns:
    - "Per-row transaction locking guest then batch/voucher (M-6), re-checking status under lock, with the unique ledger key as backstop"
    - "Per-guest transaction wrapping notification row, push and warned-markers so a failed push rolls back all three; the loop catches Throwable per guest to contain failures"

key-files:
  created:
    - backend/app/Actions/Loyalty/ExpireLoyaltyBatchesAction.php
    - backend/app/Actions/Loyalty/NotifyExpiringLoyaltyPointsAction.php
    - backend/app/Console/Commands/ExpireLoyaltyPoints.php
    - backend/app/Console/Commands/NotifyExpiringLoyaltyPoints.php
    - backend/tests/Feature/Loyalty/LoyaltyExpiryTest.php
    - backend/tests/Feature/Loyalty/LoyaltyExpiryWarningTest.php
  modified:
    - backend/routes/console.php
    - backend/app/Enums/NotificationType.php
    - backend/lang/en/custom.php
    - backend/lang/ar/custom.php
    - backend/lang/fr/custom.php
    - backend/lang/tr/custom.php
    - backend/lang/es/custom.php

key-decisions:
  - "Voucher sweep also takes the guest lock then re-reads the voucher FOR UPDATE and skips unless still active and past expiry, so a concurrent booking that used the voucher wins (not in the plan; same guest -> voucher order as the rest of the phase)"
  - "Warning window is evaluated once per run against a single captured now, so every batch and its marker use the same instant"
  - "Command output is one line per command: 'Expired N loyalty batch(es) and M voucher(s).' and 'Warned N guest(s) about expiring loyalty points (F failure(s)).'; notify exits FAILURE when F > 0 so the scheduler/monitoring sees a push outage"
  - "Failures are logged with logger()->error (guest id and exception message only, no PII beyond id)"

patterns-established:
  - "Schedule tests find the event in app(Schedule::class)->events() and assert expression, timezone (config hotel.timezone) and withoutOverlapping"
  - "Hotel-local date in a push body is derived with HotelClock::timezone(), proven with a Tokyo zone where the local date differs from UTC"

requirements-completed: [LOY-09, LOY-10]

coverage:
  - id: D1
    description: "Daily sweep expires due batches with expire entry + allocation, vouchers flip to expired, rerun writes nothing, expiry boundary at T-1s/T"
    requirement: "LOY-09"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyExpiryTest.php (sweep, rerun, boundary, vouchers, London month-end, expiry_months change, locks)"
        status: pass
    human_judgment: false
  - id: D2
    description: "Expired-but-unswept batches are already unavailable (availability independent of the job)"
    requirement: "LOY-08"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyExpiryTest.php::test_an_expired_batch_is_already_unavailable_before_the_sweep_runs"
        status: pass
    human_judgment: false
  - id: D3
    description: "One push per guest per run aggregating in-window batches, each batch warned once, retry after failure, locale and hotel-local date, run-time warning days"
    requirement: "LOY-10"
    verification:
      - kind: feature
        ref: "tests/Feature/Loyalty/LoyaltyExpiryWarningTest.php (10 tests)"
        status: pass
    human_judgment: false
  - id: D4
    description: "Both commands scheduled daily 01:00/09:00 in hotel timezone, withoutOverlapping"
    requirement: "LOY-09, LOY-10"
    verification:
      - kind: feature
        ref: "LoyaltyExpiryTest::test_the_sweep_is_scheduled_daily_at_one_..., LoyaltyExpiryWarningTest::test_the_warning_is_scheduled_daily_at_nine_..."
        status: pass
    human_judgment: false

duration: 40min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 13: Daily Expiry and Expiry-Warning Jobs Summary

**Two scheduled commands now close the points lifecycle: `loyalty:expire-points` writes expire ledger entries for due batches and flips expired vouchers idempotently at 01:00 hotel time, and `loyalty:notify-expiring` sends each guest one localized push per run (each batch warned exactly once) at 09:00.**

## Performance

- **Duration:** about 40 min
- **Completed:** 2026-10-05
- **Tasks:** 2 (RED specs, GREEN implementation)
- **Files:** 6 created, 7 modified

## Accomplishments

- `ExpireLoyaltyBatchesAction`: `chunkById(200)` over active batches with `expires_at <= now()`; per batch one transaction locks the guest, then `LoyaltyLedger::expire` re-reads the batch FOR UPDATE (no-op unless still active). Same chunked sweep for active vouchers, one row at a time through the model, so each flip is activity-logged. No bulk updates.
- `NotifyExpiringLoyaltyPointsAction`: window is active, unwarned, `points_remaining > 0`, `now < expires_at <= now + expiry_warning_days` (read at run time). Per guest, in one transaction under the guest lock: re-select the window batches FOR UPDATE, push once (total points, earliest expiry, hotel-local `Y-m-d`), then mark exactly those batches. A push exception rolls back the `guest_notifications` row and the markers; the loop contains the failure to that guest.
- Locale comes from `preferred_locale` with `app.locale` as fallback; payload is only `points` and `expires_at`.
- `routes/console.php` registers both commands with `->timezone(config('hotel.timezone'))->withoutOverlapping()` and Phase 10 comments; `schedule:list` shows both.

## Task Commits

1. **Task 1 + Task 2 (one commit per project policy, full suite green first):** `a175d8d` feat(10-13): daily loyalty expiry sweep and once-per-batch expiry warning

RED was confirmed first (16 errors "command does not exist"/missing class plus 2 schedule failures; the availability-before-sweep test already passed because 10-03 owns that filter). No red state was committed.

**Plan metadata:** committed separately as `docs(10-13): complete daily expiry and expiry-warning jobs plan`.

## Verification

- `php artisan test --filter='LoyaltyExpiry|LoyaltyLedger|LoyaltyAccount|NotificationTriggersTest|DigitalKeyLifecycleTest|LoyaltyLocaleTest|RecycleBinRetention'`: 115 tests, 782 assertions, pass.
- Full suite `php artisan test`: **2973 tests, 18100 assertions, exit 0** (334 s).
- `grep -c withoutOverlapping routes/console.php`: 5 versus 3 at HEAD (+2); no `->update([` in either action; Pint clean.

## Decisions Made

See `key-decisions` above.

## Deviations from Plan

### Auto-fixed Issues

None needing a rule: plan executed as written. One addition beyond the spec (Rule 2, correctness): the voucher sweep takes the guest lock and re-reads the voucher FOR UPDATE before flipping it, so it cannot overwrite a voucher a booking has just used.

### Count and pin drift (Phase 9.1, commit 1dac2b9)

Full suite is 2973 tests versus 2716 recorded in 10-03; the difference is Phase 9.1 plus plans 10-04..10-12 and this plan's 19 new tests. No existing pin broke. No test pins the total schedule event count, so nothing needed re-pinning: the two schedule tests find their own event by command name (the repo's existing pattern in `DigitalKeyLifecycleTest` and `RecycleBinRetentionTest`).

**Total deviations:** 1 additive safety lock, no scope change.

## Issues Encountered

- `backend/database/database.sqlite` shows as modified in `git status` during this session. It is not touched by this plan's code or commits (tests use in-memory SQLite); it was left unstaged and unmodified by me per the project rules.

## Known Stubs

None.

## Threat Flags

None. No endpoint or trust boundary added. T-10-51 (rerun test, per-batch lock plus status re-check, unique key), T-10-52 (per-batch marker in the same transaction, one push per guest per run), T-10-53 (payload is points and expiry instant only, asserted by key list) and T-10-54 (availability filter independent of the job, boundary and before-sweep tests) are mitigated and tested.

## Requirements

LOY-09 and LOY-10 are complete with this plan. LOY-08 was already ticked by earlier plans and this plan adds its sweep half. LOY-21 (locale/docs/Postman gate) is only partly delivered here (strings x5 for the new notification) and is left as is for 10-15.

## Next Phase Readiness

10-14 reports can sum `expire` ledger entries for "expired points". 10-15 should document the two commands, their schedule and the new `loyalty_points_expiring` notification type (data keys `points`, `expires_at`). Production must run `schedule:run` every minute with a lock-capable cache (FA-10.13-3, already required by the existing commands).

## Self-Check: PASSED

- All six created files exist; commit a175d8d exists.
