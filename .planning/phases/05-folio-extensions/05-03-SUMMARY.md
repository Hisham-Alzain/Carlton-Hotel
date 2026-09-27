---
phase: 05-folio-extensions
plan: 03
subsystem: manual charges + idempotency
requires: [05-02]
provides: [IdempotentWrite, PostFolioItemAction, PostFolioItemRequest, folios.post, decimal-mapping]
key-files:
  created:
    - backend/app/Support/IdempotentWrite.php
    - backend/app/Http/Requests/Folio/PostFolioItemRequest.php
    - backend/app/Actions/Folio/PostFolioItemAction.php
    - backend/app/Exceptions/FolioSettledException.php
    - backend/app/Exceptions/IdempotencyConflictException.php
  modified:
    - backend/app/Base/BaseRequest.php
    - backend/database/seeders/RolesAndPermissionsSeeder.php
    - backend/app/Services/Folio/FolioService.php
    - backend/app/Http/Controllers/Admin/FolioController.php
    - backend/routes/api.php
    - backend/tests/Feature/SeederTest.php
    - backend/tests/Feature/Staff/PermissionsGroupedTest.php
completed: 2026-09-26
---

# 05-03 — Post manual charges with idempotency (FOLIO-02, XCUT-01)

## Shipped
- `POST /api/cms/folios/{folio}/line-items` (auth:users, permission:folios.post) -> `Admin\FolioController::postItem` -> `FolioService::adminPostItem(Folio, array, User)` -> `PostFolioItemAction::handle(Folio $folio, User $poster, array $data): array`. 201 "Folio line item posted." (via `success()`), 200 on replay. No PUT/PATCH/DELETE route.
- `App\Support\IdempotentWrite::run(?string $key, callable $find, callable $matches, callable $write): array` -> `[Model $row, bool $replayed]`; null key writes without find; stored match replays; mismatch -> `IdempotencyConflictException` (409, context idempotency_key); `$write` runs in a savepoint and a `UniqueConstraintViolationException` is re-read as replay/conflict, else rethrown.
- `PostFolioItemRequest::prepareForValidation()` merges `idempotency_key` from the header (trimmed, blank -> null, always overwrites a body field) and defaults kind=charge, quantity=1 (consultant ruling plan-q3).
- Action order under the folio lock: replay check -> settled guard (`FolioSettledException`, 422, context folio_uuid, settled_at) -> bcmul line total -> insert (source_type manual, posted_by) -> `recalculateTotals()`.
- `folios.post` seeded, reception only (21 -> 22). SeederTest (`test_all_22_permissions_seeded`, `test_folio_posting_follows_the_folios_settle_holders`) and PermissionsGroupedTest (folios group) baselines updated.
- `BaseRequest::messages()['decimal']`.

## Deviations from PLAN.md
- (b) The request/action were written directly in their 05-04 final shape (kind charge|credit, credit floors) in the same round; 05-03's narrow `in:charge` never shipped on its own.
- `FolioLineItemTest` and `IdempotentWriteTest` are QA's (not written by the engineer).

## Flagged assumptions
- FA-5.03-1 superseded by 05-04 in the same round. FA-5.03-2 confirmed (poster not compared for items). FA-5.03-3 confirmed (no attribute block). FA-5.03-4 carried (min/max "characters" wording).

## Carry-forwards
- Tinker output for the decimal rule (verbatim): `The unit price usd field must have 0-2 decimal places.` Our localized en string renders `The unit price usd must have 0-2 decimal places.`
- Test harness note: `withHeader()` persists across requests in one test; send `Idempotency-Key` per request (`postJson($url, $body, ['Idempotency-Key' => $k])`).
- Permission count 22.

## Verification
- SeederTest, PermissionsGroupedTest, PermissionGuideAccuracyTest, ValidationMessageLocalizationTest, LocaleFoundationTest, RolePresetsTest: 107 passed. Engineer smoke (post 201, replay 200, conflict 409, decimal 422, settled 422, replay-before-settled 200, lock recorder) passed.
