---
phase: 8
slug: events-dining
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-10-02
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Source: `08-RESEARCH.md` → Validation Architecture, amended by PR-1..PR-9 in `08-CONTEXT.md`.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12 via `php artisan test` (Laravel 13), SQLite `:memory:`, `QUEUE_CONNECTION=sync` |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter='Event|TableReservation|Menu'` |
| **Full suite command** | `cd backend && php artisan test` (serial) |
| **Estimated runtime** | ~5 minutes (2023 tests green at `0961153`, the phase base) |
| **Lock proofs** | `tests/Concerns/RecordsRowLocks.php` (SQL carries `for update`; serialisation MySQL-only — Phase 7 A9 caveat) |
| **Query budgets** | `expectsDatabaseQueryCount(n)` around the service call: inquiry list ≤ 6, show ≤ 9, table reservations ≤ 6, menu download ≤ 2 (D-30, hard caps) |
| **Files** | `Storage::fake('public')`; purge via `Media::deleted` → `PurgeMediaFile` after commit |
| **Time** | Carbon test time + `config(['hotel.timezone' => …])` (Asia/Damascus; Europe/London for the DST unit test, PR-5) |

## Sampling Rate

- **After every task:** the touched test files, plus `LocaleFoundationTest` whenever a lang file changed.
- **After every plan:** full suite — every GREEN task ends on it.
- **Before the orchestrator commits:** full suite green and the 08-10 gate.
- **Max feedback latency:** 300 seconds.

## Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | Plan |
|--------|----------|-----------|-------------------|------|
| EVENT-01 | checklist tick/untick, explicit done, no-op, lazy rows + unique backstop, derived deposit 422, unknown 404, cancelled 422 + context, lock, 401/403/422 | feature | `php artisan test tests/Feature/Events/EventChecklistTest.php` | 08-04 |
| EVENT-01 | enum order, labels × 5 locales, owner, derived | unit | `php artisan test tests/Unit/Events/EventChecklistItemTest.php` | 08-01 |
| EVENT-01/02/03 | detail shape (checklist[], deposit{}, staff_notes, assigned_user), list keys, budgets, inquiry_state context | feature | `php artisan test tests/Feature/Events/EventInquiryDetailTest.php` | 08-03 |
| EVENT-02 | deposit happy, FQCN payable, replay 200 ×1 row, 409 conflict (amount/note/recorder), missing key 422, amount/method/note 422, already-recorded 422, status 422, no auto-confirm, folio isolation, lock, 401/403 | feature | `php artisan test tests/Feature/Events/EventDepositTest.php` | 08-05 |
| EVENT-02 | status ⇔ ledger invariant | unit | `php artisan test tests/Unit/Events/RecordEventDepositActionTest.php` | 08-05 |
| EVENT-03 | staff_notes set/clear, guest notes untouched, cancelled allowed, 5000 cap, present rule, activity, 401/403 | feature | `php artisan test tests/Feature/Events/EventNotesTest.php` | 08-04 |
| DINING-01 | tz fix: 19:00 Damascus → 16:00Z, hotel-local today | feature | `php artisan test tests/Feature/Dining/TableReservationTimezoneTest.php` | 08-06 |
| DINING-01 | list default today, date, range, 31-day cap, combos 422, venue/table/status, other types excluded, trashed venue, orphan table, order, shape, per_page, budget, 401/403 | feature | `php artisan test tests/Feature/Dining/TableReservationIndexTest.php` | 08-07 |
| DINING-01 | window maths across DST (Europe/London) | unit | `php artisan test tests/Unit/Dining/TableReservationFilterTest.php` | 08-07 |
| DINING-02 | menu-file upload/replace/delete, purge, 422, isolation from images/attach/library, force-delete purge, 401/403 | feature | `php artisan test tests/Feature/Dining/VenueMenuFileTest.php` | 08-08 |
| DINING-02 | public download 200/204/404 (unknown, inactive, trashed), latest wins, budget | feature | `php artisan test tests/Feature/Dining/MenuDownloadTest.php` | 08-09 |
| XCUT | schema additive, media default, venue relations | feature | `php artisan test tests/Feature/Database/EventsDiningSchemaTest.php` | 08-01 |
| XCUT | events.* matrix, reception/concierge 403, summary key on events.view, 29/12 | feature | `php artisan test tests/Feature/Events/EventInquiryPermissionsTest.php tests/Feature/Staff/RolePresetsTest.php tests/Feature/SeederTest.php tests/Feature/Staff/PermissionsGroupedTest.php tests/Feature/Operations/DashboardSummaryTest.php tests/Feature/Events/EventInquiryTest.php` | 08-02 |
| Regression | media suite unchanged, table reservation booking unchanged, folio payments unchanged | feature | `php artisan test tests/Feature/Cms tests/Feature/Service tests/Feature/Folio` | 08-01, 08-05, 08-06, 08-08 |
| DOCS | guide accuracy, seeders | feature | `php artisan test tests/Feature/Docs tests/Feature/SeederTest.php` | 08-10 |

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 08-01-01 | 01 | 1 | all | T-08-01 | Baseline green; schema/enum specs RED | feature+unit | `! php artisan test --filter='EventsDiningSchemaTest\|EventChecklistItemTest'` | ❌ W0 | ⬜ pending |
| 08-01-02 | 01 | 1 | all | T-08-01, T-08-02 | Additive migrations, media tests unchanged | feature+unit | see plan verify (`foundation ok`) | ✅ after 01-01 | ⬜ pending |
| 08-02-01 | 02 | 2 | EVENT-*, XCUT | T-08-03 | Matrix + re-pins RED | feature | `! php artisan test --filter='EventInquiryPermissionsTest\|RolePresetsTest\|…'` | ❌ W0 | ⬜ pending |
| 08-02-02 | 02 | 2 | EVENT-*, XCUT | T-08-03, T-08-04 | events.* gates; summary on events.view | feature | `events gate ok` | ✅ | ⬜ pending |
| 08-03-01 | 03 | 3 | EVENT-01..03 | T-08-05 | Detail/list/budget specs RED | feature | `! php artisan test --filter=EventInquiryDetailTest` | ❌ W0 | ⬜ pending |
| 08-03-02 | 03 | 3 | EVENT-01..03 | T-08-05, T-08-06 | No queries in resources; budgets | feature | `inquiry detail ok` | ✅ | ⬜ pending |
| 08-04-01 | 04 | 4 | EVENT-01, 03 | T-08-07 | Specs RED | feature | `! php artisan test --filter='EventChecklistTest\|EventNotesTest'` | ❌ W0 | ⬜ pending |
| 08-04-02 | 04 | 4 | EVENT-01, 03 | T-08-07, T-08-08 | Explicit done; derived never stored; lock | feature | `checklist notes ok` | ✅ | ⬜ pending |
| 08-05-01 | 05 | 5 | EVENT-02 | T-08-09..12 | Money specs RED | feature+unit | `! php artisan test --filter='EventDepositTest\|RecordEventDepositActionTest'` | ❌ W0 | ⬜ pending |
| 08-05-02 | 05 | 5 | EVENT-02 | T-08-09..12 | Replay-safe single deposit; shared money code untouched | feature+unit | `deposit ok` | ✅ | ⬜ pending |
| 08-06-01 | 06 | 6 | DINING-01 | T-08-13 | tz regression RED | feature | `! php artisan test --filter=TableReservationTimezoneTest` | ❌ W0 | ⬜ pending |
| 08-06-02 | 06 | 6 | DINING-01 | T-08-13 | UTC instants; generic booking untouched | feature | `tz ok` | ✅ | ⬜ pending |
| 08-07-01 | 07 | 7 | DINING-01 | T-08-14, 15 | List specs RED | feature+unit | `! php artisan test --filter='TableReservationIndexTest\|TableReservationFilterTest'` | ❌ W0 | ⬜ pending |
| 08-07-02 | 07 | 7 | DINING-01 | T-08-14, 15 | Bounded window; no auth in filter | feature+unit | `table list ok` | ✅ | ⬜ pending |
| 08-08-01 | 08 | 8 | DINING-02 | T-08-16..18 | Menu-file specs RED | feature | `! php artisan test --filter=VenueMenuFileTest` | ❌ W0 | ⬜ pending |
| 08-08-02 | 08 | 8 | DINING-02 | T-08-16..18 | PR-1 isolation; media tests unchanged | feature | `menu file ok` | ✅ | ⬜ pending |
| 08-09-01 | 09 | 9 | DINING-02 | T-08-19 | Download specs RED | feature | `! php artisan test --filter=MenuDownloadTest` | ❌ W0 | ⬜ pending |
| 08-09-02 | 09 | 9 | DINING-02 | T-08-19, 20 | 200/204/404 | feature | `menu download ok` | ✅ | ⬜ pending |
| 08-10-01 | 10 | 10 | DOCS-01 | T-08-21 | Guides/Postman/tree | docs+feature | `docs ok` | ✅ | ⬜ pending |
| 08-10-02 | 10 | 10 | XCUT-01 | T-08-21 | Gate + coverage | script | `gate ok` | ✅ | ⬜ pending |

## Decision coverage (D-01..D-32, PR-1..PR-9 → plan)

| Id | Plan(s) | Id | Plan(s) | Id | Plan(s) |
|---|---|---|---|---|---|
| D-01 | 01, 03, 04 | D-12 | 02, 05 | D-23 | 07 |
| D-02 | 01, 04 | D-13 | 07 | D-24 | 07 |
| D-03 | 01, 03 | D-14 | 02 | D-25 | 07 |
| D-04 | 03, 04, 05 | D-15 | 05 | D-26 | 09 |
| D-05 | 01, 03, 05 | D-16 | 05 | D-27 | 08 |
| D-06 | 01 (no-change, asserted by schema test) | D-17 | 05 | D-28 | 04, 05 |
| D-07 | 01, 08 | D-18 | 05, 10 | D-29 | 03 |
| D-08 | 01 | D-19 | 04 | D-30 | 03, 07, 09 |
| D-09 | 04, 05 | D-20 | 04 | D-31 | 01–09 |
| D-10 | 07 | D-21 | 03 | D-32 | 10 |
| D-11 | 08, 09 | D-22 | 06, 10 | | |
| PR-1 | 08 | PR-4 | 07 | PR-7 | 03, 07 |
| PR-2 | 02 | PR-5 | 07 | PR-8 | 05 |
| PR-3 | 07 | PR-6 | 03, 04 | PR-9 | 10 (docs/known gap) |

Claude's Discretion items: file names (all plans), `EventInquiryService` not migrated (PR-6), Idempotency-Key trait (08-05, conditional), demo seeder (08-10), menu DELETE-none = 404 (08-08), user-load collapse optional (08-03).

## Wave 0 Requirements

Every plan's Task 1 creates its own failing test file(s) before code (listed above). No shared fixtures are needed beyond the factories added in 08-01.

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Row-lock serialisation of concurrent deposits | EVENT-02 | SQLite ignores `FOR UPDATE` | On MySQL, fire two deposit PATCHes with different keys concurrently; exactly one 200, one 422 `event_deposit_already_recorded` |
| Flutter opens the menu URL externally | DINING-02 | client behaviour | Tap "download menu" in the guest app against a seeded venue |

## Validation Sign-Off

- [x] All tasks have `<automated>` verify
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 300s per targeted run
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
