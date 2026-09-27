---
phase: 06-housekeeping-guest-services
plan: 04
subsystem: housekeeping task board API
requires: [06-03]
provides: [GET/POST /housekeeping/tasks, GET /housekeeping/tasks/{task}, PATCH /housekeeping/tasks/{task}/assign|status, housekeeping.view|assign|update, HotelClock::dayWindow]
key-files:
  created:
    - backend/app/Filters/HousekeepingTaskFilter.php
    - backend/app/Services/Housekeeping/HousekeepingTaskService.php
    - backend/app/Http/Resources/Housekeeping/HousekeepingTaskResource.php
    - backend/app/Http/Controllers/Admin/HousekeepingTaskController.php
    - backend/app/Http/Requests/Housekeeping/CreateHousekeepingTaskRequest.php
    - backend/app/Http/Requests/Housekeeping/AssignHousekeepingTaskRequest.php
    - backend/app/Http/Requests/Housekeeping/UpdateHousekeepingTaskStatusRequest.php
    - backend/tests/Feature/Housekeeping/IndexTest.php
    - backend/tests/Feature/Housekeeping/CreateTest.php
    - backend/tests/Feature/Housekeeping/AssignTest.php
    - backend/tests/Feature/Housekeeping/StatusTest.php
  modified:
    - backend/app/Support/HotelClock.php
    - backend/routes/api.php
    - backend/database/seeders/RolesAndPermissionsSeeder.php
    - backend/tests/Feature/SeederTest.php
    - backend/tests/Feature/Staff/PermissionsGroupedTest.php
    - backend/tests/Feature/Staff/RolePresetsTest.php
completed: 2026-09-27
---

# 06-04 — Housekeeping task board

## Shipped
- `HotelClock::dayWindow(Y-m-d)`: hotel-local day as half-open UTC window; strict round-trip parse (`2027-03-12` in Asia/Damascus → [2027-03-11 21:00, 2027-03-12 21:00) UTC; `2027-13-01` rejected).
- `HousekeepingTaskFilter`: DSL `status|type|priority` (eq, in), `due_at` (gte, lte) parsed as an instant and compared in UTC (inclusive; offsets honoured; unparseable → 422); custom `room` (number or uuid), `assignee` (uuid | `unassigned`, else 422), `due_date` (hotel day via `dayWindow`, else 422); sort `due_at|created_at|priority` (priority by CASE rank), `id` asc tiebreak.
- `HousekeepingTaskService`: `$with` room, reservation, assignedUser, serviceRequest; default order `due_at is null, due_at, id`; show loads the last 10 history rows (newest first) with `changedBy`; store → `ensureOpen` (reason `manual`, created_by caller, due_at converted to UTC); assign/updateStatus → the single writers.
- `HousekeepingTaskResource`: uuid, type, status, priority, notes, room{uuid,number,floor,status}, reservation{uuid,booking_code,check_out}|null, assigned_user{uuid,name}|null, service_request_uuid, due/started/completed/created/updated (ISO-8601), allowed_statuses, history (show only). No occupant PII.
- `Admin\HousekeepingTaskController` (BaseController): index, show, store (201 `housekeeping_task_created` / 200 `housekeeping_task_exists` — uses `success()` directly because `respondFromService()` would swap a 201 message for the generic `created`), assign, updateStatus.
- Routes (5): `auth:users` + `permission:housekeeping.view` (GET list/show), `.assign` (POST, PATCH assign), `.update` (PATCH status). No DELETE.
- Seeder: `housekeeping.view|assign|update`; housekeeping preset += all three, reception += view, assign. Counted from disk: 23 → 26 permissions, 10 → 11 groups. Re-pinned `SeederTest` (renamed `test_all_26_permissions_seeded`, both counts 26, new holders test asserting kitchen/concierge/events unchanged), `PermissionsGroupedTest` (11 groups, housekeeping module = assign/update/view), `RolePresetsTest`.

## Deviations from PLAN.md
- **Consultant override:** `$with` and the resource use `serviceRequest` (FK) instead of `source`.
- `due_at[gte|lte]` values are parsed to UTC in the filter (`cast()` override) rather than compared as raw strings; without this an ISO-8601 bound would compare lexically against the stored `Y-m-d H:i:s`.
- RED step not observed separately: this session wrote the application code before the four spec files (resumed after the previous engineer's cut-off). The specs were run against the finished code only.
- Added `test_list_hides_history` (Index) and `test_kitchen_and_concierge_presets_are_forbidden_everywhere` (Status) beyond the minimum.
- Acceptance grep `grep -c "housekeeping\." seeder` counts lines (3); occurrences are 8 (≥ 6 intended).

## Flagged assumptions
- FA-6.04-1..4 as planned (deactivated assignee not refused; same-status moves invalid; unknown enum filter values match nothing; permission arithmetic N+3).

## Verification
- `php artisan route:list --path=api/housekeeping`: 5 routes.
- Board-core check: `dayWindow('2027-03-12')` = `2027-03-11 21:00:00|2027-03-12 21:00:00`.
- Greps: `ensureOpen` in service = 1; `guest|phone|email` in resource = 0; `reject(` in filter = 4; `assertCount(26` in SeederTest = 2.
- Housekeeping + permission tests (`Housekeeping|SeederTest|PermissionsGroupedTest|RolePresetsTest|CmsAccessControlTest|PermissionGuideAccuracyTest|StaffAuthorizationTest`): 172 passed.
- Full suite: **1626 passed** (10671 assertions). `backend/database/database.sqlite` sha1 unchanged across the run; folio files unchanged against `06-BASE.txt`.
