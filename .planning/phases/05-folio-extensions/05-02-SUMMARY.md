---
phase: 05-folio-extensions
plan: 02
subsystem: staff folio read
requires: [05-01]
provides: [adminShow, FolioService-display-loader, FolioResource-ledger-fields, FolioMissingException]
key-files:
  created:
    - backend/app/Exceptions/FolioMissingException.php
  modified:
    - backend/app/Services/Folio/FolioService.php
    - backend/app/Http/Resources/Folio/FolioResource.php
    - backend/app/Http/Resources/Folio/FolioItemResource.php
    - backend/app/Http/Controllers/Admin/FolioController.php
    - backend/routes/api.php
    - backend/lang/{en,ar,fr,tr,es}/custom.php
    - backend/tests/Feature/Reservations/ExpressCheckoutTest.php
completed: 2026-09-26
---

# 05-02 — Staff folio read (FOLIO-01)

## Shipped
- `GET /api/cms/reservations/{reservation}/folio` (auth:users, permission:folios.view) -> `Admin\FolioController::showForReservation` -> `FolioService::adminShow(Reservation): array`; pure read, 404 `folio_missing` (context reservation_uuid, reservation_status) when no folio.
- `FolioService::display(Folio, ?Reservation)` private loader: items ordered by id, `items.postedBy:id,uuid,name`, `items.reversesItem:id,uuid`, `setRelation('ledgerPayments', ledgerPayments()->orderBy(created_at)->orderBy(id)->get())`. `myFolio`, `approveMyFolio`, `adminGenerate`, `adminSettle` all return through it (one shape, D-02).
- `FolioResource` + `payments` (PaymentResource over ledgerPayments; `recorded_by` omitted because recorders are not loaded), `paid_usd`, `balance_due_usd` (FolioLedger over the loaded relation, no query in toArray).
- `FolioItemResource` + `quantity`, `unit_price_usd`, `posted_by {uuid,name}|null`, `posted_at` (created_at for manual/credit rows, else null), `reason`, `reverses_item_uuid`.
- `FolioMissingException` (404). ALL phase-5 exceptions and lang keys were created in this plan in one mechanical pass (see Deviations).

## Deviations from PLAN.md
- (b, mechanical) Every Phase 5 lang key (6 messages, 9 errors, validation decimal/uuid/prohibited_unless/required_with) and all eight exception classes were added here in one pass rather than plan by plan; content matches the plans verbatim. BaseRequest mappings still land with their rules (05-03/04/06).
- `ExpressCheckoutTest::FOLIO_KEYS` pin extended with payments, paid_usd, balance_due_usd (additive D-02 shape; 05-09 adds open_disputes_count).

## Flagged assumptions
- FA-5.02-1 confirmed (payments[] omit recorded_by). FA-5.02-2 confirmed (posted_by visible to guests). FA-5.02-3 confirmed.

## Carry-forwards
- Read path: 1 folio select + items + postedBy + reversesItem + ledgerPayments = 5 queries (05-07 latestDispute makes 6; 05-09 folds the dispute count into the first select).
- Permission count 21.

## Verification
- FolioTest, ExpressCheckoutTest, StayTest, PermissionGuideAccuracyTest, LocaleFoundationTest, ValidationMessageLocalizationTest, ServiceCatalogTest green (160).
