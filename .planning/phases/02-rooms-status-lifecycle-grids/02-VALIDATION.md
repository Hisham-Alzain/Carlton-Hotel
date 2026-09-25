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
| **Quick run command** | `cd backend && php artisan test --filter=Rooms` |
| **Full suite command** | `cd backend && php artisan test` |
| **Estimated runtime** | ~90 seconds (full suite, 966+ tests) |

---

## Sampling Rate

- **After every task commit:** Run `cd backend && php artisan test --filter=Rooms`
- **After every plan wave:** Run `cd backend && php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | ROOMS-02 | T-02-01 / — | Only rooms.status holders change status; invalid transition 422; history row per change | feature + unit | `cd backend && php artisan test --filter=RoomStatusTransitionTest` | ❌ W0 | ⬜ pending |
| 02-02-01 | 02 | 2 | ROOMS-01 | T-02-02 / — | Board readable only with rooms.status or reservations.view; ≤4 queries | feature | `cd backend && php artisan test --filter=RoomBoardTest` | ❌ W0 | ⬜ pending |
| 02-03-01 | 03 | 2 | ROOMS-03 | T-02-03 / — | Grid free equals /availability; ≤5 queries; days 1..31 | feature | `cd backend && php artisan test --filter=AvailabilityGridTest` | ❌ W0 | ⬜ pending |
| 02-03-02 | 03 | 2 | ROOMS-04 | T-02-03 / — | Rate cells match quote for one-night stays; read-only | feature + unit | `cd backend && php artisan test --filter=RatesGridTest` | ❌ W0 | ⬜ pending |
| 02-04-01 | 04 | 3 | DOCS-01, XCUT-01 | — | n/a | manual + grep | tree/guide/Postman checks; seeder diff shows rooms.status | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*(Task IDs are provisional; the planner replaces this table with the real task list.)*

---

## Wave 0 Requirements

- [ ] `backend/tests/Feature/Rooms/RoomStatusTransitionTest.php` — stubs for ROOMS-02 (transition table, 403 for wrong permission, 422 invalid/same-state, history row)
- [ ] `backend/tests/Feature/Rooms/RoomBoardTest.php` — stubs for ROOMS-01 (row shape, derived occupancy, `expectsDatabaseQueryCount`)
- [ ] `backend/tests/Feature/Rooms/AvailabilityGridTest.php` — stubs for ROOMS-03 (cells, agreement with `/availability`, param validation)
- [ ] `backend/tests/Feature/Rooms/RatesGridTest.php` and `backend/tests/Unit/PricingServiceNightlyRateTest.php` — stubs for ROOMS-04
- Existing infrastructure (factories for Room, RoomType, Reservation, ReservationRoom, PricingRule, Guest; seeder in `setUp`) covers the rest.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Guides, Postman and tree nodes updated | DOCS-01 | Documentation content | Open `docs/carlton-tree.html`; the three rooms nodes are api:true; API guide has the Front desk module |
| Summary lists `rooms.status` + presets and dashboard path changes | XCUT-01 | Summary format | Read the phase SUMMARY.md |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
