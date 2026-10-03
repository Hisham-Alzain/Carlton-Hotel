---
phase: 08-events-dining
plan: 01
status: complete
---

# 08-01 Summary — Data foundation

## Baseline gate
- First full run on the merged tree (ae387c9) **crashed** ("Premature end of PHP process" in `GuestLogoutTest`): the owner's merge left `StayService` with two `__construct()` declarations (PHP fatal) and a duplicate `POST /auth/guest/logout` route.
- Minimal fixes (outside Phase 8 scope, merge repair only):
  - `app/Services/Booking/StayService.php`: merged the two constructors (`SubmitOnlineCheckInAction` + check-in action).
  - `routes/api.php`: removed the duplicated guest `logout` line.
  - The merged self check-in (`POST /stays/check-in`) called `AssignRoomAction`, which by Phase 3 D-03 never checks in, so its own test (`StayTest::test_guest_can_self_check_in_…`) returned 500. It now calls `CheckInReservationAction` (the only `confirmed → checked_in` writer, D-01) with a null actor; `CheckInReservationAction::handle()`'s `$actor` became `?User` (only used for the early-check-in activity log, which this path never takes).
- Baseline after the repair: **2028 tests green** (2023 at 0961153 + 5 from the merge).
- `sha256(backend/database/database.sqlite)` = `80045d03c9d2c09a8a1891c6a09d27bcee1fef9c02b0bacabe592ca032a60925` (committed by the merge; untouched by Phase 8).

## Built
- Migrations `2026_10_02_100000..100300`: `event_inquiries.staff_notes/deposit_status/deposit_paid_at` (+ index), `event_inquiry_checklist_items` (unique `(event_inquiry_id,item)`, `completed_by` restrictOnDelete), `media.collection` default `images` + `(mediable_type, mediable_id, collection)` index, `service_bookings (bookable_type, scheduled_at)` index. Scratch SQLite `migrate:fresh` → `rollback --step=4` → `migrate` clean.
- Enums `EventChecklistItem` (label/ownerDepartment/isDerived), `EventDepositStatus`.
- Model `EventInquiryChecklistItem` (HasUuid, LogsActivity); `EventInquiry` gains `checklistItems`, `payments`, `depositPayment`; `DiningVenue::images()` scoped to `images`, new `menuFile()`; `Media.collection` fillable + model default.
- Factories: `EventInquiryChecklistItemFactory` (`completed()`), `EventInquiryFactory::quoted/confirmed/cancelled/withDeposit`.
- Lang (5 locales, delegated to a Sonnet agent, verified): all Phase 8 keys at once — `event_checklist.*`, 5 messages, 2 errors, `validation.date_range_max`.

## Tests
- `tests/Feature/Database/EventsDiningSchemaTest.php` (7), `tests/Unit/Events/EventChecklistItemTest.php` (5). RED → GREEN. Media suites unchanged and green.

## Deviations
- **FA-8.01-1 resolved the other way:** `->where(...)->latestOfMany()` is wrong, not just unsupported — the outer `where` is applied after the `max(id)` pick, so a later non-matching row hides the match (a failed payment hid the deposit; an image uploaded after the menu would hide the menu). Both relations use `ofMany(['id' => 'max'], fn ($q) => $q->where(...))`; pinned by tests.
- `$attributes` defaults on `EventInquiry` (`deposit_status`) and `Media` (`collection`) so freshly created, unrefreshed models read the column default.
