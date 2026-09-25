---
phase: 3
slug: reservations-front-desk-verbs
# status lifecycle: draft (seeded by plan-phase) → validated (set by validate-phase §6)
# audit-milestone §5.5 distinguishes NOT-VALIDATED (draft) from PARTIAL (validated + nyquist_compliant: false) (#2117)
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-09-26
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5 (Laravel 13), SQLite `:memory:` via `backend/phpunit.xml` |
| **Config file** | `backend/phpunit.xml` |
| **Quick run command** | `cd backend && php artisan test --filter=Reservations` |
| **Full suite command** | `cd backend && php artisan test` |
| **Estimated runtime** | ~4 minutes (full suite, 1071+ tests) |

---

## Sampling Rate

- **After every task commit:** Run `cd backend && php artisan test --filter=Reservations`
- **After every plan wave:** Run `cd backend && php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 240 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 1 | RESV-01 | T-03-01 / — | Notes staff-only; guest resources omit `notes`; 422 over 2000 chars | feature | `cd backend && php artisan test --filter=ReservationNotesTest` | ❌ W0 | ⬜ pending |
| 03-02-01 | 02 | 1 | RESV-02 | T-03-02 / — | Only free rooms of the type; maintenance excluded; ≤3 queries | feature | `cd backend && php artisan test --filter=AvailableRoomsTest` | ❌ W0 | ⬜ pending |
| 03-03-01 | 03 | 2 | RESV-03 | T-03-03 / — | Check-in only from confirmed inside hotel-local window; early_check_in logged; assign-room never flips status | feature + unit | `cd backend && php artisan test --filter=CheckInTest` | ❌ W0 | ⬜ pending |
| 03-04-01 | 04 | 3 | RESV-04 | T-03-04 / — | 422 folio_unsettled; 403 force w/o folios.settle; forced override logged; rooms dirty; event after commit; guest express shares action | feature + unit | `cd backend && php artisan test --filter=CheckOutTest` | ❌ W0 | ⬜ pending |
| 03-05-01 | 05 | 4 | DOCS-01, XCUT-01 | — | n/a | manual + grep | tree/guide/Postman checks; summary states no new permissions | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*(Task IDs are provisional; the planner replaces this table with the real task list.)*

---

## Wave 0 Requirements

- [ ] `backend/tests/Feature/Reservations/ReservationNotesTest.php` — RESV-01
- [ ] `backend/tests/Feature/Reservations/AvailableRoomsTest.php` — RESV-02 (incl. `expectsDatabaseQueryCount`)
- [ ] `backend/tests/Feature/Reservations/CheckInTest.php` + `backend/tests/Unit/Booking/CheckInReservationActionTest.php` — RESV-03 (window, early_check_in, maintenance, dirty, GuestCheckedIn event, assign-room narrowed)
- [ ] `backend/tests/Feature/Reservations/CheckOutTest.php` + `backend/tests/Unit/Booking/CheckOutReservationActionTest.php` — RESV-04 (gate matrix, activity log, rooms dirty, event, guest express regression, settle/check-out concurrency)
- [ ] Rewrite: `tests/Feature/Booking/RoomAssignmentAtBookingTest.php`, `StayTest.php`, `ReservationTest.php`, `tests/Feature/Notification/NotificationTriggersTest.php` to the new assign-room / check-in semantics
- Existing factories (Reservation, ReservationRoom, Folio, Room, RoomType, Guest, User) and the per-class `staffToken()` helper cover the rest.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Guides, changelog, Postman and tree nodes updated; assign-room change announced | DOCS-01 | Documentation content | Open `docs/carlton-tree.html`; "check in · check out" and "reservation notes" are api:true; API guide Reservations module lists the four verbs and the breaking note |
| Summary states "no new permissions" and the dashboard switch to check-in | XCUT-01 | Summary format | Read the phase SUMMARY.md |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 240s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
