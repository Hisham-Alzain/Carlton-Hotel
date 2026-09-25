---
phase: 2
slug: rooms-status-lifecycle-grids
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-09-25
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5 (Laravel 13), SQLite `:memory:` via `backend/phpunit.xml` |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter='Rooms|NightlyRate'` |
| **Full suite command** | `cd backend && php artisan test` |
| **Estimated runtime** | ~90 seconds (full suite, 966+ tests) |

---

## Sampling Rate

- **After every task commit:** Run the task's own `<verify>` command (RED tasks must fail, GREEN tasks must pass), then `cd backend && php artisan test --filter='Rooms|NightlyRate'`
- **After every plan wave:** Run `cd backend && php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | ROOMS-02 | T-02-01, T-02-03, T-02-05 | RED specs: transition matrix, 401/403/422/404, history row, no history on reject, availability independence, schema (dirty accepted, index kept) | feature + unit | `cd backend && ! php artisan test --filter='RoomStatusTransitionTest\|RoomStatusSchemaTest\|UpdateRoomStatusActionTest'` (must fail) | ❌ W0, created by this task | ⬜ pending |
| 02-01-02 | 01 | 1 | ROOMS-02 | T-02-01, T-02-03, T-02-05, T-02-06, T-02-07 | Only rooms.status holders (not cms.edit) change status; five D-04 edges; status + history in one transaction; PUT /cms/rooms ignores status; availability code untouched | feature + unit + route check | `cd backend && php artisan test --filter='RoomStatusTransitionTest\|RoomStatusSchemaTest\|UpdateRoomStatusActionTest\|SeederTest\|PermissionsGroupedTest\|RoomTest' --exclude-filter='test_transition_error_message_is_translated'` then the route:list check (`route ok`) | ✅ after 02-01-01 | ⬜ pending |
| 02-01-03 | 01 | 1 | ROOMS-02 | T-02-07 | Five-locale keys; migrate:fresh --seed, rollback --step=2, migrate on a scratch SQLite file; legacy rows mapped; dev database untouched | locale check + migration gate + full suite | Task 3 verify (`legacy rows after migrate: 0`, `dev database untouched`), then `cd backend && php artisan test` | ✅ | ⬜ pending |
| 02-02-01 | 02 | 2 | ROOMS-01 | T-02-02, T-02-04 | RED board spec: row shape, D-02 flags, adjacency/empty/ordering, filters, permissions, exact 4 queries, HTTP invariance | feature | `cd backend && ! php artisan test --filter=RoomBoardTest` (must fail) | ❌ W0, created by this task | ⬜ pending |
| 02-02-02 | 02 | 2 | ROOMS-01 | T-02-01, T-02-02, T-02-04, T-02-09 | Board readable only with rooms.status or reservations.view; no numeric ids or guest contact data; exactly 4 queries | feature + route check | `cd backend && php artisan test --filter='RoomBoardTest\|RoomStatusTransitionTest'` then the route check (`board route ok`) | ✅ | ⬜ pending |
| 02-03-01 | 03 | 3 | ROOMS-03 | T-02-03, T-02-04 | RED availability-grid spec: cell-by-cell agreement with /availability, out_of_order, status changes, boundaries, exact 4 queries | feature | `cd backend && ! php artisan test --filter=AvailabilityGridTest` (must fail) | ❌ W0, created by this task | ⬜ pending |
| 02-03-02 | 03 | 3 | ROOMS-03 | T-02-02, T-02-03, T-02-04 | free equals /availability per cell; days 1..31, from >= today-365; counts only; availability engine untouched | feature | `cd backend && php artisan test --filter='AvailabilityGridTest\|AvailabilityTest\|RoomBoardTest'` (`availability engine untouched`) | ✅ | ⬜ pending |
| 02-03-03 | 03 | 3 | ROOMS-04 | T-02-01, T-02-03, T-02-04, T-02-10 | Rate cells equal the one-night quote; inclusive rule window; two-decimal strings; read-only, exactly 2 queries; quote untouched | unit + feature + route check | `cd backend && php artisan test --filter='RatesGridTest\|PricingServiceNightlyRateTest\|PricingTest\|Rooms'` (`quote and availability untouched`, `grid routes ok`) | ❌ W0, written first inside this task (TDD) | ⬜ pending |
| 02-04-01 | 04 | 4 | DOCS-01, XCUT-01 | T-02-11 | Guides describe only what the router serves | grep + feature | Task 1 verify greps + `cd backend && php artisan test --filter=PermissionGuideAccuracyTest` | ✅ | ⬜ pending |
| 02-04-02 | 04 | 4 | DOCS-01 | T-02-13 | Postman and tree parse; only the three tree nodes change | node scripts | Task 2 verify (`postman ok`, `tree ok`) | n/a | ⬜ pending |
| 02-04-03 | 04 | 4 | DOCS-01, XCUT-01 | T-02-12, T-02-14 | Route contract, seeder names rooms.status 3x, scratch migration, summary contract with [BLOCKING] notes | route check + migration gate + full suite | Task 3 verify (`routes ok`, `scratch migration ok, dev database untouched`, `summary ok`) + `cd backend && php artisan test` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Sampling continuity: every task has an automated verify; RED tasks (02-01-01, 02-02-01, 02-03-01) are followed directly by the GREEN task that turns them green.*

---

## Wave 0 Requirements

Each RED file is written by the first task of its plan and must fail before the GREEN task starts.

- [ ] `backend/tests/Feature/Rooms/RoomStatusTransitionTest.php` (02-01-01) — ROOMS-02: preset wiring, five allowed edges, four rejected pairs with context, history row and denormalized columns, no history on reject, 401/403/422/404, availability unchanged by status changes, Arabic error message
- [ ] `backend/tests/Feature/Rooms/RoomStatusSchemaTest.php` (02-01-01) — ROOMS-02 schema: dirty accepted, default available, `rooms` status index kept, history table and columns
- [ ] `backend/tests/Unit/Rooms/UpdateRoomStatusActionTest.php` (02-01-01) — ROOMS-02 unit: enum values, 3x3 transition matrix, action return shape, domain exception with nothing written
- [ ] Updates to `backend/tests/Feature/SeederTest.php` (19 permissions, presets), `backend/tests/Feature/Staff/PermissionsGroupedTest.php` (9 groups) and `backend/tests/Feature/Cms/RoomTest.php` (PUT ignores status) (02-01-01)
- [ ] `backend/tests/Feature/Rooms/RoomBoardTest.php` (02-02-01) — ROOMS-01: row shape, D-02 derivation, adjacency/empty/ordering, filters, no ids, permissions, `expectsDatabaseQueryCount(4)` on the service, HTTP invariance
- [ ] `backend/tests/Feature/Rooms/AvailabilityGridTest.php` (02-03-01) — ROOMS-03: cell-by-cell agreement with `/public/availability`, out_of_order, status-change independence, boundaries, permissions, `expectsDatabaseQueryCount(4)`
- [ ] `backend/tests/Feature/Rooms/RatesGridTest.php` and `backend/tests/Unit/Booking/PricingServiceNightlyRateTest.php` (02-03-03, written first inside the task) — ROOMS-04: quote agreement in both rule orders, inclusive window, precision, read-only, exactly 2 queries
- No framework install: PHPUnit 12.5 and `RefreshDatabase` are configured; each new feature class copies the private `staffToken()` helper (there is none on `Tests\TestCase`). Existing factories (Room, RoomType, Reservation with its status states, ReservationRoom, PricingRule, Guest, User) cover every fixture.
- Migration proof outside PHPUnit: the scratch-SQLite gate in 02-01-03 and 02-04-03 (`migrate:fresh --seed`, `migrate:rollback --step=2`, `migrate`).

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Tree renders the three flipped nodes; Postman imports with folder 18 | DOCS-01 | Rendering and import are visual (the structure itself is checked by the 02-04-02 scripts) | Open `docs/carlton-tree.html` in a browser (no console error, three Rooms & inventory nodes API-covered); import the Postman collection and run the Rooms status requests top to bottom |
| Summary lists `rooms.status` + presets, the path changes and both [BLOCKING] deploy notes | XCUT-01 | Summary wording (the headings are checked by 02-04-03) | Read `02-04-SUMMARY.md` |
| Behaviour on MySQL (row lock serialisation, native ALTER MODIFY) | ROOMS-02 | The suite runs on SQLite only | Backstop truth in 02-01; verify on a staging MySQL database after `php artisan migrate` |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
