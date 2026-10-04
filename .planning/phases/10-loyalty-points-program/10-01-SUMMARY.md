---
phase: 10-loyalty-points-program
plan: 01
subsystem: database
tags: [laravel, loyalty, migrations, eloquent, ledger, fifo-batches, sqlite, phpunit]

requires:
  - phase: 09-night-audit-reports
    provides: committed Phase 9 base (82169a5); soft dependency only, no Phase 9 symbol is used
provides:
  - seven additive loyalty tables with every once-only backstop (earn once per folio and source, ledger idempotency key, single reversal, single application per reservation)
  - six string-backed loyalty enums with the Q16 API contract values
  - config/loyalty.php (restored_voucher_grace_days, max_adjust_points)
  - seven models, seven factories, Reservation::loyaltyApplication and Guest loyalty relations
  - Tests\Concerns\BuildsLoyaltyFixtures shared by every later Phase 10 test
affects: [10-02 through 10-15, loyalty earn, redeem, expiry, reversal, rewards CRUD, reports]

tech-stack:
  added: []
  patterns:
    - "Append-only ledger model: const UPDATED_AT = null, no LogsActivity, signed points"
    - "Snapshot columns on vouchers (type, reward_name, value_usd) so a purged reward never breaks history"
    - "Singleton settings row with no seeded rates; column defaults only for expiry_months 24 and expiry_warning_days 30"
    - "LogsActivity scoped with logOnly() on the audited lifecycle fields"

key-files:
  created:
    - backend/database/migrations/2026_10_05_100000_create_loyalty_settings_table.php
    - backend/database/migrations/2026_10_05_100100_create_loyalty_rewards_table.php
    - backend/database/migrations/2026_10_05_100200_create_loyalty_earn_batches_table.php
    - backend/database/migrations/2026_10_05_100300_create_loyalty_vouchers_table.php
    - backend/database/migrations/2026_10_05_100400_create_loyalty_ledger_entries_table.php
    - backend/database/migrations/2026_10_05_100500_create_loyalty_allocations_table.php
    - backend/database/migrations/2026_10_05_100600_create_loyalty_reservation_applications_table.php
    - backend/app/Enums/LoyaltyEntryType.php
    - backend/app/Enums/LoyaltyBatchStatus.php
    - backend/app/Enums/LoyaltyBatchSource.php
    - backend/app/Enums/LoyaltyRewardType.php
    - backend/app/Enums/LoyaltyVoucherStatus.php
    - backend/app/Enums/LoyaltyApplicationStatus.php
    - backend/config/loyalty.php
    - backend/app/Models/LoyaltySetting.php
    - backend/app/Models/LoyaltyReward.php
    - backend/app/Models/LoyaltyEarnBatch.php
    - backend/app/Models/LoyaltyLedgerEntry.php
    - backend/app/Models/LoyaltyAllocation.php
    - backend/app/Models/LoyaltyVoucher.php
    - backend/app/Models/LoyaltyReservationApplication.php
    - backend/database/factories/LoyaltySettingFactory.php
    - backend/database/factories/LoyaltyRewardFactory.php
    - backend/database/factories/LoyaltyEarnBatchFactory.php
    - backend/database/factories/LoyaltyLedgerEntryFactory.php
    - backend/database/factories/LoyaltyAllocationFactory.php
    - backend/database/factories/LoyaltyVoucherFactory.php
    - backend/database/factories/LoyaltyReservationApplicationFactory.php
    - backend/tests/Concerns/BuildsLoyaltyFixtures.php
    - backend/tests/Feature/Database/LoyaltySchemaTest.php
    - backend/tests/Unit/Loyalty/LoyaltyEnumsTest.php
  modified:
    - backend/app/Models/Reservation.php
    - backend/app/Models/Guest.php
    - backend/tests/Feature/Cms/RecycleBinRetentionTest.php

key-decisions:
  - "LoyaltyReward added to RecycleBinRetentionTest::SOFT_DELETABLE in this plan (not 10-08): the purge list is auto-discovered, so the SoftDeletes model turns the suite red the moment it exists"
  - "Standalone index on loyalty_rewards.sort_order in addition to (is_active, sort_order): CmsListIndexTest requires a leading-column index for every list ORDER BY column"
  - "Redundant single-column FK indexes omitted where a composite or unique index already leads with the column (vouchers.guest_id, applications.guest_id, allocations.ledger_entry_id)"
  - "loyalty_reservation_applications.voucher_id is a plain index, not unique (FA-10.01-3): a restored voucher can be applied to a later reservation"

patterns-established:
  - "Schema tests assert exact column lists via Schema::getColumnListing and index shapes via Schema::getIndexes, with expectException placed immediately before the violating statement so a missing table cannot pass vacuously"
  - "BuildsLoyaltyFixtures::grantPoints creates the batch and its matching ledger entry together so ledger and batches always agree"

requirements-completed: [LOY-02, LOY-04, LOY-08, LOY-17]

coverage:
  - id: D1
    description: "Seven loyalty tables with exact columns, defaults, no seeded settings row"
    requirement: "LOY-02"
    verification:
      - kind: integration
        ref: "tests/Feature/Database/LoyaltySchemaTest.php::test_tables_have_exactly_the_specified_columns, test_settings_table_is_empty_and_applies_column_defaults, test_other_column_defaults"
        status: pass
    human_judgment: false
  - id: D2
    description: "Database backstops: (folio_id, source) earn once-only, ledger idempotency_key, reverses_entry_id single reversal, application per reservation and per guest key, redeem entry"
    requirement: "LOY-04"
    verification:
      - kind: integration
        ref: "tests/Feature/Database/LoyaltySchemaTest.php (13 UniqueConstraintViolationException tests plus null-collision and shared-voucher allowances)"
        status: pass
    human_judgment: false
  - id: D3
    description: "Restrict and null-on-delete foreign keys protect ledger history and voucher snapshots"
    requirement: "LOY-17"
    verification:
      - kind: integration
        ref: "tests/Feature/Database/LoyaltySchemaTest.php::test_guest_referenced_by_a_batch_cannot_be_deleted and 4 sibling FK tests"
        status: pass
    human_judgment: false
  - id: D4
    description: "Enums, models, relations, casts, audit scope (LogsActivity on four models only), factories and BuildsLoyaltyFixtures"
    requirement: "LOY-08"
    verification:
      - kind: unit
        ref: "tests/Unit/Loyalty/LoyaltyEnumsTest.php and tests/Feature/Database/LoyaltySchemaTest.php model-layer tests"
        status: pass
    human_judgment: false

duration: 45min
completed: 2026-10-04
status: complete
---

# Phase 10 Plan 01: Loyalty Data Foundation Summary

**Seven additive loyalty tables (FIFO earn batches, append-only signed ledger, vouchers, reservation applications) with every once-only backstop enforced by unique indexes, plus six enums, seven models and factories, and the shared `BuildsLoyaltyFixtures` test trait.**

## Performance

- **Duration:** about 45 min
- **Completed:** 2026-10-04
- **Tasks:** 3 of 3
- **Files:** 34 in the code commit (31 created, 3 modified)

## Base and test counts

- **Phase 9 base:** HEAD `86b74b4` (Phase 9 commit `82169a5` is an ancestor).
- **Baseline full suite on the working tree before any change:** 2414 tests, 2413 passed, 1 failed (`ValidationMessageLocalizationTest`, caused by the concurrent Phase 9.1 session's uncommitted `DeleteGuestAccountRequest`; unrelated to this plan and fixed by that session during the run). Committed-tree baseline reported by the orchestrator: 2376 tests, 14853 assertions, green.
- **Final full suite (exit 0):** 2633 tests, 2633 passed, 16066 assertions. The count includes the concurrent session's in-flight tests; this plan adds 52 tests (45 in `LoyaltySchemaTest`, 7 in `LoyaltyEnumsTest`).

## Accomplishments

- Seven migrations roll back and re-apply cleanly on a scratch SQLite file (`migrate:fresh`, `migrate:rollback --step=7`, `migrate`); `backend/database/database.sqlite` was never touched (mtime and size unchanged).
- Unique backstops live in the database: `(folio_id, source)` earn once-only (LOY-04, NULL folios never collide), ledger `idempotency_key`, ledger `reverses_entry_id` (an entry is reversed at most once, LOY-17), voucher `code` and `reservation_id`, application `reservation_id`, `(guest_id, idempotency_key)` and `redeem_entry_id`, allocation `(ledger_entry_id, batch_id)`.
- Every guest, user, batch, reservation and folio FK is `restrictOnDelete`; only `loyalty_vouchers.loyalty_reward_id` is `nullOnDelete`, backed by snapshot columns.
- `LogsActivity` is on `LoyaltySetting`, `LoyaltyReward`, `LoyaltyVoucher` and `LoyaltyReservationApplication` only; the ledger, batches and allocations are unaudited by design (the ledger is the audit).
- No settings row is seeded; config carries only `restored_voucher_grace_days` (30) and `max_adjust_points` (1000000).

## Task Commits

All three tasks landed in one commit by project policy (never commit a red state; RED tests were written first, confirmed failing, then implemented):

1. **Tasks 1-3: feat(10-01)** - `baa5d13`

Metadata commit: see the `docs(10-01)` commit that follows.

## Decisions Made

See `key-decisions` above. The two that change later plans: `LoyaltyReward` is already in `RecycleBinRetentionTest::SOFT_DELETABLE` (10-08 step 8 becomes a verify-only step), and `loyalty_rewards` already has the `sort_order` index that `CmsListIndexTest` demands.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] RecycleBinRetentionTest red as soon as LoyaltyReward exists**
- **Found during:** full-suite run after Task 3
- **Issue:** `RecycleBin::modelsWithBin()` auto-discovers `SoftDeletes` models; `test_every_soft_deletable_model_is_covered_by_the_purge` pins a hard-coded list, so the new `LoyaltyReward` model broke the suite. Plan 10-08 owns this re-pin, but the project rule is a green suite before every commit.
- **Fix:** added `\App\Models\LoyaltyReward::class` (alphabetical, with a one-line comment) to `SOFT_DELETABLE`.
- **Files modified:** `backend/tests/Feature/Cms/RecycleBinRetentionTest.php`
- **Commit:** `baa5d13`

**2. [Rule 3 - Blocking] CmsListIndexTest requires a leading sort_order index**
- **Found during:** full-suite run
- **Issue:** the composite `(is_active, sort_order)` does not satisfy `loyalty_rewards.sort_order` ("every list column is indexed").
- **Fix:** added `$table->index('sort_order')` to the rewards migration (still uncommitted at the time) and asserted it in `LoyaltySchemaTest`.
- **Files modified:** `2026_10_05_100100_create_loyalty_rewards_table.php`, `LoyaltySchemaTest.php`
- **Commit:** `baa5d13`

**3. [Precondition adaptation] Dirty tree from the concurrent Phase 9.1 session**
- The plan's precondition `git status --porcelain -- backend ...` empty cannot hold while the Phase 9.1 session works in the same tree. The Phase 9 commit is verified as an ancestor of HEAD, and the project override scopes the check to this plan's footprint. `Guest.php` carried 26 uncommitted Phase 9.1 lines; only this plan's three `HasMany` methods were staged (via a blob built from `HEAD` plus this plan's hunk), so the Phase 9.1 edits remain unstaged in the working tree.

### Minor interpretation notes

- The plan lists voucher `guest_id` as "indexed" and applications `guest_id`/allocations `ledger_entry_id` as FKs; the composite indexes `(guest_id, status)`, `(guest_id, idempotency_key)` and `(ledger_entry_id, batch_id)` serve them through the leftmost prefix, so no duplicate single-column indexes were created.
- The empty `tests/Feature/Loyalty/` directory exists locally but is untracked (git ignores empty directories); 10-02 populates it.

## Issues Encountered

- Mid-run, the concurrent session's untracked Currency tests briefly produced a PHP fatal that aborted any run that loads the whole tests tree; it resolved without intervention and no foreign file was edited.

## Known Stubs

None. Factories and the fixture trait are test support; no production code path returns placeholder data.

## Threat Flags

None. The plan's threat register (T-10-01 through T-10-05) is fully mitigated by the schema and proved by `LoyaltySchemaTest`; no new network, auth or file surface was introduced.

## Next Phase Readiness

Plans 10-02 onward can read and write the seven tables through the models, factories and `BuildsLoyaltyFixtures` (`configureLoyalty`, `grantPoints`, `guestToken`, `staffToken`, `presetToken`, `generatedStay`, `loyaltyRowCounts`). Permission and seeder count pins are untouched by this plan and must be re-read at execution in the plans that change them.

## Self-Check: PASSED

- All 31 created files and 3 modified files exist and are in commit `baa5d13`.
- Commit `baa5d13` exists on `main`; nothing pushed.
- Full suite exit 0 (2633 tests) before the code commit.
