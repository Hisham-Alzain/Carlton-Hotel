---
phase: 06-housekeeping-guest-services
plan: 08
subsystem: departure services status
requires: [06-07]
provides: [PATCH /departure-services/{uuid}/status, UpdateServiceBookingStatusAction, service_booking_transition_invalid, departure_service_readonly]
key-files:
  created:
    - backend/app/Actions/Service/UpdateServiceBookingStatusAction.php
    - backend/app/Exceptions/ServiceBookingTransitionException.php
    - backend/app/Exceptions/DepartureServiceReadonlyException.php
    - backend/app/Http/Requests/Operations/UpdateDepartureServiceStatusRequest.php
    - backend/tests/Unit/Service/UpdateServiceBookingStatusActionTest.php
  modified:
    - backend/app/Services/Operations/DepartureServiceService.php
    - backend/app/Http/Controllers/Admin/DepartureServiceController.php
    - backend/routes/api.php
    - backend/lang/{en,ar,es,fr,tr}/custom.php
    - backend/tests/Feature/Operations/DepartureServicesTest.php
completed: 2026-09-27
---

# 06-08 — Progressing departure services

## Shipped
- `UpdateServiceBookingStatusAction::handle(ServiceBooking, ServiceBookingStatus, ?User, ?string $reason)`: `DB::transaction(fn, 3)`, `lockForUpdate` on the booking, D-22 table (reject → `service_booking_transition_invalid` 422 with `{from, to, allowed}`, nothing written), one activity entry `service_booking.status_changed` {from, to, reason} (causer = actor, anonymous when null).
- `DepartureServiceService::updateStatus(uuid, status, ?sourceType, ?reason, actor)`: resolves by the `source_type` hint or probes transfer booking → late_checkout/luggage request → guest-express reservation (anything else 404 `not_found`); reservation → `departure_service_readonly` 422 before any write; family check (`tryFrom` on the resolved enum, miss → 422 `validation_failed` on `status`); booking → the new writer, request → `UpdateRequestStatusAction` (actor + reason, mirrors `service_request_{uuid}`); returns `DepartureServiceProjection::row()` of the refreshed source.
- `UpdateDepartureServiceStatusRequest`: status ∈ union of booking + request values, reason ≤ 255, source_type ∈ service_booking|service_request|reservation.
- `PATCH /departure-services/{uuid}/status` (`auth:users`, `service_requests.update`); message `custom.messages.departure_service_status_updated`. Exactly two departure routes (GET list, PATCH status).
- Lang (five locales): `errors.service_booking_transition_invalid`, `errors.departure_service_readonly`, `messages.departure_service_status_updated`.

## Deviations from PLAN.md
- Feature tests use the **concierge** preset (not "reception-like"): the seeded **reception preset does not hold `service_requests.update`**, so the front desk cannot progress departure rows (nor any request on the queue). The preset was left unchanged (seeder presets were fixed in 06-04; changing one is a permission decision). `test_auth` pins the current 403 for reception. **Owner decision needed:** add `service_requests.update` to reception if the front desk should action late checkouts / transfers.
- `test_resolution_and_hints` also covers an invalid `source_type` value (422).

## Flagged assumptions
- FA-6.08-1..3 as planned (only departure-capable sources resolve, no date re-check; requests accept any status; booking audit via activity entry). MySQL blocking on the booking lock is a backstop (SQLite only proves the compiled `for update`).

## Verification
- `UpdateServiceBookingStatusActionTest` (4 allowed + 4 disallowed data sets, lock, anonymous causer, transactional) and 8 PATCH methods in `DepartureServicesTest`: 33 passed with the 06-07 list methods.
- Regressions (`OperationsQueueTest|ServiceRequestBoardTest|FolioTest|ServiceCatalogTest|CmsAccessControlTest|ValidationMessageLocalizationTest|LocaleFoundationTest`) green.
- Full suite after 06-08: **1702 passed** (11143 assertions).
