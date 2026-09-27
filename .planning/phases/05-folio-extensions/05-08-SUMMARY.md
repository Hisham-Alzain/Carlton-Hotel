---
phase: 05-folio-extensions
plan: 08
subsystem: staff dispute raise/resolve/reject
requires: [05-07]
provides: [folios.dispute, ResolveFolioDisputeAction, staff-dispute-route, FolioDisputeStateException]
key-files:
  created:
    - backend/app/Exceptions/FolioDisputeStateException.php
    - backend/app/Http/Requests/Folio/StaffFolioDisputeRequest.php
    - backend/app/Actions/Folio/ResolveFolioDisputeAction.php
  modified:
    - backend/database/seeders/RolesAndPermissionsSeeder.php
    - backend/app/Services/Folio/FolioService.php
    - backend/app/Http/Controllers/Admin/FolioController.php
    - backend/routes/api.php
    - backend/tests/Feature/SeederTest.php
    - backend/tests/Feature/Staff/PermissionsGroupedTest.php
completed: 2026-09-26
---

# 05-08 — Staff dispute raise/resolve/reject (FOLIO-03, XCUT-01)

## Shipped
- `PATCH /api/cms/folios/{folio}/line-items/{item}/dispute` (auth:users, permission:folios.dispute, `->scopeBindings()`) -> `Admin\FolioController::dispute` -> `FolioService::adminDispute(Folio, FolioItem, array, User)` (explicit `folio_id` check -> `NotFoundException` fallback). raise -> `RaiseFolioDisputeAction` with the User; resolve/reject -> `ResolveFolioDisputeAction::handle(FolioItem $item, User $resolver, FolioDisputeStatus $outcome, string $note): array` (folio lock; no open dispute -> `FolioDisputeStateException`, context item_uuid + status = latest dispute status value or null; stamps status, resolved_by, resolved_at, resolution_note; an OPEN outcome is an InvalidArgumentException).
- Messages "Dispute raised." / "Dispute resolved." / "Dispute rejected." chosen by the validated action.
- `StaffFolioDisputeRequest`: action required|in:raise,resolve,reject; reason required_if:action,raise|max:500; note required_if:action,resolve,reject|max:1000.
- **Permissions: 23.** `folios.dispute` seeded on reception only. SeederTest `test_all_23_permissions_seeded` + `test_folio_dispute_follows_the_folios_post_holders`, idempotent count 23; PermissionsGroupedTest folios group = folios.dispute, folios.post, folios.settle, folios.view; 10 groups.

## Deviations from PLAN.md
- none. The staff matrix in FolioDisputeTest is QA's.

## Flagged assumptions
- FA-5.08-1 confirmed (context.status null when never disputed, else the latest status). FA-5.08-2 confirmed.

## Carry-forwards
- Engineer smoke: item of another folio via the wrong folio URL 404; resolve on a never-disputed item 422 status null; reject after resolve 422 status "resolved"; raise without reason 422 errors.reason; housekeeping 403.
