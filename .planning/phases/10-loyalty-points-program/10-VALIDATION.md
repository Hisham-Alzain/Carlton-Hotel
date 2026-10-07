---
phase: 10
slug: loyalty-points-program
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-10-04
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Source: `10-RESEARCH.md` "Validation Architecture", adjusted for consultant rulings in `10-CONTEXT.md` (ledger type `clawback`, earn skipped for cancelled reservations, three program capabilities).

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit ^12.5.12 on Laravel 13, SQLite in-memory, `QUEUE_CONNECTION=sync` |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `php artisan test --filter=Loyalty` (run from `backend/`) |
| **Full suite command** | `php artisan test` (run from `backend/`); style gate `composer run pint` |
| **Estimated runtime** | quick ~30 s, full ~several minutes (re-measure baseline at execution; Phase 9 notes disagreeing counts) |
| **Auth in tests** | real Sanctum bearer tokens, `RolesAndPermissionsSeeder` seeded in `setUp`, never `actingAs` |

---

## Sampling Rate

- **After every task commit:** `php artisan test --filter=Loyalty<Area>` for the area touched
- **After every plan wave:** `php artisan test --filter=Loyalty` plus regression files `FolioPaymentTest|FolioTest|CheckOutTest|ReservationTest|GuestBookingTest|ConcurrencyTest|SeederTest|PermissionsGroupedTest|CmsAccessControlTest|RecycleBinRetentionTest|LocaleFoundationTest|ValidationMessageLocalizationTest`
- **Before `/gsd-verify-work`:** full suite green and `composer run pint` clean
- **Max feedback latency:** 120 seconds for the quick command

---

## Per-Task Verification Map

Filled by the planner per task. Requirement-group sampling (authoritative detail in `10-RESEARCH.md`):

| Req group | Behaviours sampled | Test files | Command |
|-----------|--------------------|------------|---------|
| LOY-01/02/22 settings, inactive program | get/update, validation, unset rate/cap never 100%, activity log, 401/403/422 | `Feature/Loyalty/LoyaltySettingsTest`, `Unit/Loyalty/LoyaltyProgramTest` | `--filter=LoyaltySettings` |
| LOY-03/04 earn | 3 settle sites, per-bucket half-up, once-only, cancelled = no-op, inactive/no-guest no-op | `LoyaltyEarnOnSettleTest`, `EarnLoyaltyPointsActionTest`, `LoyaltyMathTest` | `--filter=LoyaltyEarn` |
| LOY-05 adjust | reason, idempotency, over-deduct 422 | `LoyaltyAdjustTest`, `AdjustLoyaltyPointsActionTest` | `--filter=LoyaltyAdjust` |
| LOY-06/07 account/ledger | expired-unswept excluded, own data only, pagination | `LoyaltyAccountTest`, `LoyaltyStaffGuestViewTest` | `--filter=LoyaltyAccount` |
| LOY-08/09/10 expiry + warning | boundary, month-end, DST, job idempotent, warn once per batch, localised | `LoyaltyExpiryTest`, `LoyaltyExpiryWarningTest` | `--filter=LoyaltyExpiry` |
| LOY-11/12 rewards | CRUD, AR/EN, recycle bin, public list | `LoyaltyRewardTest` | `--filter=LoyaltyReward` |
| LOY-13/14 redeem/vouchers | FIFO across batches, idempotent replay vs conflict, own vouchers | `LoyaltyRedeemTest`, `LoyaltyLedgerFifoTest` | `--filter=LoyaltyRedeem` |
| LOY-15/16 preview + booking | preview == booking total, min/cap edges, one voucher, conflict, replay | `LoyaltyPreviewTest`, `LoyaltyBookingTest` | `--filter=LoyaltyBooking` |
| LOY-17/18 reversals | refund to active/fresh batch, voucher restore, clawback shortfall, double cancel, hold release untouched | `LoyaltyCancelReversalTest`, `ReverseLoyaltyForFolioActionTest` | `--filter=LoyaltyReversal` |
| LOY-19 reports | sums per type in hotel-local window, outstanding, fixed query count | `LoyaltyReportTest` | `--filter=LoyaltyReport` |
| LOY-20/21 permissions, i18n, docs | seeded/grouped/enforced, 5-locale parity, count re-pins | `LoyaltyPermissionsTest`, `LoyaltyLocaleTest` | `--filter=LoyaltyPermissions` |
| Schema | up/down on scratch DB, unique/FK constraints | `Feature/Database/LoyaltySchemaTest` | `--filter=LoyaltySchema` |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Loyalty/`, `tests/Unit/Loyalty/` and shared `tests/Concerns/BuildsLoyaltyFixtures.php`
- [ ] Factories for the new loyalty models
- [ ] Re-pin plan for `SeederTest`, `PermissionsGroupedTest`, `RecycleBinRetentionTest` (after the Phase 9 commit)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| True concurrent double-redeem / double-settle | LOY-04, LOY-13 | SQLite in-memory cannot run real concurrency; lock order asserted via `RecordsRowLocks` instead | On MySQL staging fire two parallel requests with one `Idempotency-Key`; expect one effect |
| Push delivery of expiry warning | LOY-10 | Real FCM not available in tests (`FakeFirebaseService`) | Staging device receives one localised push per run |
| Deletion racing a staff cancel of the same guest (MySQL deadlock window) | LOY-23, LOY-17 | SQLite tests run on one connection and cannot deadlock; the deletion locks the guest first (9.1 D-09) and then writes the guest's reservations, while a cancel locks the reservation, then the folio, then the guest (M-6); reservations-first in the deletion was rejected (FA-10.16-6) | On MySQL staging take a guest with a stale pending points booking (check_out in the past, no folio). (a) Forced: in a mysql session start a transaction and lock that reservation row FOR UPDATE (the cancel's first step); send DELETE /api/auth/guest/me (it takes the guest lock and waits on the reservation write); in the session lock the guest row FOR UPDATE (the reversal's step). Expect InnoDB to report a deadlock (SQLSTATE 40001, error 1213) to one side and roll it back fully: if the API request is the victim it answers 500 and the guest is still active with its points and PII; repeating it succeeds. (b) Natural: fire DELETE /api/auth/guest/me and DELETE /api/cms/reservations/{uuid} in parallel about 20 times on fresh fixtures; every run ends with both succeeding, or one 500 that a retry fixes, and finally available_points 0 and no active voucher. Record the outcome in 10-16-SUMMARY.md |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 120s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
