---
phase: 10-loyalty-points-program
verified: 2026-10-05T00:00:00Z
status: gaps_found
score: 6/7 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification: false
gaps:
  - truth: "ROADMAP Phase 10 obligation: when a guest account is deleted (9.1 DeleteGuestAccountAction), the loyalty balance is forfeited (expire entries) and the deletion activity counts stay PII-free"
    status: failed
    reason: "Not implemented and not covered by any of the 15 plans. DeleteGuestAccountAction has no reference to loyalty; a deleted (anonymized) guest keeps active earn batches, unspent vouchers and ledger rows until the normal expiry sweep."
    artifacts:
      - path: "backend/app/Actions/Guest/DeleteGuestAccountAction.php"
        issue: "No loyalty forfeit step (no expire entries, no batch/voucher closure)"
      - path: "backend/app/Actions/Loyalty/NotifyExpiringLoyaltyPointsAction.php"
        issue: "Does not skip account_status=deleted guests, so the daily warning job can write new notification rows for an anonymized guest"
      - path: "backend/app/Services/Loyalty/LoyaltyReportService.php"
        issue: "outstanding_points / liability_usd include deleted guests' balances until the sweep expires them"
      - path: "backend/app/Http/Controllers/Admin/LoyaltyGuestController.php"
        issue: "Staff adjust on a deleted guest is not refused with guest_account_deleted (9.1 guards only notes and preferences)"
    missing:
      - "Forfeit step in DeleteGuestAccountAction (or a listener it calls): expire every active batch via LoyaltyLedger::expire, void/expire active vouchers, inside the deletion transaction with the guest lock"
      - "Deletion activity properties carry counts only (forfeited_points, expired_batches), no PII"
      - "Skip or refuse deleted guests in the expiry-warning job and in staff adjust"
      - "Tests: forfeit on delete, idempotent repeat delete, no negative balance, reports exclude forfeited points"
deferred: []
behavior_unverified_items: []
human_verification:
  - test: "MySQL parallel same-key redeem: fire two simultaneous POST /api/loyalty/rewards/{reward}/redeem with one Idempotency-Key"
    expected: "One voucher, one redeem ledger entry, points spent once; the second request answers 200 with the same voucher"
    why_human: "SQLite in-memory tests are single connection; row locks and unique-key races only run on MySQL"
  - test: "MySQL parallel settle: settle one folio from two connections (SettleFolioAction and RecordFolioPaymentAction auto-settle)"
    expected: "Exactly one stay batch and one service batch per folio; the loser gets folio_settled or treats the unique violation as already earned; settlement commits once"
    why_human: "Real concurrency on the folio lock and the (folio_id, source) unique key cannot be reproduced in SQLite tests"
  - test: "MySQL cancel racing a settle, and parallel bookings with one Idempotency-Key"
    expected: "No negative balance; cancel after settle claws back; a second cancel is a no-op; parallel bookings yield one reservation"
    why_human: "Lock order reservation -> folio -> guest -> batches is asserted by RecordsRowLocks but deadlock/race behaviour needs a real engine"
  - test: "FCM: run php artisan loyalty:notify-expiring against a staging device token for a guest with preferred_locale ar"
    expected: "One push per guest per run in Arabic, none on the second run for the same batches"
    why_human: "Tests use a fake Firebase; real delivery and device rendering need a device"
---

# Phase 10: Loyalty Points Program Verification Report

**Phase Goal:** Guests earn integer points once per settled folio, see a FIFO-expiring balance and ledger, redeem catalog rewards into vouchers and pay part of a booking with points; cancellations undo every loyalty effect without ever producing a negative balance. Staff configure the six program values, manage the catalog, adjust points with an audited reason and report issued/redeemed/expired points.
**Verified:** 2026-10-05
**Status:** gaps_found (one ROADMAP obligation unmet; all 22 LOY requirements and all 6 success criteria are met)
**Re-verification:** No, initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1: every settlement path credits half-up points per stay/service bucket inside the folio-locked transaction, once, never for cancelled reservations, nothing when `earn_rate` is unset | VERIFIED | `EarnLoyaltyPointsAction` is invoked inline after `status => SETTLED` at all three sites (`SettleFolioAction` lines 53 and 79, `RecordFolioPaymentAction` line 88). No other code sets a folio to SETTLED. It returns early when `earningEnabled()` is false, skips `CANCELLED` with the `loyalty.earn_skipped_cancelled` log, uses `LoyaltyMath::pointsForSpend` per bucket, and catches only `UniqueConstraintViolationException`. Idempotency keys `earn:folio:{id}:{bucket}` plus unique `(folio_id, source)` back it. `LoyaltyEarnOnSettleTest` (14 tests, incl. rollback on failure and cancelled-skip) passes. |
| 2 | SC2: `GET /api/loyalty/account\|ledger\|rewards\|vouchers` return only the caller's data with the agreed contract strings; expired-but-unswept points never available; daily jobs expire batches idempotently and warn each batch once in the guest's locale | VERIFIED | Guest routes (`routes/api.php` 630-638) carry no id; the controller uses `auth('guests')->user()`. `LoyaltyLedger::spendable()` always filters `expires_at > now()`; `expire()` re-reads under lock, unique `expire:batch:{id}` key. `NotifyExpiringLoyaltyPointsAction` marks `expiry_warned_at` in the same transaction, uses `preferred_locale`. Both commands scheduled in `routes/console.php` (01:00 and 09:00 hotel time). Ledger type/source enum strings match Q16. |
| 3 | SC3: redeem and `POST /api/reservations` with `loyalty_points`/`voucher_code` are idempotent under `Idempotency-Key`, enforce min/cap/one-voucher server-side, and preview equals booked `total_usd` | VERIFIED | `RedeemRewardAction` uses `IdempotentWrite` (200 replay, 409 mismatch). `CreateReservationAction` locks room_type then guest, replays via `loyalty_reservation_applications (guest_id, idempotency_key)`, and prices through the same `PriceLoyaltyRedemptionAction` that `PreviewLoyaltyAction` uses. Price action enforces below-minimum, over-cap with `max_points`, `loyalty_discount_conflict`, insufficient points. `StoreReservationRequest` requires the key only when loyalty fields are present. `test_the_booking_total_equals_the_preview_for_identical_inputs`, replay, conflict, rollback and client-discount-ignored tests pass. |
| 4 | SC4: cancel refunds spent points (original batch or fresh `refund` batch), restores the voucher, claws back folio earnings with recorded shortfall; second cancel no-op; `ReverseLoyaltyForFolioAction` unit-tested | VERIFIED | `CancelReservationAction` re-checks `isCancellable()` after `lockForUpdate()`, then calls `ReverseLoyaltyForReservationAction` in the same transaction; return stays `['data'=>null,'code'=>204]`. `LoyaltyLedger::refund` (Q3 revive rules), `clawback` (origin batch then FIFO, floor zero, `shortfall_points`), unique `reverses_entry_id`, application status flip give idempotence. `ReverseLoyaltyForFolioActionTest`, `LoyaltyLedgerReversalTest`, `LoyaltyReversalOnCancelTest` pass. |
| 5 | SC5: staff with `loyalty.view/manage/adjust` use `/api/cms/loyalty/*` (settings, rewards + bin, guest ledger, adjustments, reports); catalogue grows by 3 permissions / 1 group, presets unchanged | VERIFIED | 14 staff routes registered, gated per Q7 (`view\|manage` read, `view` guest/reports, `manage` writes, `adjust` for adjustments; bin under `cms.restore\|cms.purge`). Seeder adds exactly the three strings; `LoyaltyPermissionsTest` asserts no seeded role holds them. `UpdateLoyaltySettingsAction` writes only `loyalty_settings`, audited via `LogsActivity` on `LoyaltySetting`. `AdjustLoyaltyPointsAction` requires reason, is idempotent, logs `loyalty.points_adjusted`, deducts through `consume()` so it cannot exceed the available balance. |
| 6 | SC6: contract gate: happy / 401 / 403 / 422 per route, 5 locales, docs/Postman/tree updated, Flutter/React teams notified of contract strings and error codes | VERIFIED | Staff test files carry 401/403/422 cases; guest routes carry 401/422 (no 403 exists for a guest-only guard). 28 loyalty-related keys are identical across en/ar/fr/tr/es with no untranslated copies, and every `__('custom.*')` used in 49 loyalty source files resolves. API guides (Mobile and Dashboard, incl. error codes and Flutter/React notes), `CHANGELOG_MOBILE_API.md`, 23 Postman requests and 2 tree nodes (`api:true`) are present. |
| 7 | ROADMAP Phase 10 depends-on obligation: loyalty balance forfeited when `DeleteGuestAccountAction` deletes the account, activity counts PII-free | FAILED | `Grep` for loyalty in `app/Actions/Guest` returns nothing; `DeleteGuestAccountAction` is untouched by Phase 10. Acknowledged as an open follow-up in `10-15-SUMMARY.md`. Not a must_have of any plan, so it does not fault the 15 plans, but it is a ROADMAP "must add" and is observably absent. |

**Score:** 6/7 truths verified (0 present-but-behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| 7 migrations `2026_10_05_1000*..1006*` | Additive loyalty schema | VERIFIED | Additive only; FKs all explicit `restrictOnDelete`/`nullOnDelete`; uniques on ledger `idempotency_key`, `reverses_entry_id`, batch `(folio_id, source)`, voucher `code` and `reservation_id`, application `reservation_id` and `(guest_id, idempotency_key)` |
| `app/Support/LoyaltyLedger`, `LoyaltyMath`, `LoyaltyProgram` | Single mover of points, bcmath calculator, settings reader | VERIFIED | Substantive and wired; no `?? 100` / `?? 1.0` anywhere (only `min_redeem_points ?? 1`); getters throw `LoyaltyProgramInactiveException` on null |
| 11 actions in `app/Actions/Loyalty` | Earn, adjust, redeem, price, preview, apply, reverse x2, expire, notify, settings | VERIFIED | All have callers (settle sites, cancel, booking, controllers, commands) |
| 4 staff + 2 guest controllers, 7 requests, 7 resources, 5 services, 3 filters | Routes and shaping | VERIFIED | Wired in `routes/api.php`; `ReservationResource` carries the additive `loyalty` block |
| 8 `Loyalty*Exception` classes | Stable error codes | VERIFIED | Lang keys present in all 5 locales |
| `routes/console.php` schedule entries | Daily expiry and warning | VERIFIED | `loyalty:expire-points` 01:00, `loyalty:notify-expiring` 09:00, `withoutOverlapping` |
| `QuoteReservationAction`, `ReleaseExpiredHoldsAction` | Byte-unmodified | VERIFIED | `git diff --stat 86b74b4 HEAD` is empty for both; `test_releasing_expired_holds_touches_no_loyalty_table` exists |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `SettleFolioAction` (x2), `RecordFolioPaymentAction` | `EarnLoyaltyPointsAction` | Constructor injection, call after SETTLED update | WIRED | Inside the folio-locked transaction |
| `CancelReservationAction` | `ReverseLoyaltyForReservationAction` | Same transaction after status re-check | WIRED | Calls `ReverseLoyaltyForFolioAction` for clawback |
| `CreateReservationAction` | `PriceLoyaltyRedemptionAction`, `ApplyLoyaltyToReservationAction` | Only when `loyalty_points`/`voucher_code` present | WIRED | Staff and public-OTP bookings never apply loyalty (tested) |
| `PreviewLoyaltyAction` | `QuoteReservationAction` + `PriceLoyaltyRedemptionAction` | Shared pricing | WIRED | Same calculator as booking |
| `DeleteGuestAccountAction` | Loyalty forfeit | None | NOT_WIRED | The gap above |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|--------------------|--------|
| `LoyaltyAccountResource` | available, expiring_soon, next_expiry_at | `LoyaltyLedger::balances()` SUM over active unexpired batches | Yes | FLOWING |
| `LoyaltyReportResource` | issued, redeemed, expired, outstanding, liability | `LoyaltyReportService` aggregate over ledger/batches | Yes | FLOWING |
| `ReservationResource.loyalty` | points_redeemed, voucher | `loyaltyApplication.voucher` eager-loaded (`$with`) | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| All loyalty tests | `php artisan test --filter=Loyalty` (backend/) | 406 passed, 2411 assertions | PASS |
| Locale parity | PHP script over `lang/{en,ar,fr,tr,es}/custom.php` | 28 loyalty keys identical in all five, none equal to en | PASS |
| No missing translation keys | PHP script over 49 loyalty source files | 0 missing | PASS |
| Route registry | `php artisan route:list --path=loyalty` | 20 routes (6 guest, 14 staff) | PASS |

The full suite was not re-run (reported green at 2992 tests by the caller).

### Probe Execution

Step 7c: SKIPPED (no probe scripts declared by the phase plans).

### Requirements Coverage

Every LOY ID in the 15 PLAN frontmatters (union = LOY-01..LOY-22) exists in `.planning/REQUIREMENTS.md` (lines 98-119, all ticked). No orphaned IDs: REQUIREMENTS maps nothing else to this phase.

| Requirement | Source Plans | Status | Evidence |
|-------------|--------------|--------|----------|
| LOY-01 | 10-04, 10-15 | SATISFIED | Settings API with six values, audited (`LoyaltySetting` LogsActivity) |
| LOY-02 | 10-01, 02, 04, 05, 15 | SATISFIED | No seeded rates; capability getters throw; earn no-ops |
| LOY-03 | 10-05, 15 | SATISFIED | Three settle sites, half-up per bucket, cancelled skipped |
| LOY-04 | 10-01, 05, 15 | SATISFIED (concurrency: human) | Unique keys plus `UniqueConstraintViolationException` catch; sequential replay tests |
| LOY-05 | 10-07, 15 | SATISFIED | Reason required, idempotent, audited, deduction bounded by FIFO consume |
| LOY-06 | 10-06, 15 | SATISFIED | Guest account/ledger with reservation tie |
| LOY-07 | 10-06, 15 | SATISFIED | `GET /cms/loyalty/guests/{guest}` and `/ledger` |
| LOY-08 | 10-01, 02, 03, 06, 13, 15 | SATISFIED | Hotel-local end-of-day expiry, FIFO `expires_at, id`, `expires_at > now()` gate |
| LOY-09 | 10-13, 15 | SATISFIED | `ExpireLoyaltyBatchesAction`, unique expire key |
| LOY-10 | 10-13, 15 | SATISFIED (FCM: human) | Once-per-batch marker, guest locale |
| LOY-11 | 10-08, 15 | SATISFIED | Rewards CRUD, AR/EN translatable, bin |
| LOY-12 | 10-08, 15 | SATISFIED | Guest catalog (active only) |
| LOY-13 | 10-03, 09, 15 | SATISFIED (concurrency: human) | Transactional, idempotent, FIFO |
| LOY-14 | 10-09, 15 | SATISFIED | `GET /loyalty/vouchers` with status filter |
| LOY-15 | 10-10, 15 | SATISFIED | Read-only preview |
| LOY-16 | 10-10, 11, 15 | SATISFIED | Booking with points or voucher, atomic, replay returns same reservation |
| LOY-17 | 10-01, 03, 12, 15 | SATISFIED (race: human) | Refund, voucher restore, clawback with floor zero |
| LOY-18 | 10-12, 15 | SATISFIED | `ReverseLoyaltyForFolioAction` seam, unit-tested |
| LOY-19 | 10-14, 15 | SATISFIED | `GET /cms/loyalty/reports` |
| LOY-20 | 10-04, 07, 14, 15 | SATISFIED | 3 permissions seeded, route middleware, no preset change |
| LOY-21 | 10-02, 04, 06, 07, 08, 13, 14, 15 | SATISFIED | Locale parity, per-route tests, docs/Postman/tree |
| LOY-22 | 10-02, 04, 09, 10, 15 | SATISFIED | Unset value or cap gives `loyalty_program_inactive`; rewards unaffected |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `app/Actions/Loyalty/ReverseLoyaltyForFolioAction.php` | 32 | `TODO(refund flow)` with rationale (seam wired only from cancel, by ruling Q1) | Info | Not a debt marker (TBD/FIXME/XXX); no blocker |
| 8 `app/Exceptions/Loyalty*Exception.php` | n/a | Not Pint-clean one-liners | Info | Matches existing exception style (SUMMARY) |

No TBD/FIXME/XXX in any Phase 10 file. No stubs or hardcoded empty data found in loyalty sources.

### Human Verification Required

1. **MySQL parallel same-key redeem.** Expected: one voucher and one redeem entry; the second call returns 200 with the same voucher. Why human: needs a real engine.
2. **MySQL parallel settle of one folio** (both settle actions and the auto-settle payment). Expected: one stay and one service batch only. Why human: needs real concurrency.
3. **MySQL cancel racing settle, and parallel same-key bookings.** Expected: no negative balance, a single reservation. Why human: deadlock and lock-order behaviour.
4. **FCM delivery** of `loyalty_points_expiring` to a staging device in the guest's language, once per run. Why human: tests use a fake Firebase.

### Gaps Summary

One gap, and it is a ROADMAP obligation rather than a plan must_have: `DeleteGuestAccountAction` does not forfeit loyalty balances. The 15 plans themselves are fully delivered: all 22 LOY requirements are satisfied in code and covered by 406 passing loyalty tests, the six roadmap success criteria hold, and the mandatory prohibitions from `10-CONTEXT.md` check out (no default-to-100 cap, single caught exception type in earn, status re-check under lock in cancel, `QuoteReservationAction` and `ReleaseExpiredHoldsAction` unmodified, hold/pending bookings refused by apply, no client-supplied discount, no settings in `site_settings`).

Consequences of the gap until it is closed: a deleted guest's active batches linger until natural expiry, so `outstanding_points` and `liability_usd` are overstated; the 09:00 warning job can create notification rows for an anonymized guest; staff adjust is not refused on a deleted guest. Closure needs a small gap plan (forfeit step plus guards plus tests), run through `/gsd-plan-phase 10 --gaps`.

Bookkeeping for the orchestrator: ROADMAP.md still shows Phase 10 unchecked ("15/15 In Progress"); update it after the forfeit gap is closed.

---

_Verified: 2026-10-05_
_Verifier: Claude (gsd-verifier)_
