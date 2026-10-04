---
phase: 09-night-audit-reports
plan: 08
status: complete
---

# 09-08 Summary — reports period, occupancy, arrivals/departures

## Built
- `GET /api/reports/dashboard` — `auth:users` + `permission:reports.view` ("P9 — Reports" block) → `ReportController::dashboard` (defaults both dates to `HotelClock::today()`, never the audit business date).
- `ReportDashboardRequest`: `date_from`/`date_to` `bail|required_with:<other>|string|date_format:Y-m-d`; an `after()` hook (once both are valid) adds `after_or_equal` on reversal and `custom.validation.report_period_too_long` above 31 inclusive days.
- `app/Services/Reports/ReportService.php::dashboard($from, $to)`: `period {date_from, date_to, days, timezone}`, `generated_at` (Z), `occupancy {occupied_room_nights, available_room_nights, occupancy_rate}`, `arrivals`, `departures`.
  - occupancy: one `GROUP BY reservations.check_in, check_out` over `reservation_rooms ⋈ reservations` (confirmed/checked_in/checked_out, `whereDate` overlap), folded in PHP with half-open `[in, out) ∩ [from, to+1)`; capacity = active live rooms × days; rate `bcdiv(…, 4)`, `"0.0000"` with no rooms, not clamped.
  - arrivals/departures: two `whereDate` counts on reservations (once per reservation, no line join).
- Lang `custom.validation.report_period_too_long` already landed in 09-04 (all 5 locales).

## Tests
- `tests/Feature/Reports/ReportDashboardPeriodTest.php` (15 incl. provider): default = hotel today across the UTC date line, 31-day month, future allowed, 6 invalid shapes, 32 days → localized message (en + ar), 401, `night_audit.manage`-only 403, 7 presets 403, super admin 200.
- `tests/Feature/Reports/ReportOccupancyTest.php` (7): 2 lines × 2 nights over capacity 4, half-open edges, status filter, no rooms / overbooking `"3.0000"`, `Y-m-d 00:00:00` storage parity, arrivals/departures once per reservation, SQL guard (no julianday/datediff/date().

## Deviations
- `after_or_equal:date_from` replaced by an equivalent check in `after()`: Laravel's rule throws a `TypeError` (500) when `date_from` is an array — found by the `array` validation case.
- Arrivals/departures use two counts (Claude's discretion) — the report total stays within D-22 (see 09-09).
