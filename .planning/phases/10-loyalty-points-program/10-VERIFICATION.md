---
phase: 10-loyalty-points-program
verified: 2026-10-07T00:00:00Z
status: human_needed
score: 7/7 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 6/7
  gaps_closed:
    - "ROADMAP Phase 10 obligation: loyalty balance forfeited on guest account deletion (LOY-23), activity counts PII-free"
  gaps_remaining: []
  regressions: []
gaps: []
deferred: []
behavior_unverified_items: []
human_verification:
  - test: "MySQL parallel same-key redeem: fire two simultaneous POST /api/loyalty/rewards/{reward}/redeem with one Idempotency-Key"
    expected: "One voucher, one redeem ledger entry, points spent once; the second request answers 200 with the same voucher"
    why_human: "SQLite in-memory tests are single connection; row locks and unique-key races only run on MySQL"
  - test: "MySQL parallel settle: settle one folio from two connections (SettleFolioAction and RecordFolioPaymentAction auto-settle)"
    expected: "Exactly one stay batch and one service batch per folio; the loser gets folio_settled or treats the unique violation as already earned"
    why_human: "Real concurrency on the folio lock and the (folio_id, source) unique key cannot be reproduced in SQLite tests"
  - test: "MySQL cancel racing a settle, parallel same-key bookings, and the new deletion-vs-cancel race (guest deletion racing a cancel of the same guest's reservation)"
    expected: "No negative balance, no deadlock 500, a single reservation; whichever of deletion and cancel wins, the guest ends with zero active batches and no active voucher; a second cancel or delete is a no-op"
    why_human: "Lock order (reservation -> folio -> guest -> batches -> vouchers on cancel; guest -> batches -> vouchers on deletion) is asserted by RecordsRowLocks, but deadlock and race behaviour needs a real engine"
  - test: "FCM: run php artisan loyalty:notify-expiring against a staging device token for a guest with preferred_locale ar"
    expected: "One push per guest per run in Arabic, none on the second run for the same batches"
    why_human: "Tests use a fake Firebase; real delivery and device rendering need a device"
---

# Phase 10: Loyalty Points Program Verification Report (re-verification)

**Phase Goal:** Guests earn integer points once per settled folio, see a FIFO-expiring balance and ledger, redeem catalog rewards into vouchers and pay part of a booking with points; cancellations undo every loyalty effect without ever producing a negative balance. Staff configure the six program values, manage the catalog, adjust points with an audited reason and report issued/redeemed/expired points. Deleting a guest account forfeits the loyalty balance.
**Verified:** 2026-10-07
**Status:** human_needed (all 7 truths verified; only the MySQL concurrency and real-FCM checks remain, and they cannot run on SQLite)
**Re-verification:** Yes, after gap closure plans 10-16 and 10-17. Truths 1 to 6 got a regression check (existence, wiring, tests); truth 7 got full verification.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1: earn once per settled folio inside the folio-locked transaction | VERIFIED (regression) | `EarnLoyaltyPointsAction` still wired at the three settle sites. The new deleted-guest skip is additive, placed after the cancelled skip. |
| 2 | SC2: guest account/ledger/rewards/vouchers; daily expiry and warning jobs | VERIFIED (regression) | Routes and schedule unchanged; the warning job gained a deleted-guest filter. |
| 3 | SC3: redeem and booking idempotent, preview equals total | VERIFIED (regression) | Loyalty suite green. |
| 4 | SC4: cancel refunds, restores voucher, claws back; second cancel no-op | VERIFIED (regression) | `ReverseLoyaltyForReservationAction` still returns the same shape; gained a post-reversal forfeit for deleted guests. |
| 5 | SC5: staff settings, rewards, adjust, reports under 3 permissions | VERIFIED (regression) | Adjust now refuses a deleted guest (below); permissions untouched. |
| 6 | SC6: contract gate (401/403/422, 5 locales, docs/Postman) | VERIFIED (regression) | `guest_account_deleted` already exists in en/ar/fr/tr/es (`lang/*/custom.php:144`); docs extended (below). |
| 7 | LOY-23: deleting a guest account forfeits the loyalty balance with a PII-free audit, and nothing re-attaches points to the deleted account | VERIFIED | See breakdown. |

**Score:** 7/7 truths verified (0 present-but-behavior-unverified)

### Truth 7 breakdown (read from code, not summaries)

| Obligation | Evidence | Status |
|------------|----------|--------|
| Forfeit in `DeleteGuestAccountAction` | Constructor injects `ForfeitLoyaltyBalanceAction`; called inside the existing transaction, under the guest `lockForUpdate`, after `assertDeletable` (a blocked deletion forfeits nothing) and before the scrub, inside `activity()->withoutLogging`. The `isDeleted()` early return keeps a repeat call a no-op. | VERIFIED |
| Real forfeit mechanism | `ForfeitLoyaltyBalanceAction` calls `LoyaltyLedger::forfeit()` (active batches ordered `expires_at, id`, locked, each expired through the existing `expire()` so key `expire:batch:{id}` is shared with the daily sweep) then closes active vouchers (`void`, or `expired` if past expiry), locked in order. Reads no program setting. | VERIFIED |
| Counts-only audit | `guest.account_deleted` carries `retained` plus `loyalty: {forfeited_points, expired_batches, closed_vouchers}`. `test_the_deletion_audit_entry_carries_loyalty_counts_only` asserts exactly one new activity row, exact counts, and no voucher code, phone, email or guest uuid in any activity row. | VERIFIED |
| Earn skipped for deleted guests | `EarnLoyaltyPointsAction` line 96: `guest?->isDeleted()` writes `loyalty.earn_skipped_deleted` (subject folio, anonymous causer, `skipped_points` only) and creates no batch. `test_a_folio_settled_after_the_deletion_earns_nothing` drives a real staff settle after a real deletion (stale no-show, no forced DB state) and asserts zero batches and unchanged loyalty row counts. | VERIFIED |
| Cancel after deletion takes back refunds | `ReverseLoyaltyForReservationAction` re-forfeits when the locked guest is deleted. Three tests: revived depleted batch, fresh refund batch, restored voucher voided. The starting state is reached through the real 9.1 flow (stale pending booking, no folio, deletion returns 200). | VERIFIED |
| Warning job skips deleted | `NotifyExpiringLoyaltyPointsAction`: query-level `whereDoesntHave('guest', DELETED)` plus an `isDeleted()` re-check under lock. `test_a_deleted_account_is_never_warned_and_an_active_guest_still_is`. | VERIFIED |
| Staff adjust refused with 422 `guest_account_deleted` | `AdjustLoyaltyPointsAction` throws `GuestAccountDeletedException` (error_code `guest_account_deleted`, status 422) after locking the guest, before any idempotency replay. Four tests (deduction with residue, localized, replay after erasure, direct call). | VERIFIED |
| Reports exclude deleted | `LoyaltyReportService::outstandingPoints()` filters deleted guests, same single statement; `liability_usd` derives from it. `test_outstanding_and_liability_leave_out_a_deleted_account`; forfeited points show under `expired_points`. | VERIFIED |
| `loyalty:forfeit-deleted` idempotent | Command exists (`app/Console/Commands/ForfeitDeletedLoyaltyBalances.php`), not scheduled, delegates to `ForfeitDeletedGuestBalancesAction` (per-guest transaction, `isDeleted()` re-check under lock, candidate query on active batches with points or active vouchers). Tests: leaves active guests alone, second run prints zeros, empty install, lock order, report effect. | VERIFIED |
| Tests exist and pass | `LoyaltyForfeitOnDeletionTest` (14), `LoyaltyForfeitDeletedCommandTest` (6), `ForfeitLoyaltyBalanceActionTest` (6), `LoyaltyLedgerForfeitTest` (9), plus additions to the adjust, warning and report tests. | VERIFIED |
| Docs updated | Mobile guide (account-deletion forfeit, irreversible), Dashboard guide (deleted-guest behaviour, `expired_points` includes forfeits, leftover points and the one-off command, no Phase 10 forfeit surprises), `CHANGELOG_MOBILE_API.md` entry 2026-10-07, `docs/MOBILE_FRONTEND_HANDOFF.md`. | VERIFIED |
| Protected files | `git diff 86b74b4 HEAD` for `QuoteReservationAction.php` and `ReleaseExpiredHoldsAction.php` is empty. | VERIFIED |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| All loyalty tests | `php artisan test --filter=Loyalty` (backend/) | 451 passed, 2728 assertions | PASS |
| Guest and account-deletion tests | `php artisan test --filter=Guest` (backend/) | 396 passed, 2254 assertions | PASS |

The full suite was not re-run here (caller reports 3085 passed); the two filtered runs cover every file touched by 10-16 and 10-17.

### Probe Execution

Step 7c: SKIPPED (no probe scripts declared).

### Requirements Coverage

LOY-01..LOY-22 remain satisfied (regression run green, 406 earlier tests now within 451). LOY-23 is satisfied in code and tests (Truth 7).

### Anti-Patterns Found

None blocking. No TBD/FIXME/XXX in files touched by 10-16/10-17. The earlier `TODO(refund flow)` info item is unchanged. Review items from `10-REVIEWS.md` that I checked in code: earn skip for deleted (done), cancel-after-deletion starting state is the real 9.1 flow with preconditions asserted (done), both refund outcomes tested (done), `loyalty:forfeit-deleted` added (done), lock-order test and the deadlock risk moved to manual MySQL verification (done, below).

### Human Verification Required

1. MySQL parallel same-key redeem (one voucher, points spent once).
2. MySQL parallel settle of one folio (one stay and one service batch).
3. MySQL cancel racing settle, parallel same-key bookings, and deletion racing cancel (no deadlock 500, no negative balance, end state zero active batches).
4. FCM delivery of `loyalty_points_expiring` to a staging device in Arabic, once per run.

### Bookkeeping for the orchestrator (not a gap)

`.planning/REQUIREMENTS.md` still shows LOY-23 as unchecked / "Pending" (lines 120 and 235), and ROADMAP.md still lists Phase 10 as in progress. Tick LOY-23 and complete the phase after the human items are accepted.

---

_Verified: 2026-10-07_
_Verifier: Claude (gsd-verifier)_
