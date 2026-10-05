---
phase: 10-loyalty-points-program
plan: 15
subsystem: docs
tags: [docs, postman, changelog, phase-gate, loyalty, coverage]

requires:
  - phase: 10-loyalty-points-program
    provides: "10-01..10-14 loyalty schema, ledger, settings, earn, adjust, rewards, redeem, preview, booking, reversals, jobs, reports"
provides:
  - "Dashboard guide: Loyalty program module, error codes, permission catalogue 33/14"
  - "Mobile guide: Loyalty module with Flutter mapping note and booking flow; changelog Phase 10 entry"
  - "Postman folder 'Loyalty (Phase 10)' covering all 20 routes; tree nodes for guest and staff loyalty"
  - "Phase gate results and LOY-01..22 / Q1..Q25 / M-1..M-10 coverage (this file also serves as the phase summary)"
affects: [phase 10 verification, Flutter and React teams]

tech-stack:
  added: []
  patterns:
    - "Docs examples captured from real responses of a temporary dump test (deleted before commit)"

key-files:
  created:
    - .planning/phases/10-loyalty-points-program/10-15-SUMMARY.md
  modified:
    - backend/docs/API_GUIDE_DASHBOARD.md
    - backend/docs/API_GUIDE_MOBILE.md
    - backend/docs/CHANGELOG_MOBILE_API.md
    - backend/docs/postman/carlton-api.postman_collection.json
    - docs/carlton-tree.html
    - backend/app/Http/Resources/Loyalty/LoyaltyLedgerEntryResource.php
    - backend/tests/Feature/Loyalty/LoyaltyAdjustTest.php

key-decisions:
  - "A missing Idempotency-Key is documented as the shared 422 validation_failed with errors.idempotency_key; no top-level idempotency_key_required code exists"
  - "The tree gets two leaf nodes inside the existing 'Guests & messaging' section (no new section), so the api:true text count grows by exactly 2"
  - "The account-deletion loyalty forfeit hook (Phase 9.1 / ROADMAP) is NOT implemented here and is recorded as an open follow-up"
  - "No separate phase-level SUMMARY.md: the harness refused to write it (twice), so this file carries the phase summary content"

patterns-established:
  - "Postman requests capture ids into collection variables via test scripts and generate a fresh Idempotency-Key per send"

requirements-completed: [LOY-01, LOY-21]

duration: 60min
completed: 2026-10-05
status: complete
---

# Phase 10 Plan 15: Docs and Phase Gate Summary

**Both client teams can integrate the loyalty program from the guides, changelog and a 25-request Postman folder alone; the full suite is green at 2992 tests and every LOY requirement, ruling and mandatory item is traced to plans and named tests.**

## Performance
- **Duration:** about 60 min (full suite 345 s)
- **Completed:** 2026-10-05
- **Tasks:** 2 of 2 (Task 1 docs, Task 2 gate + summary)
- **Commits:** code/docs `2c91cf5`; metadata commit follows as `docs(10-15)`.
- **Files:** 7 in the docs commit (0 created, 7 modified), +1126 / −8.

## Accomplishments
- **Dashboard guide:** new *Module: Loyalty program* (routes and gates table, settings with present-key PUT semantics and bounds, three independent capabilities and "unset cap is never 100%", rewards CRUD with 204 delete and bin on `cms.restore`/`cms.purge`, voucher semantics, guest balance and ledger contract, adjustments with `Idempotency-Key` and 201/200/409/422, reports with metric definitions, the reservation `loyalty` block, scheduled jobs, error-code table, a "Not provided / known limitation" note, dashboard handoff). Catalogue updated to 14 modules / 33 permissions with the three `loyalty.*` permissions in no preset, and the "adjusters also need `loyalty.view`" rule. Eight loyalty rows added to the error quick reference. `PermissionGuideAccuracyTest` stays green (inert list is still "None").
- **Mobile guide:** endpoint index 64 → 70; new *Module: Loyalty* (account, ledger, rewards, redeem, vouchers with lifecycle, preview, booking flow, expiry push `loyalty_points_expiring`, error table) and the Flutter mapping note (kinds, sources collapse to `service`, no tier fields, no `staysCount`, no `memberId`, lifetime figures, FIFO by earliest expiry); `POST /reservations`, `DELETE /reservations/{uuid}` and the push-triggers paragraph updated.
- **Changelog:** dated 2026-10-05 Phase 10 entry (added routes, non-breaking changes, eight new error codes, new notification type, "No breaking changes", Flutter actions).
- **Postman:** folder *Loyalty (Phase 10)* with Guest (9) and Staff (16) requests, every one of the 20 routes covered; `Idempotency-Key` header on redeem, both adjustments and both loyalty bookings; collection variables captured by test scripts.
- **Tree:** nodes "loyalty — guest" (`dash:"na"`, `mob:"mock"`) and "loyalty program — staff" (`dash:false`); textual `"api":true` count 92 → 94 (exactly the two nodes added); computed header counters 84 → 86 capabilities, 83 → 85 on the API.
- **Real responses:** a temporary dump test exercised every route (including 401/403/409/422 shapes) and wrote the JSON to the scratchpad; examples in the guides come from it. The test was deleted before the commit.

## Task Commits
1. **Tasks 1-2 (docs, resource fix, gate): `2c91cf5`** docs(10-15) — created after the full suite was green.
2. Metadata commit: `docs(10-15): complete docs and phase gate plan` (this file, ROADMAP, STATE).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `shortfall_points` was `null` on the first adjustment response, `0` on its replay**
- **Found during:** Task 1 (response capture)
- **Issue:** a ledger row created in the request has not been re-read, so `LoyaltyLedgerEntryResource` emitted `null` for the column default; the replay and every list read emitted `0`.
- **Fix:** `(int)` cast in the resource and `->assertJsonPath('data.shortfall_points', 0)` in `LoyaltyAdjustTest::test_an_award_creates_a_manual_batch_and_an_adjust_entry`.
- **Files modified:** `backend/app/Http/Resources/Loyalty/LoyaltyLedgerEntryResource.php`, `backend/tests/Feature/Loyalty/LoyaltyAdjustTest.php`
- **Commit:** `2c91cf5`

### Plan wording adjusted
- The plan lists `.planning/phases/10-loyalty-points-program/SUMMARY.md` as an output. The harness refused to create a file named `SUMMARY.md` twice ("subagents should return findings as text"), while `10-15-SUMMARY.md` (the file the orchestrator asked for) was accepted. This file therefore also carries the phase-level content (endpoints, waves, permissions, deploy steps, notes, coverage). If a separate phase `SUMMARY.md` is wanted, copy it from here.
- The plan says "MUST NOT git commit"; the project override (commit locally, never push) takes precedence, so this plan produced two local commits and no push.
- `composer run pint` does not exist in `composer.json`; used `vendor/bin/pint --test`.
- The plan says error codes `idempotency_key_required` / `reservation_state`; per the known facts the missing key is `validation_failed` with `errors.idempotency_key`, and the guides say so.
- The Postman "collection variables" are declared in the collection's `variable` array and set by test scripts; the environment file (generated by `postman:refresh-env`) was not touched.

## Authentication Gates
None.

## Issues Encountered
- **`backend/database/database.sqlite` changed during the gate, caused by `php artisan schedule:list`.** The local `.env` has `CACHE_STORE=database`, so `schedule:list` writes a cache row to the tracked dev DB (confirmed: running it again changed the hash again; `route:list` and `migrate:status` do not). The hash was `8218d6dd…07694ac6` before the gate and unchanged after the scratch migrations, the dump test and the full suite; it is now `b20bfd01…` (prefix). The file was already modified in the working tree by an unrelated local process, was never staged, and nothing was restored or reset. I could not restore the previous bytes. Consequence for the orchestrator: treat the file as a foreign dirty file as before, and do not run `schedule:list` with this `.env` again.
- The context-mode MCP tools named in the prompt were not available; plain Bash and Grep were used instead.

## Known Stubs
None. The Postman variables start empty by design and are filled by test scripts.

## Threat Flags
None. T-10-58 (guide says no preset holds `loyalty.*` and why `adjust` is separate), T-10-59 (examples captured from real responses; all 20 routes cross-checked against `route:list`), T-10-60 (the [BLOCKING] deploy steps below) are mitigated.

## Deploy notes (phase)
- **[BLOCKING]** `php artisan migrate`: seven new tables `loyalty_settings`, `loyalty_rewards`, `loyalty_earn_batches`, `loyalty_vouchers`, `loyalty_ledger_entries`, `loyalty_allocations`, `loyalty_reservation_applications` (`2026_10_05_100000` .. `100600`, additive, roll back with `--step=7`).
- **[BLOCKING]** `php artisan db:seed --class=RolesAndPermissionsSeeder` (adds `loyalty.view`, `loyalty.manage`, `loyalty.adjust`; +5 lines, 0 removed).
- **[BLOCKING]** Assign `loyalty.*` per staff account via `POST /api/staff/{uuid}/permissions`; **no preset holds any**. Loyalty manager = view + manage; supervisor = view + adjust (an adjuster needs view too, FA-10.06-2).
- **[BLOCKING]** Configure the program with `PUT /api/cms/loyalty/settings`. No settings row is seeded: earning stays off until `earn_rate > 0`; paying with points stays off until `redeem_value_usd > 0` AND `max_redeem_percent` is set; rewards need no settings. `expiry_months` 24 and `expiry_warning_days` 30 are column defaults.
- **[BLOCKING]** Confirm `schedule:run` runs every minute with a lock-capable cache: `loyalty:expire-points` daily 01:00 and `loyalty:notify-expiring` daily 09:00 hotel time (`schedule:list` renders `0 22 * * *` / `0 6 * * *` for Asia/Damascus, UTC+3). The notify command exits non-zero on a push failure.
- No `.env` changes; `config/loyalty.php` adds `restored_voucher_grace_days` (30) and `max_adjust_points` (1 000 000). No backfill.

## Endpoints (all under `/api`, no `/v1`)

Guest (`auth:guests`): `GET /loyalty/account`, `GET /loyalty/ledger`, `GET /loyalty/rewards`, `POST /loyalty/rewards/{reward}/redeem` (Idempotency-Key, 201/200/409, throttle 30/min), `GET /loyalty/vouchers`, `GET /loyalty/preview` (throttle 30/min); additive `loyalty_points` / `voucher_code` on `POST /reservations` (Idempotency-Key required only with them).

Staff (`auth:users`):

| Verb + path | Gate |
|---|---|
| GET `/cms/loyalty/settings` | `loyalty.view\|loyalty.manage` |
| PUT `/cms/loyalty/settings` | `loyalty.manage` |
| GET `/cms/loyalty/rewards`, `…/{reward}` | `loyalty.view\|loyalty.manage` |
| POST `/cms/loyalty/rewards`, PUT / DELETE `…/{reward}` | `loyalty.manage` (DELETE soft, 204) |
| GET `/cms/loyalty/rewards/trashed` | `cms.restore\|cms.purge` |
| POST `…/{reward}/restore` | `cms.restore` |
| DELETE `…/{reward}/force` | `cms.purge` |
| GET `/cms/loyalty/guests/{guest}`, `…/ledger` | `loyalty.view` |
| POST `/cms/loyalty/guests/{guest}/adjustments` | `loyalty.adjust` (Idempotency-Key) |
| GET `/cms/loyalty/reports` | `loyalty.view` |

`route:list --path=api/loyalty -v`: 6 routes, all `Authenticate:guests` (preview and redeem add `ThrottleRequests:30,1`). `--path=api/cms/loyalty -v`: 14 routes, all `Authenticate:users` plus the `PermissionMiddleware` gate above.

## Waves

| Plan | Commit | Delivered |
|---|---|---|
| 10-01 | `baa5d13` | 7 tables, 6 enums, models, factories, fixtures trait, unique backstops |
| 10-02 | `43a84eb` | `LoyaltyMath`, `LoyaltyProgram`, 8 exceptions, 5-locale error keys |
| 10-03 | `30a2439` | `LoyaltyLedger`: FIFO consume, refund, clawback, expire |
| 10-04 | `2f89639` | Settings API, 3 permissions, count re-pins |
| 10-05 | `0b82bef` | Earn inline at the 3 settlement sites |
| 10-06 | `0af458e` | Guest account + ledger, staff guest view |
| 10-07 | `da88cbf` | Manual adjust |
| 10-08 | `db1fb10` | Rewards CRUD + bin, guest catalogue |
| 10-09 | `186183b` | Redeem into voucher, my vouchers |
| 10-10 | `b3f5f59` | Pricing action + preview |
| 10-11 | `e3f6e7f` | Booking with points or voucher |
| 10-12 | `4750a80` | Cancel reversals, folio seam |
| 10-13 | `a175d8d` | Daily expiry and warning jobs |
| 10-14 | `0979410` | Reports |
| 10-15 | `2c91cf5` | Docs and gate |

## Permissions: 33 / 14 (was 30 / 13)
New group `loyalty` = `loyalty.view`, `loyalty.manage`, `loyalty.adjust`. **New permissions: seeded, in no role preset; assign per account.** Presets unchanged (`RolePresetsTest` has no diff since the pre-phase base). Phase 9.1 added none, so the measured baseline was 30 / 13 as planned. `CmsAccessControlTest::$notYetBuilt` is `[]` again. The guide catalogue, `GET /permissions` module list (14) and the inert list ("None") match the router.

## Error codes (details under `context`)
`loyalty_program_inactive {capability}`, `loyalty_insufficient_points {available_points, requested_points}`, `loyalty_below_minimum {min_redeem_points}`, `loyalty_over_cap {max_points}`, `loyalty_voucher_invalid`, `loyalty_reward_unavailable`, `loyalty_adjustment_invalid {max_adjust_points}`, `loyalty_discount_conflict` — all 422. Reused: `idempotency_conflict` 409, `no_availability` 409, `reservation_state` 422, `validation_failed` 422. Validation key `loyalty_report_period_too_long`. Notification type `loyalty_points_expiring` (`data {points, expires_at}`).

## Flutter note
Additive only. Mock kinds `earned|redeemed|expired` → `earn|redeem|expire` (+ `adjust`, `clawback`, `refund`); sources `dining|spa|other` → `service` (+ `manual`, `refund`); no tier fields, no `staysCount`, no `memberId`; `earnedTotal`/`redeemedTotal` → `lifetime_earned_points`/`lifetime_redeemed_points`; `bookingRef` → `reservation.booking_code` (nullable). Flow: preview, then `POST /reservations` with the same inputs and a reused `Idempotency-Key` (200 replay, 409 on a different body). Use `program` switches; branch on `error_code`; open Loyalty from the expiry push.

## React note
Additive routes; no loyalty screens exist in the dashboard yet. Gate on `loyalty.view|manage`; adjust button on `loyalty.adjust` AND `loyalty.view`; rewards trash on `cms.restore`/`cms.purge`. PUT settings is present-key (explicit `null` clears). Reservations carry an additive `loyalty` block; `upgrade_requested` means staff upgrade the room. Reports: issued excludes refunds; outstanding and liability are point in time; liability is `null` until a redeem value is set.

## Manual-only checks
- MySQL true concurrency: parallel redeems with one key, parallel settles of one folio, cancel racing a settle, concurrent first settings insert, parallel bookings with one key.
- FCM: one localized `loyalty_points_expiring` push per guest per run on a staging device.

## Deferred / open items
- **OPEN FOLLOW-UP (needs a gap plan): account-deletion forfeit.** ROADMAP Phase 10 (Phase 9.1 hook) requires that `DeleteGuestAccountAction` forfeit the guest's loyalty balance (expire entries, PII-free activity counts). No plan covers it and `DeleteGuestAccountAction` has no loyalty reference; a deleted guest keeps active batches until the normal sweep, and `outstanding_points`/`liability_usd` include them meanwhile. Not implemented here by instruction. The dashboard guide states it as a known limitation.
- Folio refund endpoint (only the `ReverseLoyaltyForFolioAction` seam exists), tiers, historical backfill, loyalty on staff and public-OTP bookings, a producer for the `void` voucher status.
- `idempotency_conflict.context.idempotency_key` echoes the internal composite key (shared exception, unchanged).
- Tree footer text ("311 routes", "94 backend test files") was already stale and was not touched.
- The 8 `Loyalty*Exception` one-liners are not Pint-clean, matching the existing exception style (`NoAvailabilityException` fails the same fixers); not reformatted.

## Tests and gate results
- **Full suite:** `php artisan test` = **2992 tests, 2992 passed, 18241 assertions, exit 0** (345 s), on the final tree. Base before 10-01: 2376. Phase 10 added 406 tests (plans 10-01..10-14: 52, 45, 33, 22, 25, 27, 25, 25, 19, 37, 34, 24, 19, 19); the remaining +210 are Phase 9.1's in-flight tests. 10-15 added one assertion and no test class.
- Docs tests: `tests/Feature/Docs` 8 passed. Postman JSON parses. Scratch SQLite `migrate:fresh` / `migrate:rollback --step=7` / `migrate`: clean.
- Pint (`vendor/bin/pint --test`): clean for every Phase 10 file except the 8 exception one-liners above.
- `QuoteReservationAction` and `ReleaseExpiredHoldsAction`: zero commits since the pre-10-01 base; `git diff --quiet HEAD` clean. Seeder +5 / −0. Lang files: additions only. Migrations: additions only. `RolePresetsTest`: no diff.
- `schedule:list` shows both loyalty commands (see the `database.sqlite` note above).
- **M-gates, all clean:** M-1 no `?? 100`/`?? 1.0` (only `min_redeem_points ?? 1` in `LoyaltyProgram.php`); M-2 one `catch` (`UniqueConstraintViolationException`) in `EarnLoyaltyPointsAction`; M-3 `lockForUpdate` then `isCancellable()` in the transaction, return `['data' => null, 'code' => 204]`; M-4 both actions unmodified; M-5 apply refuses `PENDING_VERIFICATION` and non-null `hold_expires_at`; M-6 "Lock order" documented in the loyalty actions; M-7 no `discount_usd`/`total_usd` in `StoreReservationRequest` or `LoyaltyPreviewRequest`; M-8 no `Cache::`, `cache(`, `SiteSetting`, `site_settings` in loyalty support/actions/services; M-9 unique `loyalty_vouchers.reservation_id` and `loyalty_ledger_entries.reverses_entry_id`; M-10 lang appended only, pins re-pinned once.
- Phase 9.1 drift: none affecting this plan. Counts re-read: permissions 33, groups 14, routes 20 loyalty (6 + 14), 392 `api/` routes total, schedule events present.

## Requirement status (LOY-01..LOY-22)
All 22 were already ticked in REQUIREMENTS.md by earlier plans (including LOY-21 and LOY-22 early); none was changed in this plan and none needs un-ticking. Status after the gate:

| Req | Status | Evidence (plans / named tests) |
|---|---|---|
| LOY-01 | Complete, verified | 10-04 `LoyaltySettingsTest`; documented 10-15 |
| LOY-02 | Complete, verified | 10-01/02/04/05 `LoyaltySchemaTest`, `LoyaltyProgramTest`, `LoyaltySettingsTest` |
| LOY-03 | Complete, verified | 10-05 `LoyaltyEarnOnSettleTest`, `EarnLoyaltyPointsActionTest`, `LoyaltyMathTest` |
| LOY-04 | Complete; **concurrency partial** | 10-01/05 unique backstop + rollback tests; real concurrent double-settle is a MySQL manual check |
| LOY-05 | Complete, verified | 10-07 `LoyaltyAdjustTest`, `AdjustLoyaltyPointsActionTest` |
| LOY-06 | Complete, verified | 10-06 `LoyaltyAccountTest` |
| LOY-07 | Complete, verified | 10-06 `LoyaltyAccountStaffViewTest` |
| LOY-08 | Complete, verified | 10-01/02/03/06/13 `LoyaltyLedgerFifoTest`, `LoyaltyExpiryTest`, `LoyaltyAccountTest` |
| LOY-09 | Complete, verified | 10-13 `LoyaltyExpiryTest` |
| LOY-10 | Complete; **FCM delivery unverified** | 10-13 `LoyaltyExpiryWarningTest` (fake Firebase); real push is a manual check |
| LOY-11 | Complete, verified | 10-08 `LoyaltyRewardTest` |
| LOY-12 | Complete, verified | 10-08 `LoyaltyRewardTest` (guest catalogue) |
| LOY-13 | Complete; **concurrency partial** | 10-03/09 `LoyaltyRedeemTest`, `RedeemRewardActionTest`; parallel same-key redeem is a MySQL manual check |
| LOY-14 | Complete, verified | 10-09 `LoyaltyRedeemTest` |
| LOY-15 | Complete, verified | 10-10 `LoyaltyPreviewTest`, `PriceLoyaltyRedemptionActionTest` |
| LOY-16 | Complete, verified | 10-10/11 `LoyaltyBookingTest`, `ApplyLoyaltyToReservationActionTest` |
| LOY-17 | Complete; **race partial** | 10-12 `LoyaltyReversalOnCancelTest`, `LoyaltyLedgerReversalTest`; cancel-vs-settle race is a MySQL manual check |
| LOY-18 | Complete (seam only by ruling Q1) | 10-12 `ReverseLoyaltyForFolioActionTest` |
| LOY-19 | Complete, verified | 10-14 `LoyaltyReportTest` |
| LOY-20 | Complete, verified | 10-04/07/14 `LoyaltyPermissionsTest`, `SeederTest`, `PermissionsGroupedTest`, `CmsAccessControlTest`, `RolePresetsTest`, `PermissionGuideAccuracyTest` |
| LOY-21 | Complete with this plan | 10-02/04/06/07/08/13/14 locale + per-route tests; 10-15 guides, changelog, Postman, tree (ticked early by 10-02, now actually satisfied) |
| LOY-22 | Complete, verified | 10-02/04/09/10 `LoyaltyProgramTest`, `LoyaltySettingsTest`, `LoyaltyPreviewTest`, `PriceLoyaltyRedemptionActionTest` |

**Not a LOY requirement but a ROADMAP Phase 10 obligation and unmet:** account-deletion loyalty forfeit (see Deferred).

## Coverage: rulings Q1..Q25

| Q | Ruling | Plans | Named tests |
|---|---|---|---|
| Q1 | Folio-refund seam only, wired from cancel | 10-12 | `ReverseLoyaltyForFolioActionTest` |
| Q2 | Clawback: origin then FIFO, floor 0, shortfall | 10-03, 10-12 | `LoyaltyLedgerReversalTest::test_clawback_*`, `LoyaltyReversalOnCancelTest` |
| Q3 | Refund into active/depleted unexpired batch else fresh batch | 10-03, 10-12 | `LoyaltyLedgerReversalTest::test_refund_*`, `LoyaltyReversalOnCancelTest` |
| Q4 | Three independent capabilities, null never 100% | 10-02, 10-04, 10-10 | `LoyaltyProgramTest`, `LoyaltySettingsTest`, `PriceLoyaltyRedemptionActionTest` |
| Q5 | Cap on post-promo total, one voucher, conflict 422, floor 0.00 | 10-10, 10-11 | `PriceLoyaltyRedemptionActionTest`, `LoyaltyPreviewTest`, `LoyaltyBookingTest` |
| Q6 | Idempotency-Key only with loyalty fields; replay 200 / conflict 409 | 10-11 | `LoyaltyBookingTest` (replay, conflict, key rules) |
| Q7 | Permissions, no preset change, bin on cms.restore/purge, reports on view | 10-04, 10-08, 10-14 | `LoyaltyPermissionsTest`, `SeederTest`, `RolePresetsTest`, `LoyaltyRewardTest` |
| Q8 | Earn inline atomic; only unique violation caught | 10-05 | `LoyaltyEarnOnSettleTest::test_a_failure_while_earning_rolls_the_whole_settlement_back` |
| Q9 | Cancelled reservations do not earn, logged | 10-05 | `LoyaltyEarnOnSettleTest::test_a_cancelled_reservation_settles_without_earning_and_logs_the_skip` |
| Q10 | One rate, half-up per stay/service bucket | 10-02, 10-05 | `LoyaltyMathTest`, `EarnLoyaltyPointsActionTest` |
| Q11 | Manual lines and credits in buckets | 10-05 | `EarnLoyaltyPointsActionTest` (bucketing tests) |
| Q12 | Free night = one night capped; upgrade = flag only | 10-10, 10-11 | `PriceLoyaltyRedemptionActionTest`, `LoyaltyBookingTest` |
| Q13 | Voucher validity end of hotel day; restore with grace | 10-09, 10-12 | `LoyaltyRedeemTest`, `LoyaltyReversalOnCancelTest` (voucher tests) |
| Q14 | Consume by `expires_at, id` | 10-03 | `LoyaltyLedgerFifoTest` |
| Q15 | Expiry months not applied to existing batches; warning days read at run time | 10-13, 10-06 | `LoyaltyExpiryTest::test_changing_the_expiry_months_does_not_move_an_existing_batch`, `LoyaltyExpiryWarningTest`, `LoyaltyAccountTest::test_the_warning_window_is_read_at_request_time` |
| Q16 | Contract strings (`clawback` instead of `reversal`), no tier fields | 10-01, 10-06, 10-11, 10-15 | `LoyaltyEnumsTest`, `LoyaltyAccountTest`, `LoyaltyBookingTest`, guides + changelog |
| Q17 | Loyalty only on authenticated guest `POST /reservations` | 10-11 | `LoyaltyBookingTest` (staff and OTP tests) |
| Q18 | `is_active` visibility | 10-08 | `LoyaltyRewardTest` |
| Q19 | Report: issued excludes refunds; point-in-time outstanding and liability | 10-14 | `LoyaltyReportTest` |
| Q20 | Manual deduct refused beyond balance; magnitude cap; reason min 3 | 10-07 | `LoyaltyAdjustTest`, `AdjustLoyaltyPointsActionTest` |
| Q21 | Route namespaces `/loyalty/*` and `/cms/loyalty/*` | 10-04..10-14 | `LoyaltyPermissionsTest` route walk; `route:list` at the gate |
| Q22 | One aggregated push per guest per run, localized | 10-13 | `LoyaltyExpiryWarningTest` |
| Q23 | Execute after Phase 9 commit | 10-01, 10-04 | Phase 9 commit verified as ancestor; count pins re-pinned once |
| Q24 | Guest-null reservations earn nothing | 10-05, 10-12 | `LoyaltyEarnOnSettleTest::test_a_reservation_without_a_guest_settles_without_earning`, `ReverseLoyaltyForFolioActionTest` |
| Q25 | Throttle 30/min on redeem and preview | 10-09, 10-10 | `LoyaltyRedeemTest` and `LoyaltyPreviewTest` throttle tests |

## Coverage: mandatory items M-1..M-10

| M | Plans | Evidence |
|---|---|---|
| M-1 | 10-02, 10-10 | `LoyaltyProgramTest`; grep gate clean |
| M-2 | 10-05 | `EarnLoyaltyPointsActionTest`, `LoyaltyEarnOnSettleTest`; one `catch` |
| M-3 | 10-12 | `LoyaltyReversalOnCancelTest::test_the_status_is_rechecked_under_the_row_lock`, `::test_the_cancel_action_keeps_its_return_contract` |
| M-4 | 10-05, 10-11, 10-12 | `LoyaltyReversalOnCancelTest::test_releasing_expired_holds_touches_no_loyalty_table`; zero commits touching either action |
| M-5 | 10-11 | `ApplyLoyaltyToReservationActionTest` |
| M-6 | 10-05, 10-07, 10-09, 10-11, 10-12, 10-13 | lock-order tests in `LoyaltyReversalOnCancelTest`, `LoyaltyBookingTest`, `LoyaltyRedeemTest`, `LoyaltyAdjustTest`, `ReverseLoyaltyForFolioActionTest` |
| M-7 | 10-10, 10-11 | `LoyaltyBookingTest` (fake money fields ignored), `LoyaltyPreviewTest` (extra keys ignored) |
| M-8 | 10-04 | `LoyaltySettingsTest::test_the_public_site_settings_table_is_untouched`; grep gate clean |
| M-9 | 10-01, 10-12 | `LoyaltySchemaTest`, `LoyaltyReversalOnCancelTest` |
| M-10 | 10-02, 10-04 | `LoyaltyLocaleTest`, `SeederTest`, `PermissionsGroupedTest`; lang additions only |

## Planning-state updates applied in the metadata commit
ROADMAP: 10-15 checked, "15/15 plans executed", progress row "15/15 In Progress" (left in progress on purpose: the orchestrator runs `phase complete` after verification). REQUIREMENTS: no change (all 22 already ticked, see the table). STATE: position, metrics, decision and session lines (only this plan's hunks; the Phase 9.1 paragraph was left unstaged).

## Self-Check: PASSED
- Docs commit `2c91cf5` exists on `main` (7 files); nothing pushed.
- `backend/docs/API_GUIDE_DASHBOARD.md`, `API_GUIDE_MOBILE.md`, `CHANGELOG_MOBILE_API.md`, `postman/carlton-api.postman_collection.json` and `docs/carlton-tree.html` contain the Loyalty content (verified by the plan's grep checks below).
- The temporary dump test `ZzTmpDumpTest.php` is deleted and was never committed.
