# P8 — Folios & Express Checkout — Tickets

> Source: `PLAN.md` §P8. Depends on P4–P5, P7 (all green, 194 tests). This is the first phase to consume the `folios.view`/`folios.settle` permissions, seeded since P0 but unused until now.

---

## Scope decisions (PLAN.md is terse here — recording gap-fills before coding)

1. **One folio per reservation.** `folios.reservation_id` is a unique FK — `GenerateFolioAction` is idempotent (`updateOrCreate` + regenerate items), matching how a hotel PMS keeps one running folio per stay rather than accumulating duplicates on repeat calls.
2. **What counts as a "service charge" on the folio, given what P7 actually built:**
   - Room charge: `reservation.total_usd` — one line item, always present.
   - `service_bookings` (confirmed/completed) whose bookable has a `price_usd` (spa/pool_cabana/transfer) — one line item each, amount = bookable's price.
   - `restaurant_tables` bookings carry no charge (the table itself is free; P7 never built menu-item-priced food ordering against a booking/request) — correctly excluded, not a bug.
   - `service_requests` carry **no price data** in the P7 schema (`type`/`notes` only, no line-item/amount field) — cannot be priced, so they are **not** aggregated onto the folio. This is a real modeling gap for future phases (e.g., room-service orders with priced menu items), not something P8 should invent a fix for.
3. **"Guest review/approve" vs. "admin settle" is not the same action.** Cash/on-arrival payment requires a staff `User` recorder for audit (per P5's `RecordCashPaymentAction` signature — reused here unchanged). A guest cannot "settle" a cash payment themselves. So:
   - **Guest side** ("express checkout"): review the folio (`GET /folio`), then approve it (`POST /folio/approve`) — this finalizes checkout logistics (reservation → `checked_out`) without requiring the guest to be physically present at the desk. Gated `is_checked_in` (can only checkout from an active stay).
   - **Admin side**: generate/regenerate (`folios.view`), settle payment (`folios.settle` — reuses P5's `ManualDriver` via `RecordCashPaymentAction`, same as reservation settlement).
   - The folio can be settled before or after guest approval — they're independent steps, matching real front-desk operation (a guest might pay at checkout, or have already paid on arrival).
4. **`RequestTransportAction`** is a thin wrapper around P7's `PlaceServiceRequestAction` with `type` hardcoded to `transport` — reuses the existing department-routing/event-dispatch machinery rather than duplicating it. `transport` isn't in `ServiceRequest::TYPE_DEPARTMENTS`, so it correctly falls through to the `concierge` default (no map change needed).

## Slice
- **Migrations:** `folios` (uuid, reservation_id unique FK, status, subtotal_usd, total_usd, approved_by_guest_at nullable, settled_at nullable), `folio_items` (uuid, folio_id FK, description, amount_usd, source_type, source_id nullable).
- **Models:** `Folio`, `FolioItem`.
- **Actions:** `GenerateFolioAction`, `SettleFolioAction` (wraps `RecordCashPaymentAction` against the `Folio` as payable), `ApproveFolioAction` (guest-side checkout), `RequestTransportAction`.
- **Service:** `FolioService`.
- **Routes:** guest (`is_checked_in`): `GET /folio`, `POST /folio/approve`, `POST /transport-requests`. Admin: `POST /cms/folios/{reservation}/generate` (`folios.view`), `POST /cms/folios/{folio}/settle` (`folios.settle`).
- **Tests:** folio aggregates reservation + service_bookings correctly (and correctly excludes unpriced restaurant_table bookings + service_requests); settle moves folio to `settled` + creates a `Payment`; guest approve moves reservation to `checked_out`; transport request creates a `service_request` with `type=transport`; permission gates.

## ✅ P8 done-condition (from PLAN.md)
- [ ] Folio generation + settlement correct end to end.
- [ ] Checkout flow works (guest approve → `checked_out`).
- [ ] Full `php artisan test` green; P0–P7 suites unchanged.
- [ ] **Report and wait.**
