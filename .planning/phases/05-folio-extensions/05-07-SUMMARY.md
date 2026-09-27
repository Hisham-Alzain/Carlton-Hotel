---
phase: 05-folio-extensions
plan: 07
subsystem: guest line-item disputes
requires: [05-06]
provides: [folio_item_disputes, FolioItemDispute, RaiseFolioDisputeAction, guest-dispute-route, dispute-freeze]
key-files:
  created:
    - backend/database/migrations/2026_09_26_130200_create_folio_item_disputes_table.php
    - backend/app/Enums/FolioDisputeStatus.php
    - backend/app/Models/FolioItemDispute.php
    - backend/database/factories/FolioItemDisputeFactory.php
    - backend/app/Exceptions/FolioItemDisputeOpenException.php
    - backend/app/Actions/Folio/RaiseFolioDisputeAction.php
    - backend/app/Http/Requests/Folio/GuestFolioDisputeRequest.php
  modified:
    - backend/app/Models/FolioItem.php
    - backend/app/Services/Folio/FolioService.php
    - backend/app/Http/Resources/Folio/FolioItemResource.php
    - backend/app/Http/Controllers/Api/FolioController.php
    - backend/routes/api.php
completed: 2026-09-26
---

# 05-07 — Guest line-item dispute (FOLIO-03)

## Shipped
- Migration `2026_09_26_130200_create_folio_item_disputes_table` (uuid, folio_item_id cascade, status string(16), reason string(500), guest_id/user_id/resolved_by nullOnDelete, resolved_at, resolution_note string(1000); indexes (folio_item_id,status), status, guest_id, user_id, resolved_by).
- `FolioDisputeStatus` (OPEN, RESOLVED, REJECTED); `FolioItemDispute` (HasUuid, LogsActivity, `scopeOpen()`, `raisedBy(): ?string`, relations item/guest/raisedByUser/resolver); factory states `resolved()`, `rejected()`, `byStaff()`.
- `FolioItem::disputes()`, `latestDispute()` (hasOne latestOfMany by id); `scopeWithLedgerReferences()` adds `is_disputed`; `isFrozen()` = reversed OR any dispute (any status).
- `PATCH /api/folio/items/{item}/dispute` (auth:guests + is_checked_in) -> `Api\FolioController::dispute` -> `FolioService::guestDispute(Guest, FolioItem, string)` -> `RaiseFolioDisputeAction::handle(FolioItem $item, Guest|User $raiser, string $reason): array` (folio lock; one open dispute else `FolioItemDisputeOpenException`, context item_uuid, dispute_uuid). 200 "Dispute raised." with FolioItemResource.
- `GuestFolioDisputeRequest::authorize()` compares `item->folio->reservation->guest_id` with the guest; `failedAuthorization()` throws `NotFoundException()` (same body as an unknown uuid: message `custom.errors.not_found`, context null), before validation.
- `FolioItemResource.dispute` = latest `{uuid, status, reason, raised_by, raised_at, resolved_at, resolution_note}|null`; `display()` eager-loads `items.latestDispute` (the 6th read query).

## Deviations from PLAN.md
- none. The FolioDisputeTest guest matrix and the reconcile freeze case are QA's.

## Flagged assumptions
- FA-5.07-1..4 confirmed.

## Carry-forwards
- Private `FolioService::displayedItem(array $result)` loads `latestDispute`, `postedBy:id,uuid,name`, `reversesItem:id,uuid` for item responses (shared with 05-08).
- Engineer smoke: foreign item and unknown uuid bodies identical; guest causer on the dispute activity row; disputed cabana row survives cancellation (total 380.00).

## Verification
- Scratch migrate cycle ok (dev DB untouched).
