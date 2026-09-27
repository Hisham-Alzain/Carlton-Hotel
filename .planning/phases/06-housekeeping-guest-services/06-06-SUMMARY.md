---
phase: 06-housekeeping-guest-services
plan: 06
subsystem: staff service-request board
requires: [06-05]
provides: [GET /cms/service-requests, GET /cms/service-requests/{serviceRequest}, ServiceRequestFilter, ServiceRequestBoardService, ServiceRequestBoardResource]
key-files:
  created:
    - backend/app/Filters/ServiceRequestFilter.php
    - backend/app/Services/Operations/ServiceRequestBoardService.php
    - backend/app/Http/Resources/Operations/ServiceRequestBoardResource.php
    - backend/app/Http/Controllers/Admin/ServiceRequestBoardController.php
    - backend/tests/Feature/Operations/ServiceRequestBoardTest.php
  modified:
    - backend/routes/api.php
completed: 2026-09-27
---

# 06-06 — Staff service-request board

## Shipped
- `ServiceRequestFilter`: DSL `status|department|priority|type` (eq, in), `created_at` (gte, lte; parsed as an instant, compared in UTC like the 06-04 `due_at` bound); custom `assignee` (uuid | `unassigned`, else 422), `room` (number via `reservation.rooms.room`), `date` (hotel-local Y-m-d via `HotelClock::dayWindow`, half-open, else 422), `guest` (uuid exact, else case-insensitive fragment of name / first_name / last_name through `orWhereLikeInsensitive`); sort `created_at|priority|status` (priority by CASE rank), id desc tiebreak.
- `ServiceRequestBoardService` (BaseService): `$with` guest, reservation, serviceItem, assignedUser, housekeepingTask; `index()` = `withRoomNumber()` + eager loads, default `created_at desc, id desc`; `show()` re-reads the row through the same query so `room_number` is present. Read-only, no transaction, no lock.
- `ServiceRequestBoardResource` (staff-only): uuid, type, category_code, department, status, priority, notes, created_at, updated_at, guest{uuid,name}, reservation{uuid,booking_code,check_out,room_number}, service_item{uuid,name(map),expected_minutes,price_usd}|null, assigned_user{uuid,name}|null, housekeeping_task{uuid,status}|null.
- `Admin\ServiceRequestBoardController` (BaseController): index (`paginatedSuccess`), show. No write methods (D-17).
- Routes: `GET /cms/service-requests`, `GET /cms/service-requests/{serviceRequest}` behind `auth:users` + `permission:service_requests.view`.

## Deviations from PLAN.md
- **Consultant override:** the plan's `housekeepingTask(): MorphOne` step was not needed — `ServiceRequest::housekeepingTask(): HasOne` over the `service_request_id` FK already existed (06-01). `grep -c "function housekeepingTask"` = 1 as required.
- **Found, not fixed (pre-existing):** the global exception renderer in `bootstrap/app.php` has no branch for `MethodNotAllowedHttpException`, so `POST/PUT/PATCH/DELETE /api/cms/service-requests` answer **500 `server_error`** instead of 405 (the same holds for every GET-only path in the API). The plan expected 404/405; `test_no_write_routes` asserts "never 2xx and the row is unchanged" and `route:list` shows no write verb on the path. Fixing it means a new `method_not_allowed` error code (contract addition) in the global handler — left for the owner, listed in the phase SUMMARY.
- `test_show_and_unknown_uuid` also carries the show route's 401/403 checks.

## Flagged assumptions
- FA-6.06-1 (category_code = snapshotted `type` when `service_item_id` is set), FA-6.06-2 (guest uuid or name fragment) as planned.

## Verification
- RED observed (routes missing) before implementation.
- `ServiceRequestBoardTest` 20 passed; with `CmsAccessControlTest|PermissionGuideAccuracyTest|OperationsQueue*`: 77 passed.
- ≤ 7 queries per 15-row page (count + page with subselect + 5 eager loads) asserted on the service path incl. resource serialisation; `lockedSelects` = [] and row counts (service_requests, housekeeping_tasks, activity_log) unchanged by a GET.
- `route:list --path=service-requests`: only the two GET board routes plus the existing guest routes.
