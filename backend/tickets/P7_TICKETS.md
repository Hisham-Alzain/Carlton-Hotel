# P7 — Service Layer (In-room / Venue + Pre-Arrival) — Tickets

> Source: `PLAN.md` §P7 (revision 4). Depends on P0–P6.5 (all green, 163 tests). Requires `is_checked_in` on `GET /api/auth/guest/me`, built in P6.5 — confirmed present (`GuestResource`, `App\Http\Resources\GuestResource`).

---

## Build order (gate first, per PLAN.md)

### T1 — Entitlement gate (two middlewares)
- `App\Support\GuestEntitlement` — single source of truth for the `has_booking` / `is_checked_in` computation (status ∈ [confirmed, checked_in] AND `check_out >= today` for booking; status == checked_in for checked-in), shared between `GuestResource` (P6.5) and the two new middlewares so the logic isn't duplicated. Also exposes `currentReservation(Guest): ?Reservation` so downstream actions (service bookings, requests, documents) resolve the guest's active reservation server-side — the request body never supplies a reservation ID (same "identity/entitlement from server state, not client input" principle as P4's authenticated booking path).
- `App\Http\Middleware\EnsureHasBooking` — alias `has_booking`. Rejects with `NotFoundException`-style domain exception `NoActiveReservationException` (`error_code: no_active_reservation`, 403) if `GuestEntitlement::hasBooking() === false`.
- `App\Http\Middleware\EnsureIsCheckedIn` — alias `is_checked_in`. Same rejection, checks `isCheckedIn()`.
- Registered as route middleware aliases in `bootstrap/app.php`.

### T2 — Migrations (10 tables)
`service_bookings` (polymorphic bookable via `bookable_type`/`bookable_id`, morph-mapped), the 4 concrete bookables (`spa_services`, `restaurant_tables` — FK to existing `dining_venues`, `pool_cabanas`, `transfers`), `service_requests` (queue-shaped, type→department routing mirroring P6's `SubmitInquiryAction` pattern), `menu_categories`, `menu_items`, `guest_documents`, `check_in_approvals` (one row per reservation, upserted on first document submission).

### T3 — Models, Actions, Services, Requests, Resources, Controllers, Routes
Per PLAN.md slice. Concrete bookables use `HasTranslations` for `name` (content-facing, per global convention). `Relation::morphMap()` registered in `AppServiceProvider::boot()` for clean `bookable_type` strings (`spa_service`, `restaurant_table`, `pool_cabana`, `transfer`) instead of raw FQCNs.

### T4 — Firestore mirror stub
`PlaceServiceRequestAction` dispatches `ServiceRequestPlaced`; `App\Listeners\MirrorServiceRequestToFirestore` implements `ShouldQueue` with an empty body + P9 forward-reference comment — same stub pattern as P6's `NotifyDepartmentOnInquiry`.

---

## Scope decisions (recorded here to avoid ambiguity mid-build; not deviations from spec, just gap-fills where PLAN.md is silent on detail)

1. **Permission for catalog/menu admin CRUD:** PLAN.md doesn't name a permission for spa/table/cabana/transfer or menu CRUD (the seeder has no `service_catalog.*`). Reusing **`cms.edit`** (content-management permission, same as P3's CMS CRUD) rather than inventing a new permission — these are content catalogs, not operational queues. Pre-arrival approvals (`ApproveCheckInAction`) reuse **`reservations.create`**, consistent with P4's precedent for reservation-state mutations (`assign-room`).
2. **No guest-facing "browse menu" endpoint.** PLAN.md's Routes bullet lists only "place request, pre-book, upload docs" for guests; the done-condition/tests only require admin menu CRUD. Building a guest read endpoint would be scope not specified — skipped. Room-service ordering is placed via `PlaceServiceRequestAction` (type=`room_service`, item selection in `notes`/a `payload` field); a guest-facing menu browse endpoint can be added in a later phase if the app team asks for it.
3. **`service_bookings` has no concurrency lock.** Unlike BMS room inventory, PLAN.md doesn't ask for slot-capacity concurrency guarantees here — the done-condition only requires "polymorphic bookings resolve." No `lockForUpdate`.
5. **No admin index/assign/status-update routes for `service_bookings`/`service_requests` in P7.** Re-reading PLAN.md's Actions list closely: P7 only lists `PlaceServiceRequestAction`, `CreateServiceBookingAction`, `SubmitDocumentsAction`, `ApproveCheckInAction` — no `AssignRequestAction`/`UpdateRequestStatusAction`. Those belong to **P10** ("Actions: RouteRequestAction, AssignRequestAction, UpdateRequestStatusAction... Service: OperationsQueueService — unified, department-filtered, permission-gated read over both tables"), confirming P7's admin surface is limited to catalog CRUD + check-in approvals. Building admin assign/status routes here would duplicate P10's actual scope. P7 admin-side is therefore: catalog CRUD (6 types) + `Admin/CheckInApprovalController` only.
4. **Guest identity/reservation resolution is server-side only** — guest-facing routes never accept a `reservation_id` or `guest_id` in the request body; both are resolved from the authenticated guest token + `GuestEntitlement::currentReservation()`, mirroring P4's "auth path ignores body contact fields" rule.

## Tests
- Gate: no booking → `no_active_reservation` on both pre-arrival and in-room; booked-not-checked-in → pre-arrival allowed, in-room rejected; checked-in → both allowed.
- Polymorphic booking across all 4 bookable types resolves correctly.
- `service_requests` carries correct `department` per `type`; status defaults to `new`.
- Pre-arrival document upload (secure, `has_booking` gated) + admin approve/reject via `check_in_approvals`.
- Menu CRUD (admin, `cms.edit`).

## ✅ P7 done-condition (from PLAN.md)
- [ ] Both service shapes (`service_bookings`, `service_requests`) work.
- [ ] Polymorphic bookings resolve across all 4 types.
- [ ] Pre-arrival flow complete (documents + approval).
- [ ] Two-flag gate enforced server-side (`has_booking` pre-arrival, `is_checked_in` in-room).
- [ ] Firestore mirror seam marked for P9.
- [ ] Full `php artisan test` green; P0–P6.5 suites unchanged.
- [ ] **Report and wait.**
