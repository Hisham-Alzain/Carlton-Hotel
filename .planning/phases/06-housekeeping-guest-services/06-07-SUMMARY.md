---
phase: 06-housekeeping-guest-services
plan: 07
subsystem: departure services list + departure catalogue categories
requires: [06-06]
provides: [GET /departure-services, DepartureServiceProjection, late_checkout + luggage direct categories, ServiceBookingStatus::allowedTargets, ServiceBookingFactory::transfer, BaseRequest before_or_equal message]
key-files:
  created:
    - backend/app/Support/DepartureServiceProjection.php
    - backend/app/Services/Operations/DepartureServiceService.php
    - backend/app/Http/Requests/Operations/IndexDepartureServicesRequest.php
    - backend/app/Http/Controllers/Admin/DepartureServiceController.php
    - backend/tests/Feature/Operations/DepartureServicesTest.php
  modified:
    - backend/database/seeders/GuestServiceCatalogSeeder.php
    - backend/app/Enums/Department.php
    - backend/app/Enums/ServiceBookingStatus.php
    - backend/database/factories/ServiceBookingFactory.php
    - backend/app/Base/BaseRequest.php
    - backend/lang/{en,ar,es,fr,tr}/custom.php
    - backend/routes/api.php
    - backend/tests/Feature/Service/ServiceCatalogTest.php
completed: 2026-09-27
---

# 06-07 — Departure services list and the two new catalogue categories

## Shipped
- `GuestServiceCatalogSeeder`: direct categories `late_checkout` (reception, icon `late_checkout`, sort 8) and `luggage` (concierge, icon `luggage`, sort 9), one `is_default` item each with `price_usd = null`; idempotent (10 categories after two runs).
- `Department::forServiceType()`: `late_checkout → RECEPTION`, `luggage → CONCIERGE` explicit.
- `ServiceBookingStatus::allowedTargets()` / `canTransitionTo()` (pending → confirmed|cancelled, confirmed → completed|cancelled, terminal otherwise). `ServiceBookingFactory::transfer()` state.
- `DepartureServiceProjection` (`KINDS`, `REQUEST_KINDS`, `LIMIT = 500`): departing set = reservations `checked_in|checked_out` with `whereDate(check_out, D)` or `checked_out_at` in `HotelClock::dayWindow(D)` as a half-open UTC range (no `date()` on the timestamp); transfers (`bookable_type = transfer`, `scheduled_at ≥` D's hotel midnight so arrival pickups drop out), late_checkout / luggage requests, guest-express stays; each source capped at 501; stage / allowed_statuses from the source's own enum; sort scheduled_at asc nulls last, created_at, uuid; `meta {count, truncated}`. `row(source, ?reservation)` also serves a single refreshed row (06-08).
- `IndexDepartureServicesRequest`: `date` Y-m-d within HotelClock today ± 30; `kind` / `status` accept single, comma list, repeated or `[in]` forms, flattened in `prepareForValidation()` so errors are keyed `kind` / `status`.
- `GET /departure-services` (`auth:users`, `service_requests.view`) → `DepartureServiceController::index` via `success()` (unpaginated `data.items` + `data.meta`).

## Deviations from PLAN.md
- `BaseRequest::messages()` gained `before_or_equal` and `custom.validation.before_or_equal` was added in all five locales: `ValidationMessageLocalizationTest` requires every reachable rule to be mapped (the date upper bound is the first `before_or_equal` in the codebase).
- The scratch-DB gate used a file in the session scratchpad instead of `storage/framework/` (same effect, nothing left in the repo).
- Extra test assertions: `kind=late_checkout,luggage` comma form; `date=2027-04-11` (exactly +30) accepted.

## Flagged assumptions
- FA-6.07-1..5 as planned (half-open day; express rows `status = completed`; requests have null `scheduled_at` and sort last; per-source cap makes `truncated` conservative across sources; double-tapped chip creates two requests — unchanged guest contract, flagged for mobile).

## Verification
- RED observed (routes/categories missing).
- `DepartureServicesTest` (14 list methods) + `ServiceCatalogTest` (ten-category re-pin + 6 new): green. ≤ 7 queries on the service path with all four kinds (actual: 5); `lockedSelects` = [] and row counts unchanged by a GET.
- Scratch SQLite: `migrate:fresh --seed` then `db:seed --class=GuestServiceCatalogSeeder` again → 10 categories, 2 departure items; `backend/database/database.sqlite` sha1 unchanged.
