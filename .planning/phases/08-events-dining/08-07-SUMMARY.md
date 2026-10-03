---
phase: 08-events-dining
plan: 07
status: complete
---

# 08-07 Summary — Staff table-reservation list

## Built
- `App\Filters\TableReservationFilter`: `status` eq/in; `venue` / `table` uuid subqueries (venue join ignores soft deletes; non-uuid or unknown → empty page); window = `date` | `from`+`to` (inclusive, `to ≥ from`, ≤ 31 calendar days) | default hotel-local today; `date` + range → 422 on `date`; half a range → 422 on the missing key; bad date → 422; sortable `scheduled_at`, `guest_count`.
- `App\Services\Dining\TableReservationService extends BaseService` (`$perPage = 50`, max 100 inherited): restaurant_table rows, `morphWith(RestaurantTable → diningVenue withTrashed)`, `guest:id,uuid,name,first_name,last_name`, `reservation:id,uuid,booking_code`; default order `scheduled_at, id`.
- `App\Http\Resources\Dining\TableReservationResource extends BaseResource` (D-25 + PR-3 name fallback + PR-4 null-safe).
- `Admin\TableReservationController::index`; route `GET /cms/table-reservations` (`auth:users`, `service_requests.view`), imported as `AdminTableReservationController` (an `Api\TableReservationController` already exists).
- Lang: `custom.validation.prohibits` added in 5 locales (the `date` + range conflict message); `custom.validation.date_range_max` (from 08-01) used for the 31-day cap.

## Tests
- `tests/Feature/Dining/TableReservationIndexTest.php` (19): default today with the local-midnight edges, date, range, 422 × 4 groups, venue/table/status filters incl. unknown, other bookable types excluded, trashed venue renders, hard-deleted table null-safe, order + sort, exact row shape, Arabic venue name, per_page 50/100, **budget 6**, 401, 403.
- `tests/Unit/Dining/TableReservationFilterTest.php` (4, Europe/London, PR-5): 25-hour fall-back window, 23:30Z booking included, 31 accepted / 32 rejected, default today in BST.
- Full suite after this plan: 2145 green.

## Deviations
- The default order is set in the service `query()` (as `ServiceRequestBoardService` does) rather than in the filter's `applySort()`; an explicit `sort` still reorders (BaseFilter appends `id`).
