---
phase: 08-events-dining
plan: 06
status: complete
---

# 08-06 Summary — Table reservation timezone fix (D-22)

## Built
- `ReserveTableAction`: `CarbonImmutable::createFromFormat('!Y-m-d H:i', "$date $time", HotelClock::timezone())->utc()`; `findFreeTable()` takes `CarbonInterface`. Docblock notes no backfill.
- `ReserveTableRequest`: `after_or_equal:` + `HotelClock::today()->toDateString()` (hotel-local today).

## Tests
- `tests/Feature/Dining/TableReservationTimezoneTest.php` (3, Asia/Damascus): 19:00 local → stored `2026-11-12 16:00:00`; at 22:30Z the hotel day is already the 11th (10th → 422, 11th → 201); 01:00 local → previous UTC day 22:00.
- `TableReservationTest` and `tests/Feature/Service` green unchanged (94).

## Notes
- `CreateServiceBookingAction` untouched (PR-9 gap, deferred). Existing table bookings keep their old instants (pre-production; no data migration).
