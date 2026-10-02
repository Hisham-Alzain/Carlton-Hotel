---
phase: 07-support-tickets-queue
plan: 10
subsystem: staff directory + ticket presets
requires: [07-09]
provides: [GET /operations/staff, reception/concierge ticket presets, A3/PR-5 assignability matrix, A4/PR-6 blast-radius pins, D-06-consistent demo tickets]
key-files:
  created:
    - backend/app/Services/Operations/OperationsStaffService.php
    - backend/app/Http/Controllers/Admin/OperationsStaffController.php
    - backend/app/Http/Requests/Operations/IndexOperationsStaffRequest.php
    - backend/app/Http/Resources/Operations/OperationsStaffResource.php
    - backend/tests/Feature/Operations/OperationsStaffTest.php
    - backend/tests/Feature/Staff/AssignabilityMatrixTest.php
  modified:
    - backend/routes/api.php
    - backend/database/seeders/RolesAndPermissionsSeeder.php
    - backend/database/seeders/DemoShowcaseSeeder.php
    - backend/database/seeders/GuestActivitySeeder.php
    - backend/tests/Feature/Staff/RolePresetsTest.php
    - backend/tests/Feature/SeederTest.php
completed: 2026-10-02
---

# 07-10 — Staff directory, presets, assignability matrix (OPS-02)

## Shipped
- `GET /api/operations/staff` (`auth:users` + `permission:service_requests.view|tickets.view|housekeeping.view`, the same gate as the queue index). It returns `data.items` (cap 200, name asc, then id) and `data.meta {count, truncated}`. Each row is exactly `{uuid, name, type, departments[]}`.
- Filters (`IndexOperationsStaffRequest`):
  - `type` must be a registry segment.
  - `permission` accepts only the three registry work permissions (A5); anything else is 422.
  - `department` is a `Department` enum value.
  - `search` is at most 100 characters.
  - `type` and `permission` apply together (AND, FA-7.10-2).
- `OperationsStaffService::index()`:
  - Lists active `staff`/`super_admin` users.
  - Each permission constraint is `permission($p) OR type = super_admin`.
  - The department filter checks that the role exists before calling Spatie `role()` (Pitfall 6). `sales` and `maintenance` return `[]`. Super admins are excluded by a department filter unless they hold the role.
  - Search is case-insensitive LIKE with `!`-escaping, the same scheme as `BaseFilter`.
  - Runs exactly 4 queries (pinned).
- `OperationsStaffResource`: `departments` = loaded role names that are `Department` values, always an array.
- There is no `/operations/queue/staff` alias (404, tested).
- Presets (no new permission strings, catalogue stays 26 / 11):
  - reception gains `tickets.view` and `tickets.respond` (16 permissions).
  - concierge gains `tickets.view`, `tickets.assign` and `tickets.respond` (8 permissions).
  - A seeder comment documents the A4/PR-6 exposure.
- Demo data:
  - Every demo ticket now has a `created` action and a valid D-06 path (assignment open → assigned, then status_change rows).
  - Resolved and closed tickets carry `resolved_at`; closed tickets also carry `closed_at`.
  - Three staff-sourced tickets are linked to a reservation and its room: one with a reply, one escalated (reception → housekeeping, level 1, meta `{level, previous_assignee_uuid}`), one with an apology recovery.

## Tests
- `OperationsStaffTest` (16): 401, 403 (cms.view only), 200 for each of the three view permissions, exact row shape, active / type filtering, sort, type filter (+ bogus 422), A5 permission whitelist (422 for `tickets.view`, `staff.manage`, `nope`), type+permission AND, department via role (super admin rules, sales/maintenance empty, bogus 422), search with `%` / `_` escaped, departments always an array, cap 200 / truncated, short list, `expectsDatabaseQueryCount(4)`, no alias.
- `AssignabilityMatrixTest` (23): 7 presets × 3 segments plus super admin plus "matrix covers every seeded preset". Matrix:
  - service-requests: reception, kitchen, housekeeping and concierge are eligible.
  - tickets: events, reception and concierge are eligible.
  - housekeeping-tasks: housekeeping only.
- `RolePresetsTest` adds two tests:
  - `test_reception_and_concierge_gain_ticket_permissions`: exact preset lists; kitchen and housekeeping hold no `tickets.*`.
  - `test_ticket_preset_blast_radius`: reception and concierge reach `/cms/conversations` read and reply, `/cms/event-inquiries` read, ticket rows in the queue, and the `tickets` / `event_inquiries` summary blocks. Event-inquiry status/assign and `/support-tickets/{t}/assign` succeed for concierge and return 403 for reception. Kitchen and housekeeping get 403 on `/cms/conversations` and `/support-tickets`.
- `SeederTest`: `test_all_26_permissions_seeded` is kept. Two tests are added (ticket holders; kitchen/housekeeping/events unchanged). The existing concierge pin in `test_housekeeping_permissions_are_granted_to_housekeeping_and_reception` was re-pinned to include `tickets.*`.
- The RED run was confirmed first: 55 tests, 20 failing.

## Deviations
- `GuestActivitySeeder` (outside the plan's file list) now stamps `resolved_at` / `closed_at` on its resolved/closed demo tickets. Without it, the scratch seed left one closed ticket with no `closed_at` (D-06 inconsistency). The fix is demo data only.
- Response nesting follows the `DepartureServiceController` precedent: `data.items` + `data.meta`. The plan text says `data.items` + `meta`.
- Search uses `lower(name) like ? escape '!'`, the `BaseFilter` scheme. With a plain LIKE, SQLite ignored the backslash escape.

## Verification
- `OperationsStaffTest|AssignabilityMatrixTest|RolePresetsTest|SeederTest|PermissionGuideAccuracyTest|CmsAccessControlTest`: 81 passed.
- Scratch `migrate:fresh --seed` (incl. demo): rc 0. The seed produced 43 tickets, 112 actions, 1 recovery and 3 staff tickets. 0 closed tickets lack `closed_at` and 0 resolved/closed tickets lack `resolved_at`. The dev DB is unchanged.
- Acceptance check `grep -c "'tickets\."` reads 2 → 4, not +5. `grep -c` counts lines, and each preset is one line, so the reception and concierge lines are the 2 new matching lines. Together they hold 5 new grants, as pinned by `RolePresetsTest` and `SeederTest`. The plan's +5 assumed one grant per line.
- Full suite: see 07-11-SUMMARY (run together with the gate).
- `database.sqlite` sha1 `babcdd27c9070101d4b7dc2bcfa42fdb8affd70d` unchanged.
