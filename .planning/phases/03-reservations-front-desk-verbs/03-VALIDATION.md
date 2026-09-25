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
| 03-01-01 | 01 | 1 | RESV-01 | T-03-01, T-03-04 | Notes contract incl. guest omission and the six RESV-01 probes (RED first) | feature | `cd backend && ! php artisan test --filter=ReservationNotesTest` | ❌ W0 (this task creates it) | ⬜ pending |
| 03-01-02 | 01 | 1 | RESV-01 | — | `messages.reservation_notes_updated` in five locales, parity kept | locale | `cd backend && php artisan test --filter=LocaleFoundationTest` | ✅ | ⬜ pending |
| 03-01-03 | 01 | 1 | RESV-01 | T-03-01, T-03-04, T-03-05, T-03-06, T-03-07 | PATCH notes staff-only, any status, 2000-char cap, one activity row per change; additive migration reversible on a scratch DB | feature | `cd backend && php artisan test --filter='ReservationNotesTest\|Tests\\Feature\\Booking'` + route check + scratch migrate/rollback | ✅ after 03-01-01 | ⬜ pending |
| 03-02-01 | 02 | 2 | RESV-02 | T-03-08, T-03-09, T-03-10, T-03-11 | Pick list, shared predicate/picker and hotel clock specs (RED first) | feature + unit | `cd backend && ! php artisan test --filter='AvailableRoomsTest\|RoomAvailabilityPredicateTest\|HotelClockTest'` | ❌ W0 (this task creates them) | ⬜ pending |
| 03-02-02 | 02 | 2 | RESV-02 | T-03-01, T-03-08, T-03-09, T-03-10 | Only free, active, non-maintenance rooms of the type; capacity rule; exactly 3 queries; booking pick unchanged | feature + unit | `cd backend && php artisan test --filter='AvailableRoomsTest\|RoomAvailabilityPredicateTest\|Tests\\Feature\\Booking'` + route check | ✅ after 03-02-01 | ⬜ pending |
| 03-02-03 | 02 | 2 | RESV-02 (D-02) | T-03-11 | Hotel-local business date; board and grid defaults follow it | unit + feature | `cd backend && php artisan test --filter='HotelClockTest\|Tests\\Feature\\Rooms'` | ✅ after 03-02-01 | ⬜ pending |
| 03-03-01 | 03 | 3 | RESV-03 | T-03-03, T-03-12 | Check-in contract: window, early override, resolution order, maintenance, notifications, board read (RED first) | feature + unit | `cd backend && ! php artisan test --filter='CheckInTest\|CheckInReservationActionTest'` | ❌ W0 (this task creates them) | ⬜ pending |
| 03-03-02 | 03 | 3 | RESV-03 | — | Two exceptions, GuestCheckedIn, three keys in five locales | lint + locale | `cd backend && php artisan test --filter=LocaleFoundationTest` + `php -l` | ✅ | ⬜ pending |
| 03-03-03 | 03 | 3 | RESV-03 | T-03-01, T-03-03, T-03-05, T-03-12 | Only confirmed, inside the hotel-local window (day-before override logged); locks reservation then room type; never writes room status; GuestCheckedIn after commit | feature + unit | `cd backend && php artisan test --filter='CheckInTest\|CheckInReservationActionTest\|Tests\\Feature\\Booking\|Tests\\Feature\\Notification'` + route check | ✅ after 03-03-01 | ⬜ pending |
| 03-04-01 | 04 | 4 | RESV-03 | T-03-14, T-03-15 | Narrowed assign-room spec plus in-place rewrites of the four legacy tests (method counts grow vs `b928abf`) (RED first) | feature | `cd backend && ! php artisan test --filter='AssignRoomTest\|RoomAssignmentAtBookingTest\|StayTest\|ReservationTest\|NotificationTriggersTest'` | ❌ W0 (this task creates / rewrites them) | ⬜ pending |
| 03-04-02 | 04 | 4 | RESV-03 | T-03-01, T-03-03, T-03-14 | assign-room never flips status; RoomAssigned only on a real move during a stay; shared predicate under lock | feature | `cd backend && php artisan test --filter='Tests\\Feature\\Booking\|Tests\\Feature\\Notification\|Tests\\Feature\\Reservations'` | ✅ after 03-04-01 | ⬜ pending |
| 03-05-01 | 05 | 5 | RESV-04 | T-03-02, T-03-03, T-03-16 | Check-out gate matrix, force, rooms dirty, event after commit, folio immutability, settle orderings; null-actor room status (RED first) | feature + unit | `cd backend && ! php artisan test --filter='CheckOutTest\|CheckOutReservationActionTest\|test_null_actor_records_a_system_change'` | ❌ W0 (this task creates them) | ⬜ pending |
| 03-05-02 | 05 | 5 | RESV-04 | — | CheckOutMode, FolioUnsettledException, ReservationCheckedOut, two keys in five locales | lint + locale | `cd backend && php artisan test --filter=LocaleFoundationTest` + `php -l` | ✅ | ⬜ pending |
| 03-05-03 | 05 | 5 | RESV-04 | T-03-01, T-03-02, T-03-03, T-03-05, T-03-16 | 422 folio_unsettled; 403 force without folios.settle; forced override logged, folio stays open; rooms dirty via the single writer (system actor); event after commit; GenerateFolio guard | feature + unit | `cd backend && php artisan test --filter='CheckOutTest\|CheckOutReservationActionTest\|UpdateRoomStatusActionTest\|Tests\\Feature\\Rooms\|Tests\\Feature\\Folio\|Tests\\Feature\\Reservations\|Tests\\Feature\\Booking'` + route check | ✅ after 03-05-01 | ⬜ pending |
| 03-06-01 | 06 | 6 | RESV-04 | T-03-02, T-03-05 | Guest express through the shared action; index filters (RED first) | feature | `cd backend && ! php artisan test --filter='ExpressCheckoutTest\|ReservationIndexFilterTest'` | ❌ W0 (this task creates them) | ⬜ pending |
| 03-06-02 | 06 | 6 | RESV-04 | T-03-03, T-03-05 | Guest approve = GUEST_EXPRESS check-out, guest marker with no causer, atomic on refusal | feature | `cd backend && php artisan test --filter='ExpressCheckoutTest\|Tests\\Feature\\Folio\|CheckOutTest'` | ✅ after 03-06-01 | ⬜ pending |
| 03-06-03 | 06 | 6 | RESV-04 (D-10) | T-03-01, T-03-17 | `status` / `folio_status` filters; bad folio_status 422 | feature | `cd backend && php artisan test --filter='ReservationIndexFilterTest\|Tests\\Feature\\Reservations\|Tests\\Feature\\Booking'` | ✅ after 03-06-01 | ⬜ pending |
| 03-07-01 | 07 | 7 | DOCS-01 | T-03-15, T-03-18 | Guide sections in order, breaking callout, error rows, changelog row, mobile push sentence | grep + node + test | verify block of 03-07 Task 1 (`guide order ok`, `docs ok`, PermissionGuideAccuracyTest) | ✅ | ⬜ pending |
| 03-07-02 | 07 | 7 | DOCS-01 | T-03-20 | Postman 17 requests in safe order; tree 94 nodes / 73 api:true / untouched hash | node | verify block of 03-07 Task 2 (`postman ok`, `tree ok`) | ✅ | ⬜ pending |
| 03-07-03 | 07 | 7 | DOCS-01, XCUT-01 | T-03-19, T-03-21 | Route guards, seeder unchanged vs `b928abf`, scratch migrate/rollback, full suite, summary contract sections | full suite + node | `cd backend && php artisan test` + verify block of 03-07 Task 3 | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*Every plan's Task 1 is its RED spec; a leading `!` in the command means the task passes when the spec fails.*

---

## Wave 0 Requirements

Each spec is created by the owning plan's Task 1 (RED first), not by a separate Wave 0 plan:

- [ ] `backend/tests/Feature/Reservations/ReservationNotesTest.php` — RESV-01 (03-01-01)
- [ ] `backend/tests/Feature/Reservations/AvailableRoomsTest.php` (incl. `expectsDatabaseQueryCount(3)`), `backend/tests/Unit/Booking/RoomAvailabilityPredicateTest.php`, `backend/tests/Unit/Booking/HotelClockTest.php`, plus one added method each in `tests/Feature/Rooms/RoomBoardTest.php` and `tests/Feature/Rooms/AvailabilityGridTest.php` — RESV-02 and D-02 (03-02-01)
- [ ] `backend/tests/Feature/Reservations/CheckInTest.php` + `backend/tests/Unit/Booking/CheckInReservationActionTest.php` — RESV-03 (window, early_check_in, maintenance, dirty, resolution order, GuestCheckedIn, board read, sequential races) (03-03-01)
- [ ] `backend/tests/Feature/Reservations/AssignRoomTest.php` + in-place rewrites of `tests/Feature/Booking/RoomAssignmentAtBookingTest.php`, `StayTest.php`, `ReservationTest.php`, `tests/Feature/Notification/NotificationTriggersTest.php` — RESV-03 assign-room narrowed (03-04-01)
- [ ] `backend/tests/Feature/Reservations/CheckOutTest.php` + `backend/tests/Unit/Booking/CheckOutReservationActionTest.php` + `UpdateRoomStatusActionTest::test_null_actor_records_a_system_change` — RESV-04 (gate matrix, activity log, rooms dirty, event after commit, settle/check-out orderings) (03-05-01)
- [ ] `backend/tests/Feature/Reservations/ExpressCheckoutTest.php` + `backend/tests/Feature/Reservations/ReservationIndexFilterTest.php` — RESV-04 guest express regression and D-10 filters (03-06-01)
- Existing factories (Reservation, ReservationRoom, Folio, Room, RoomType, Guest, User, DeviceToken, ServiceBooking), `Tests\Support\FakeFirebaseService` and the per-class `staffToken()` helper cover the rest. No framework install needed.

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
