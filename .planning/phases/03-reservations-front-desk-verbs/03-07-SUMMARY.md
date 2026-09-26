---
phase: 03-reservations-front-desk-verbs
plan: 03-07
status: complete
completed: 2026-09-26
---

# Plan 03-07: Docs, Postman, Tree and Phase Close - Summary

Closing plan for Phase 3: documentation, the Postman collection, `carlton-tree.html`, and the phase-closing gate. See `SUMMARY.md` (in this same directory) for the phase-level view: endpoints, waves, decisions, test counts, carry-forwards and production notes.

## Task 1 - Docs (delegate: docs-guides)

- Guide order verified ok against the existing document structure (module ordering preserved: CMS Content, Front Desk, then Reservations).
- `PermissionGuideAccuracyTest` 7/7 passing, confirming every documented permission string matches a real `permission:` middleware entry.
- `API_GUIDE_DASHBOARD.md` updated: new sections for available-rooms, check-in, check-out and notes; assign-room section rewritten with the breaking-change callout; index filter table; detail-view notes field; status reference table; Folios paragraph; three new error-code rows plus the existing `no_availability` row widened to also cover the check-in auto-pick case.
- `CHANGELOG_MOBILE_API.md` updated: breaking-change row (three-verb to four-verb dashboard flow), a push-timing paragraph (room-ready now fires on check-in and on room moves, not on assign), and a Changed (non-breaking) row for `/folio/approve`'s new `reservation_state` edge.
- `API_GUIDE_MOBILE.md` updated: push-timing sentence and the `reservation_state` error code added to `/folio/approve`'s failure list; no index-shape change for the guest app.

## Task 2 - Postman (delegate: postman)

- Result: ok, 6 hunks confined to folders `05 - Booking (Admin)` and `10` (folio approve).
- Folder `05 - Booking (Admin)`: 17 requests total - 9 new (notes, available-rooms, check-in happy/early/maintenance/foreign-room/inactive-room negative cases, check-out happy/force/unsettled-negative cases) and 1 renamed (`Assign room at check-in (Layla)` -> `Assign room - pre-arrival (Layla, status stays confirmed)`) to reflect the narrowed, non-check-in behaviour.
- Folder `10` (folio approve): description updated to note the shared check-out delegation and the new `reservation_state` 422 edge.
- The force-checkout request uses request-level (not folder-level) auth, required by the collection's verify script.

## Task 3 - Tree (delegate: tree)

- Result: ok.
- `docs/carlton-tree.html`: `var TREE` parses as valid JS (verified by extracting and evaluating the object literal directly) - 94 nodes total, 73 with `api:true`.
- Baseline hash: `0c5da966...` (pre-Phase-3 tree, for diff reference).
- Nodes flipped: `check in check out` (now `api:true`, `dash:"mock"`, meta "check-in check-out room pick list"); `reservation notes` (now `api:true`, `dash:"mock"`, `ep:["PATCH /cms/reservations/{r}/notes"]`).
- `assign room confirm cancel` node meta updated to "assign only; check-in is its own verb" to reflect D-03's narrowing.

## Task 3 <verify> - Closing gate

Run from the repo root after the docs/Postman/tree delegates and the close-stage QA fixes:

- Routes: ok - `php artisan route:list` shows the seven Phase 3 endpoints on the expected guards/permissions, no stray routes.
- Seeder: identical to `b928abf` - `git diff b928abf -- database/seeders/RolesAndPermissionsSeeder.php` empty; `SeederTest` green (permission/role counts unchanged, zero new permissions this phase).
- Scratch migration: `php artisan migrate:fresh --seed` run against a scratch SQLite file (never `backend/database/database.sqlite`) - clean run, no errors; the tracked dev database was not touched or staged.
- Full suite: 1229/1229 passing, 6327 assertions, 0 failures (`php artisan test`, run serially - `--parallel` fails because `paratest` is not installed). See `SUMMARY.md` for the breakdown against the engineer-stage baseline.

Points to `SUMMARY.md` for the phase-level endpoint table, decision log, permissions statement, dashboard/app path-change contract, carry-forwards and production deploy notes.
