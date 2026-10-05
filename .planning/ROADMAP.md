# Roadmap: Carlton Hotel Backend — API Gap Closure

## Overview

This milestone replaces the mock data behind the Carlton staff dashboard and guest app with real, tested `/api` endpoints (the code mounts routes at `/api` with no `/v1` segment; the code wins over older `/api/v1` wording). It adds to the existing P0–P10 backend (283 routes, 250 green tests) one module at a time, in dependency order. It starts with the account endpoints guests and staff are missing. Next come the room status lifecycle, the front-desk reservation verbs, and the guest directory with online check-in. After those come folio writes, housekeeping and guest services, and support tickets on the shared operations queue. Event and dining extensions follow. Night audit and reports come last because they read from every earlier domain. Each phase is planned with `/gsd-plan-phase`, built by the `council-build` workflow and committed locally (never pushed) once the full suite is green.

**Every phase passes the same contract gate before it counts as done.** This covers DOCS-01 and XCUT-01, which are cross-cutting:

- Every new route responds in the standard envelope with the documented `error_code` values
- Feature tests (happy / 401 / 403 / 422) pass and the full suite is green
- AR/EN translation keys exist for every new message
- The phase's nodes in `docs/carlton-tree.html` are flipped to `api:true`
- The dashboard/mobile API guides and the Postman collection are updated
- New `{domain}.{action}` permissions are seeded with their role presets and listed, together with the paths the dashboard must adopt, in the phase summary

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Access & Settings** - Guest sign-out, staff own profile and password change; sets the per-phase docs/permission conventions
- [x] **Phase 2: Rooms — Status Lifecycle & Grids** - Live room board, audited housekeeping status transitions, 14-day availability and rate grids
- [x] **Phase 3: Reservations Front-Desk Verbs** - Reservation notes, available-room pick list, one-call check-in and check-out
- [x] **Phase 4: Guests & Stay** - Guest directory, profile with notes/preferences/pre-arrival checklist, online check-in with digital key
- [x] **Phase 5: Folio Extensions** - Staff folio read, line-item posting, payments, guest and staff line-item disputes
- [x] **Phase 6: Housekeeping & Guest Services** - Housekeeping task board tied to room status, service-request board, departure services
- [x] **Phase 7: Support Tickets & Queue** - Full support-ticket lifecycle, queue claim, assignable staff list
- [x] **Phase 8: Events & Dining** - Event inquiry checklist/deposit/notes, table reservation list, venue menu download (3886416)
- [x] **Phase 9: Night Audit & Reports** - Per-business-date night audit with checks and blockers, explicit close, reports dashboard
- [x] **Phase 9.1: Guest Account & App Support** (INSERTED) - Guest self-service account deletion (anonymize, retain accounting), 5-locale `preferred_locale`, public exchange rates (SYP/TRY) maintained by staff under `pricing.edit`
- [ ] **Phase 10: Loyalty Points Program** - Earn on settlement, FIFO expiry, rewards catalog and vouchers, points at booking, cancellation reversals, staff settings/audit/reports

## Phase Details

### Phase 1: Access & Settings

**Goal**: Guests can end their session, and staff can manage their own profile and password. The docs, permission and summary conventions every later phase follows are set up here.
**Mode:** mvp
**Depends on**: Nothing (first phase)
**Requirements**: ACCESS-01, ACCESS-02, ACCESS-03, DOCS-01, XCUT-01
**Success Criteria** (what must be TRUE):

  1. A signed-in guest calls `POST /auth/guest/logout`. The same token then gets 401 on any guest route.
  2. A staff member reads their own name and email via `GET /auth/profile` and updates them via `PUT /auth/profile` (changing the email requires the current password). A duplicate email or wrong current password returns 422 with field errors.
  3. A staff member changes their password via `PUT /auth/password` only when the current password is correct (wrong current password gives 422). Their other tokens then return 401 while the current session keeps working, and the endpoint is throttled.
  4. Contract gate: the three routes respond in the standard envelope, feature tests (happy / 401 / 403 / 422) pass with the full suite green, AR/EN keys exist, and the guest sign-out and staff profile/settings nodes in `docs/carlton-tree.html` are `api:true`. The API guides and Postman are updated.
  5. The phase summary sets the per-phase format that DOCS-01 and XCUT-01 require from every later phase: new `{domain}.{action}` permissions with their seeded role presets, and the dashboard path changes to adopt.

**Reuses**: existing staff auth (sign-in / me / logout), guest OTP + Sanctum token flow, `User` / `Guest` models, `RolesAndPermissionsSeeder`
**Plans**: 4 plans
Plans:
**Wave 1**

- [ ] 01-01-PLAN.md — Guest logout: `POST /auth/guest/logout` revokes the current token, optional owned device_token deregistration (ACCESS-01)

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 01-02-PLAN.md — Staff profile: `GET/PUT /auth/profile` (name + email), email change guarded by current_password, key localized in 5 locales (ACCESS-02)

**Wave 3** *(blocked on Wave 2 completion)*

- [ ] 01-03-PLAN.md — Staff password change: `PUT /auth/password`, other tokens revoked, throttled 5/min per account, password kept out of activity_log (ACCESS-03)

**Wave 4** *(blocked on Wave 3 completion)*

- [ ] 01-04-PLAN.md — Guides, Postman, carlton-tree flips, and the per-phase Summary Contract for permissions and path changes (DOCS-01, XCUT-01)

### Phase 2: Rooms — Status Lifecycle & Grids

**Goal**: The front desk sees every room's live housekeeping and occupancy state, moves rooms through an audited status lifecycle, and reads 14-day availability and rate grids.
**Mode:** mvp
**Depends on**: Phase 1 (docs and permission conventions only)
**Requirements**: ROOMS-01, ROOMS-02, ROOMS-03, ROOMS-04
**Success Criteria** (what must be TRUE):

  1. `GET /front-desk/room-board` returns every room with its housekeeping status and today's occupied / arriving / departing state. The query count stays bounded however many rooms there are.
  2. A staff member with `rooms.status` moves a room along an allowed transition via `PATCH /cms/rooms/{room}/status`: available ↔ dirty, available/dirty → maintenance, maintenance → dirty. A disallowed transition (including same-state) returns 422 with a domain `error_code`. Every accepted change writes a `room_status_history` row recording who, when, from and to.
  3. Changing a room's housekeeping status never changes what the booking availability check returns for a date range, because housekeeping status is independent of availability.
  4. `GET /front-desk/availability-grid?from&days` and `GET /front-desk/rates-grid?from&days` return per-room-type, per-night cells for 14 days by default. The rate grid is read-only, and an invalid `from` or `days` returns 422.
  5. Contract gate: all four routes pass happy / 401 / 403 / 422 tests with the suite green, AR/EN keys exist, and the room-board, room-status, availability-grid and rates-grid nodes are `api:true`. The guides and Postman are updated, and `rooms.status` is seeded with its role presets and listed in the summary.

**Reuses**: `Room`, `RoomStatus` enum (available / occupied / maintenance, to be extended), `AvailabilityService` / `CheckAvailabilityAction`, `PricingService` / `RatePlan` / `PricingRule` for rate cells
**Plans**: 4 plans
Plans:
**Wave 1**

- [ ] 02-01-PLAN.md — Room status lifecycle: `rooms.status` enum to string with legacy rows mapped, `room_status_history`, `PATCH /cms/rooms/{room}/status` behind new `rooms.status` (housekeeping + reception presets), five-locale keys, demo seeders fixed (ROOMS-02)

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 02-02-PLAN.md — Room board: `GET /front-desk/room-board` with derived occupancy / arriving / departing / stayover, filters, exactly 4 queries (ROOMS-01)

**Wave 3** *(blocked on Wave 2 completion)*

- [ ] 02-03-PLAN.md — Grids: `GET /front-desk/availability-grid` equal to `/availability` per cell (4 queries) and `GET /front-desk/rates-grid` via `PricingService::nightlyRate` equal to the one-night quote (2 queries) (ROOMS-03, ROOMS-04)

**Wave 4** *(blocked on Wave 3 completion)*

- [ ] 02-04-PLAN.md — Guides, Postman, carlton-tree flips, phase gate and the contract summary with [BLOCKING] deploy notes (DOCS-01, XCUT-01)

### Phase 3: Reservations Front-Desk Verbs

**Goal**: Staff run the desk flow on a reservation with explicit verbs: keep notes, pick an available room, check in, and check out against a settled folio.
**Mode:** mvp
**Depends on**: Phase 2 (check-out marks the room dirty)
**Requirements**: RESV-01, RESV-02, RESV-03, RESV-04
**Success Criteria** (what must be TRUE):

  1. Staff set and update free-text notes via `PATCH /cms/reservations/{reservation}/notes`, and the reservation detail returns them.
  2. `GET /cms/reservations/{reservation}/available-rooms` lists only rooms of the reservation's room type that are free for its dates. Rooms held by overlapping stays never appear.
  3. `POST /cms/reservations/{reservation}/check-in` assigns the room (given, pre-assigned, or auto-picked), sets `status = checked_in` and stamps `checked_in_at` in one call; the room board derives it as occupied (no `rooms.status` write). Checking in an already checked-in or cancelled reservation returns 422 `reservation_state`; check-in outside the hotel-local stay window returns 422 `reservation_outside_stay_window` unless `early_check_in` with a reason is sent for the day before. `assign-room` no longer flips status.
  4. `POST /cms/reservations/{reservation}/check-out` stamps `checked_out_at` and moves every assigned room to dirty on the room board (`UpdateRoomStatusAction`, system actor). If the folio is open, it is refused with 422 `folio_unsettled` unless a `folios.settle` holder sends `force: true` with a reason (recorded in the activity log; the folio stays open). Check-out emits `ReservationCheckedOut` after commit, which Phase 6's turnover task listens to; guest express checkout shares the same action.
  5. Contract gate: all four routes pass happy / 401 / 403 / 422 tests with the suite green, AR/EN keys exist, and the reservation notes, room pick and check-in/out nodes are `api:true`. The guides and Postman are updated, the assign-room behaviour change is announced, and the summary states that no new permissions were added.

**Reuses**: `AssignRoomAction`, `CheckAvailabilityAction`, `RoomAssigned` event → `SendRoomReadyNotification`, `Folio` / `FolioStatus`, the `SettleFolioAction` lock pattern, `reservations.*` and `folios.settle` permissions
**Plans**: 7 plans
Plans:
**Wave 1**

- [ ] 03-01-PLAN.md — Reservation notes: additive `reservations.notes`, `PATCH /cms/reservations/{reservation}/notes`, staff-only `notes` on the shared resource, five-locale key (RESV-01)

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 03-02-PLAN.md — Room pick list: `GET /cms/reservations/{reservation}/available-rooms` in 3 queries on one shared overlap predicate/picker; `config/hotel.php` + `HotelClock` hotel-local date, board and grid defaults aligned (RESV-02)

**Wave 3** *(blocked on Wave 2 completion)*

- [ ] 03-03-PLAN.md — Check-in verb: `POST /cms/reservations/{reservation}/check-in` (given / pre-assigned / auto-picked room, hotel-local stay window, logged day-before override, maintenance refused, no room-status write), `GuestCheckedIn` on the room-ready listener (RESV-03)

**Wave 4** *(blocked on Wave 3 completion)*

- [ ] 03-04-PLAN.md — assign-room narrowed to pure assignment (no status flip, `RoomAssigned` only on a move during a stay) and the four legacy tests rewritten in place (RESV-03)

**Wave 5** *(blocked on Wave 4 completion)*

- [ ] 03-05-PLAN.md — Check-out verb: `POST /cms/reservations/{reservation}/check-out` with the `folio_unsettled` gate, `folios.settle` force with a logged reason, rooms dirty via `UpdateRoomStatusAction` (system actor), `ReservationCheckedOut` after commit, `GenerateFolioAction` guard (RESV-04)

**Wave 6** *(blocked on Wave 5 completion)*

- [ ] 03-06-PLAN.md — Guest express checkout through the shared check-out action; `status` / `folio_status` filters on `GET /cms/reservations` (RESV-04)

**Wave 7** *(blocked on Wave 6 completion)*

- [ ] 03-07-PLAN.md — Guides, changelog, Postman, carlton-tree flips, phase gate and the contract summary (no new permissions, [BLOCKING] migrate) (DOCS-01, XCUT-01)

### Phase 4: Guests & Stay

**Goal**: Staff work from a guest directory with profiles, notes, preferences and a live pre-arrival checklist. Guests save their preferences and complete online check-in to receive a digital key.
**Mode:** mvp
**Depends on**: Phase 3 (consumes `ReservationCheckedOut`, `config/hotel.php` and the hotel-local clock) and Phase 1
**Requirements**: GUEST-01, GUEST-02, GUEST-03, GUEST-04, GUEST-05, GUEST-06
**Success Criteria** (what must be TRUE):

  1. Staff with `guests.view` search `GET /guests` by name, phone, email and a derived `stay_status` (`in_house | departing | arriving | upcoming | past | none`). They open `GET /guests/{guest}` to see stay history, preferences, the current/next reservation and a six-item pre-arrival checklist (`documents_uploaded, check_in_approved, preferences_set, arrival_time_set, room_assigned, digital_key_issued`) derived from current data rather than stored.
  2. Staff with `guests.edit` add internal notes via `POST /guests/{guest}/notes`. Preferences (bed type, pillow, floor, other) saved by the guest via `PATCH /auth/guest/preferences` or by staff via `PATCH /guests/{guest}/preferences` appear on the profile. Staff notes never appear in any guest-facing response.
  3. A guest with a confirmed upcoming stay submits an arrival time via `POST /stays/{reservation}/online-check-in` (non-owner → 403 `forbidden`; after the check-in date → 422 `online_check_in_closed`). Once the check-in approval is approved (with or without online check-in), `GET /stays/upcoming|active|status` show a random, high-entropy digital key code that expires at the hotel's check-out time on the check-out date. The code stops being returned after check-out, cancellation, rejection or expiry, and never appears in staff responses, pushes or the activity log.
  4. An ID scan uploaded through the existing `POST /pre-arrival/documents` route counts toward the checklist, and the mobile guide documents that wiring. No new route is added.
  5. Contract gate: all new routes pass happy / 401 / 403 / 422 tests with the suite green, AR/EN keys exist, and the guest directory, profile, preferences, online check-in and digital key nodes are `api:true`. The guides and Postman are updated, and `guests.view` / `guests.edit` are seeded with role presets and listed in the summary.

**Reuses**: `Guest` model + factory, `GuestDocument` / `PreArrivalService` (existing document upload), `CheckInApproval` / `ApproveCheckInAction`, the guest `has_booking` / `is_checked_in` gates
**Plans**: TBD

### Phase 5: Folio Extensions

**Goal**: Staff manage a reservation's folio end to end: read it, post charges and take payments. Guests and staff can dispute line items and resolve disputes, and totals stay consistent under concurrent use.
**Mode:** mvp
**Depends on**: Phase 3 (check-out gate reads the settled folio)
**Requirements**: FOLIO-01, FOLIO-02, FOLIO-03, FOLIO-04
**Success Criteria** (what must be TRUE):

  1. `GET /cms/reservations/{reservation}/folio` returns line items, payments and totals. A reservation without a folio returns 404 `folio_missing`.
  2. Staff with `folios.post` post a charge or credit line via `POST /cms/folios/{folio}/line-items` and the folio totals update at once. Posting to a settled folio returns 422 `folio_settled`; a credit may not exceed the charge it reverses or take the balance below zero. Concurrent posts never lose or double a total (verified by an interleaved invariant test), posted rows survive folio refreshes, and an `Idempotency-Key` replay returns the original result.
  3. Staff record a cash or pay-on-arrival payment via `POST /cms/folios/{folio}/payments` (`Idempotency-Key` required) and the balance, which counts folio and reservation-level payments, reflects it. A retried identical request does not record a second payment; a payment above the balance is refused (422 `folio_overpayment`); a payment that brings the balance to zero settles the folio automatically; a prepaid folio with zero balance closes without a payment via the settle route. Either way the folio then passes the Phase 3 check-out gate.
  4. A guest disputes their own line item via `PATCH /folio/items/{item}/dispute`, and staff with `folios.dispute` raise, resolve or reject disputes via `PATCH /cms/folios/{folio}/line-items/{item}/dispute`. The folio shows an open-dispute count, check-out is not blocked by an open dispute, disputes never move money, and a guest disputing another guest's item gets 404 `not_found` (a guest who is not checked in gets 403 `no_active_reservation`).
  5. Contract gate: all new routes pass happy / 401 / 403 / 422 tests with the suite green, AR/EN keys exist, and the staff folio, line-item, payment and dispute nodes are `api:true`. The guides and Postman are updated, and `folios.post` and `folios.dispute` are seeded and listed in the summary.

**Reuses**: `Folio`, `FolioItem`, `Payment`, `FolioService`, `GenerateFolioAction` (changed from rebuild to reconcile-by-source so posted rows and disputes survive refresh), `SettleFolioAction` (`DB::transaction` + `lockForUpdate`), `RecordCashPaymentAction`, `PaymentMethod` enum, `folios.view` / `folios.settle`
**Plans**: 10 plans
Plans:
**Wave 1**

- [ ] 05-01-PLAN.md — Ledger foundation: `folio_items` ledger migration, `FolioItemSource`, `FolioLedger` bcmath helpers, `Folio::ledgerPayments/paidUsd/balanceDueUsd/recalculateTotals`, `GenerateFolioAction` own-lock reconcile with frozen rows, receipt on the shared balance (FOLIO-02)

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 05-02-PLAN.md — Staff folio read `GET /cms/reservations/{reservation}/folio`, 404 `folio_missing`, one FolioResource shape with payments and signed balance, ≤6 queries (FOLIO-01)

**Wave 3** *(blocked on Wave 2 completion)*

- [ ] 05-03-PLAN.md — Post a charge `POST /cms/folios/{folio}/line-items`, `IdempotentWrite`, `folio_settled`, `idempotency_conflict`, `folios.post` seeded (FOLIO-02, XCUT-01)

**Wave 4** *(blocked on Wave 3 completion)*

- [ ] 05-04-PLAN.md — Credits with `reverses_item_uuid`, item and balance floors, D-07 interleaved invariant (FOLIO-02)

**Wave 5** *(blocked on Wave 4 completion)*

- [ ] 05-05-PLAN.md — Record a payment `POST /cms/folios/{folio}/payments` (Idempotency-Key required), overpayment refusal, auto-settle, decimal-string cash payments (FOLIO-04)

**Wave 6** *(blocked on Wave 5 completion)*

- [ ] 05-06-PLAN.md — Payment-free close on the settle route, `folio_settled` everywhere, legacy reservation-settle guard, prepaid folio passes check-out (FOLIO-04)

**Wave 7** *(blocked on Wave 6 completion)*

- [ ] 05-07-PLAN.md — Guest disputes a line item `PATCH /folio/items/{item}/dispute`, dispute history table, 404 for foreign items, disputed rows frozen (FOLIO-03)

**Wave 8** *(blocked on Wave 7 completion)*

- [ ] 05-08-PLAN.md — Staff raise/resolve/reject `PATCH /cms/folios/{folio}/line-items/{item}/dispute` with scoped bindings, `folios.dispute` seeded (FOLIO-03, XCUT-01)

**Wave 9** *(blocked on Wave 8 completion)*

- [ ] 05-09-PLAN.md — Open-dispute count on folio and check-out responses, `has_open_disputes` filter, check-out never blocked, Phase 9 read hooks (FOLIO-03)

**Wave 10** *(blocked on Wave 9 completion)*

- [ ] 05-10-PLAN.md — Guides, changelog, Postman (Idempotency-Key pre-request), carlton-tree flip, phase gate and contract summary with [BLOCKING] notes (DOCS-01, XCUT-01)

### Phase 6: Housekeeping & Guest Services

**Goal**: Housekeeping works from a task board wired to room status. Staff see and progress service requests and departure services through the shared operational endpoints.
**Mode:** mvp
**Depends on**: Phase 2, Phase 3, Phase 4 (`HotelClock::checkOutAt`, sync-listener precedent) and Phase 5 (folio billing of bookings)
**Requirements**: HK-01, HK-02, HK-03, HK-04, HK-05, SVC-01, SVC-02, SVC-03, SVC-04
**Success Criteria** (what must be TRUE):

  1. Checking a reservation out creates exactly one open turnover task for the room, and repeated triggers never create a duplicate open task (database-level dedupe). A service request routed to `Department::HOUSEKEEPING` creates a request-type task; completing it completes the request, and closing the request cancels its open task.
  2. Staff list tasks via `GET /housekeeping/tasks` with filters (status, room, assignee, type, due date), assign them, and move them through their statuses. Completing a turnover task moves a `dirty` room to `available` on the room board (a `maintenance` room is left untouched), and the room board's `dirty → available` click closes the open turnover task. The same tasks appear in the merged operations queue as the `housekeeping-tasks` type (rows carry `room_number` and `allowed_statuses`) and can be assigned and progressed via `/operations/queue/housekeeping-tasks/{uuid}`.
  3. Staff with `service_requests.view` filter the service-request board (`GET /cms/service-requests`) and use the existing assign/status actions on it. The mobile guide maps each guest quick-request chip to a direct-category catalogue item submitted through the existing service-request route.
  4. `GET /departure-services` lists transfers, late checkout, luggage (two newly seeded `direct` categories with unpriced default items) and express checkout (derived from the new `reservations.check_out_mode`) for reservations departing on a hotel-local date, with `stage`, `allowed_statuses` and `meta.truncated`. `PATCH /departure-services/{uuid}/status` changes the status on the underlying booking or request (express-checkout rows are read-only), and the next listing shows the change.
  5. Contract gate: all new routes pass happy / 401 / 403 / 422 tests with the suite green, AR/EN keys exist, and the housekeeping, service-request board and departure-services nodes are `api:true`. The guides and Postman are updated, and housekeeping permissions are seeded and listed in the summary.

**Reuses**: `AssignRequestAction` / `UpdateRequestStatusAction` (polymorphic union, adding a third arm), `OperationsQueueService`, `ServiceRequest`, `ServiceBooking`, `Transfer`, `ServiceItem` / `ServiceCategory`, the Phase 3 check-out event, `service_requests.view` / `.assign` / `.update`
**Plans**: 9 plans
Plans:
**Wave 1**

- [ ] 06-01-PLAN.md — Task foundation: `housekeeping_tasks` + history tables, enums, model-derived dedupe key, `ensureOpen()`, `HousekeepingTaskChanged`, `housekeeping:reconcile` (HK-04)

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 06-02-PLAN.md — Automatic tasks: turnover on check-out (SLA config, arrival priority), request task for housekeeping-department requests, `reservations.check_out_mode` (HK-04)

**Wave 3** *(blocked on Wave 2 completion)*

- [ ] 06-03-PLAN.md — Single writers: status (transition table, turnover room hook, request link) and assign, room-board closer, service-request close cancels its task, housekeeping lang keys (HK-03, HK-04)

**Wave 4** *(blocked on Wave 3 completion)*

- [ ] 06-04-PLAN.md — Task board API: `GET/POST /housekeeping/tasks`, show, assign, status; filter; `housekeeping.view|assign|update` seeded with presets and re-pinned tests (HK-01, HK-02, HK-03, HK-04)

**Wave 5** *(blocked on Wave 4 completion)*

- [ ] 06-05-PLAN.md — Operations queue third type via `OperationsQueueType` registry, `room_number` / `allowed_statuses`, widened polymorphic actions, queued Firestore task mirror (HK-05)

**Wave 6** *(blocked on Wave 5 completion)*

- [ ] 06-06-PLAN.md — Service-request board: `GET /cms/service-requests` (+ show), filters, ≤ 7 queries, writes via the queue (SVC-01)

**Wave 7** *(blocked on Wave 6 completion)*

- [ ] 06-07-PLAN.md — Departure list: seeded `late_checkout` / `luggage` direct categories, `GET /departure-services` projection with `stage`, `allowed_statuses`, `meta.truncated` (SVC-02, SVC-04)

**Wave 8** *(blocked on Wave 7 completion)*

- [ ] 06-08-PLAN.md — Departure status: `PATCH /departure-services/{uuid}/status`, `UpdateServiceBookingStatusAction`, read-only express rows (SVC-03)

**Wave 9** *(blocked on Wave 8 completion)*

- [ ] 06-09-PLAN.md — Guides, changelog, Postman, tree, quick-request chip mapping, phase gate and SUMMARY (SVC-04, DOCS-01, XCUT-01)

### Phase 7: Support Tickets & Queue

**Goal**: Staff run the full support-ticket lifecycle and route operations-queue work to the right colleague.
**Mode:** mvp
**Depends on**: Phase 6 (generalized operations queue)
**Requirements**: TICKET-01, TICKET-02, TICKET-03, TICKET-04, TICKET-05, TICKET-06, TICKET-07, OPS-01, OPS-02, OPS-03
**Success Criteria** (what must be TRUE):

  1. Staff list tickets via `GET /support-tickets` with filters (status, priority, department, assignee, source), open one with its action history, and create one for a guest or internally via `POST /support-tickets` (source `staff`).
  2. Staff change a ticket's status only along valid transitions (an invalid one returns 422 with a domain `error_code`; `assigned` is set only by assign/claim/escalate — a status PATCH to `assigned` returns 422 `ticket_transition_invalid`). They can also assign, reply, record a service-recovery action (type, description, optional amount; a folio credit is posted through the Phase 5 folio endpoint and linked by `folio_item_uuid`), and escalate to another staff member with a reason. Each of these writes a timestamped `ticket_actions` entry shown on the ticket detail, and replies are not exposed to the guest.
  3. A staff member claims a queue item via `PATCH /operations/queue/{type}/{uuid}/claim`. A second claim on an item that is already claimed returns 409 `queue_item_already_claimed`; re-claiming your own item is a 200 no-op; claim requires the type's work permission (`service_requests.update|tickets.respond|housekeeping.update`).
  4. `GET /operations/staff` lists assignable staff filtered by department or permission. Every queue item carries `queue_type` (the URL segment) alongside `type`, so the dashboard can build `{queue_type}/{uuid}` paths for all three item types.
  5. Contract gate: all new routes pass happy / 401 / 403 / 422 tests with the suite green, AR/EN keys exist, and the support-ticket, queue-claim and staff-list nodes are `api:true`. The guides and Postman are updated. No new permission strings; the `reception` and `concierge` presets gain ticket permissions (listed in the summary).

**Reuses**: `Ticket` model, `TicketStatus` / `TicketSource` / `TicketCategory` enums, `tickets.view` / `.assign` / `.respond`, `OperationsQueueService` as generalized in Phase 6, `OperationsQueueType` registry, `AssignRequestAction` (reused as the delegating arm, not the ticket writer), `AssignHousekeepingTaskAction` (claim), the Phase 5 credit path via `POST /cms/folios/{folio}/line-items`, `RecordsRowLocks`, `OperationsQueueMirror`
**Plans**: 11 plans (sequential waves 1-11)

- [x] 07-01-PLAN.md — Ticket foundation: 3 additive migrations, TicketStatus transitions, action/recovery enums + models, factories, escalation cap config (TICKET-02..07)
- [x] 07-02-PLAN.md — AssigneeEligibility, SR assign lock + `service_request_closed`, HK eligibility, ~12 test re-pins (TICKET-04, OPS-01)
- [x] 07-03-PLAN.md — Create + show: CreateTicketAction, TicketChanged mirror, TicketService show ≤ 6 queries, resources (TICKET-01, TICKET-02)
- [x] 07-04-PLAN.md — Ticket list: TicketFilter, index ≤ 6 queries (TICKET-01)
- [x] 07-05-PLAN.md — Status + assign single writers and routes (TICKET-03, TICKET-04)
- [x] 07-06-PLAN.md — Internal reply + escalation with guards and cap (TICKET-05, TICKET-07)
- [x] 07-07-PLAN.md — Record-only service recovery linking Phase 5 credits (TICKET-06)
- [x] 07-08-PLAN.md — Queue ticket arms delegate, A2 actor guard, `queue_type`, ticket room_number (OPS-03)
- [x] 07-09-PLAN.md — Claim across three types, ClaimGuard, 409, deactivated-token regression (OPS-01)
- [x] 07-10-PLAN.md — `GET /operations/staff`, reception/concierge presets + blast radius, assignability matrix, demo timelines (OPS-02)
- [x] 07-11-PLAN.md — Dashboard guide, Postman, tree flips, phase gate, SUMMARY, decision coverage (DOCS-01, XCUT-01)

### Phase 8: Events & Dining

**Goal**: Event staff track an inquiry's checklist, deposit and notes. Restaurant staff see table reservations, and guests can download venue menus.
**Mode:** mvp
**Depends on**: Phase 5 (payment action + Idempotency-Key pattern) and Phase 7 (event-inquiry gates and role presets re-pinned)
**Requirements**: EVENT-01, EVENT-02, EVENT-03, DINING-01, DINING-02
**Success Criteria** (what must be TRUE):

  1. Staff toggle checklist items via `PATCH /cms/event-inquiries/{inquiry}/checklist/{item}` (body `{done}`; items `contract|deposit|guarantee|beo|av`, where `deposit` is derived from the deposit and read-only) and update internal notes (`staff_notes`, the guest's `notes` unchanged) via `PATCH …/notes`. The inquiry detail reflects both.
  2. Staff with `events.deposit` record a deposit on a quoted or confirmed inquiry via `PATCH …/deposit`. It is written as a `payments` row through `RecordCashPaymentAction` with a required `Idempotency-Key`. A retried request returns 200 without a second payment, the same key with a different payload returns 409 `idempotency_conflict`, a second deposit returns 422 `event_deposit_already_recorded`, and an invalid amount returns 422.
  3. Staff with `service_requests.view` list restaurant table reservations via `GET /cms/table-reservations`, filtered by venue, status and hotel-local date or date range (default: today). Stored seating times are true UTC of the hotel-local slot.
  4. `GET /public/dining-venues/{venue}/menu/download` returns 200 with the menu file URL when one exists, 204 when none exists, and 404 for an unknown or inactive venue. Staff upload/replace/remove the file via `POST|DELETE /cms/dining-venues/{venue}/menu-file`.
  5. Contract gate: all new routes pass happy / 401 / 403 / 422 tests where they apply (the public menu route has no 401/403) with the suite green, keys exist in all five locales, and the event checklist/deposit/notes, table-reservation and menu-download nodes are `api:true`. The guides and Postman are updated. `events.view|manage|deposit` are seeded (29 permissions / 12 groups), event-inquiry routes move from `tickets.*` to `events.*` (reception/concierge lose event access), and the preset diffs are listed in the summary.

**Reuses**: `EventInquiry` / `EventRequirement`, `EventInquiryService`, `RecordCashPaymentAction`, `IdempotentWrite` and `FolioLedger::normalize` (Phase 5), `HotelClock::dayWindow`, `PurgesMedia` / `MediaService`, `RecordsRowLocks`, `ReserveTableAction` (tz fix) / `RestaurantTable`, `DiningVenue` / `DiningVenueService` + `Media`
**Plans**: 10 plans (sequential waves 1-10)

- [x] 08-01-PLAN.md — Foundation: 4 additive migrations (`staff_notes`/deposit columns, checklist table, `media.collection`, `service_bookings` index), `EventChecklistItem` / `EventDepositStatus` enums, checklist model, relations, factories, five-locale labels (EVENT-01..03, DINING-01, DINING-02)
- [x] 08-02-PLAN.md — `events.view|manage|deposit` (29/12), event-inquiry routes and dashboard-summary key re-gated off `tickets.*`, preset/seeder/guide re-pins (EVENT-01..03, XCUT-01)
- [x] 08-03-PLAN.md — Inquiry list/detail resources (checklist, deposit, staff_notes, assigned_user), list ≤ 6 / show ≤ 9 queries, additive `inquiry_state` context (EVENT-01..03)
- [x] 08-04-PLAN.md — Checklist toggle (explicit `done`, derived deposit 422, lazy rows) and staff notes writer (EVENT-01, EVENT-03)
- [x] 08-05-PLAN.md — Ledger-backed deposit: `RecordEventDepositAction`, Idempotency-Key replay/409, single deposit, status guards, folio isolation (EVENT-02)
- [x] 08-06-PLAN.md — `ReserveTableAction` timezone fix: hotel-local slot stored as true UTC, hotel-local "today" (DINING-01)
- [x] 08-07-PLAN.md — `GET /cms/table-reservations`: filter (venue/table/status/date/range, default today), resource, ≤ 6 queries (DINING-01)
- [x] 08-08-PLAN.md — Venue menu file: `POST|DELETE /cms/dining-venues/{venue}/menu-file`, replace action, images paths blind to menu rows (DINING-02)
- [x] 08-09-PLAN.md — Public `GET /public/dining-venues/{venue}/menu/download` (200 / 204 / 404, ≤ 2 queries) (DINING-02)
- [x] 08-10-PLAN.md — Guides, changelog, Postman, tree flips, demo data, PROJECT.md, phase gate, SUMMARY, decision coverage (DOCS-01, XCUT-01)

### Phase 9: Night Audit & Reports

**Goal**: Management closes each business date with a transparent night audit of checks and blockers, and reads a reports dashboard covering occupancy, arrivals and departures, revenue and open work.
**Mode:** mvp
**Depends on**: Phase 3, Phase 5, Phase 6, Phase 7
**Requirements**: AUDIT-01, AUDIT-02, AUDIT-03, AUDIT-04, REPORT-01
**Success Criteria** (what must be TRUE):

  1. Staff with `reports.view` or `night_audit.manage` open `GET /api/operations/night-audit?date` for a business date (initializing the business date needs `night_audit.manage`). The audit is created on first open and lists evaluated, persisted checks: unsettled departures, unassigned arrivals, dirty rooms, open high-priority tickets, and open folio disputes. Re-opening the same date never duplicates the audit or its checks, and the date is taken from the request or persisted state, never from the wall clock.
  2. Staff with `night_audit.manage` mark a check resolved or overridden with a note via `PATCH /api/operations/night-audit/checks/{check}` and resolve a blocker via `PATCH /api/operations/night-audit/blockers/{blocker}`. The audit then shows who acted and when, and acting on an already-resolved or closed item returns a domain error (`night_audit_item_resolved` / `night_audit_closed`).
  3. A manager closes the current business date via `POST /api/operations/night-audit/{audit}/close` once every check is terminal and every blocker resolved; the business date then advances by one calendar day (AUDIT-04).
  4. `GET /api/reports/dashboard` returns occupancy, arrivals/departures, revenue, and open requests/tickets for the requested period. The figures match the underlying bookings, folios, requests and tickets, and are computed with a bounded number of queries.
  5. Contract gate: all new routes pass happy / 401 / 403 / 422 tests with the suite green, keys exist in all 5 locales (en/ar/fr/tr/es), and the night-audit and reports nodes are `api:true`. The guides and Postman are updated, and `night_audit.manage` (catalogue 30 permissions / 13 groups) is listed in the summary.

**Reuses**: `reports.view` (already seeded), the `FolioStatus` / `RoomStatus` / `TicketStatus` enums and the domain tables from Phases 2–7, `HotelClock::today/dayWindow`, `FolioLedger` (and its new `MoneyAggregate` sibling), `RecordsRowLocks`, `TicketStatus::active()` and `ServiceRequestStatus::active()`, `ServiceRequestPriority::fromTicketScale`, `FolioItemSource`
**Research**: Done (`09-RESEARCH.md`): persisted business-date singleton, snapshot-at-first-open, portable room-night fold, integer-cents money aggregation, two report indexes.
**Plans**: 10 plans (sequential waves 1-10)

- [x] 09-01-PLAN.md — Foundation: 4 audit tables, report indexes (`folio_items.created_at`, `payments(status, created_at)`), 4 enums, models, factories (AUDIT-01..04, REPORT-01)
- [x] 09-02-PLAN.md — `MoneyAggregate`: exact integer-cents SQL aggregation + bcmath, explicit driver support (REPORT-01)
- [x] 09-03-PLAN.md — Five read-only evaluators with exact counts, ≤ 20 public evidence, volume-invariant queries; `CountsDomainQueries` (AUDIT-01)
- [x] 09-04-PLAN.md — `OpenNightAuditAction`: business-date state, initialization rules, future guard, idempotent lazy creation under lock; 6 exceptions; audit lang keys in 5 locales (AUDIT-01)
- [x] 09-05-PLAN.md — `GET /operations/night-audit`, `night_audit.manage` seeded (30/13), presets unchanged, `$notYetBuilt` cleared of `reports.view` (AUDIT-01, XCUT-01)
- [x] 09-06-PLAN.md — Check resolve/override and blocker resolve routes, closed-first and terminal guards, independent sign-off (AUDIT-02, AUDIT-03)
- [x] 09-07-PLAN.md — `POST …/{audit}/close`: readiness gate, idempotent repeat, one-calendar-day advance, invariants (AUDIT-04)
- [x] 09-08-PLAN.md — `GET /reports/dashboard` period contract, occupancy (portable fold), arrivals/departures (REPORT-01)
- [x] 09-09-PLAN.md — Revenue (`by_source`), collections by payable type, open work, ≤ 8-query budget (REPORT-01)
- [x] 09-10-PLAN.md — Guide, Postman, tree flips, phase gate, SUMMARY, decision coverage (DOCS-01, XCUT-01)

### Phase 9.1: Guest Account & App Support (INSERTED)

**Goal**: The Flutter guest app's three open asks work against tested endpoints. A guest can delete their own account: the personal data is erased, and bookings, folios and payments are kept for accounting. The profile accepts every configured language. The app reads current SYP/TRY exchange rates that staff maintain, so it no longer hard-codes them.
**Mode:** mvp
**Depends on**: Phase 4 (guest directory/profile, preferences, notes), Phase 5 (folio status), Phase 9 commit (shared routes/lang/docs). Executes before Phase 10.
**Requirements**: GACC-01, GACC-02, GACC-03, LOCALE-01, FX-01, FX-02
**Success Criteria** (what must be TRUE):

  1. `DELETE /api/auth/guest/me` with `{"confirm":true}` anonymizes the guest in one transaction. PII, tokens, device tokens, notifications, staff notes and OTP rows go. Guest chat is redacted (including Firestore), and ID scans are deleted except on checked-out stays. Reservations, folios, payments, tickets and reviews stay intact. A live stay, an open folio or an upcoming service booking returns 422 `guest_account_deletion_blocked` with `context.reasons`. A repeat is harmless, and the same phone can register again as a new account.
  2. Staff see deleted accounts flagged (`account_status`, `account_deleted_at`). The directory hides them unless filtered. Notes and preferences writes on them return 422 `guest_account_deleted`.
  3. `PUT /api/auth/guest/profile` accepts `preferred_locale` ∈ `cms.locales` (en, ar, fr, tr, es). A guest created at OTP sign-in gets the negotiated request locale.
  4. `GET /api/public/exchange-rates` returns USD-based SYP/TRY rates as decimal strings with `updated_at` and `is_stale`, in at most 2 queries, with `Cache-Control: max-age=300`. Staff with `pricing.edit` append rates (with a >50% change guard), read the board and page the history. The catalogue stays 30/13 and the presets are unchanged.
  5. Contract gate: happy / 401 / 403 / 422 per new route, keys in all 5 locales, full suite green. The mobile/website/dashboard guides, the mobile changelog, Postman, the tree and the dashboard handoff are updated.

**Reuses**: `GuestEntitlement::constrainLive`, `HotelClock`, `StaffService::deactivate` token-revocation pattern, `FileTrait`, `MirrorsToFirestore`, `TranslatableRules::locales()`, `RecordsRowLocks`, `CountsDomainQueries`, the inert `pricing.edit`
**Research**: Done (`09.1-RESEARCH.md`)
**Plans**: 9 plans (sequential waves 1-9)

- [x] 09.1-01-PLAN.md — `preferred_locale` validated against `cms.locales`, column-fit guard, seed locale on OTP guest creation (LOCALE-01)
- [x] 09.1-02-PLAN.md — `guests.account_status` + `account_deleted_at`, enum, factory state, 2 exceptions, 5-locale keys (GACC-01..03)
- [x] 09.1-03-PLAN.md — `DeleteGuestAccountAction`: guards, anonymize/retain/delete matrix, activity-log redaction, after-commit file/Firestore cleanup, idempotent under lock (GACC-01, GACC-02)
- [x] 09.1-04-PLAN.md — `DELETE /api/auth/guest/me` (confirm, throttle, 401/422 matrix, re-registration) (GACC-01, GACC-02)
- [x] 09.1-05-PLAN.md — Staff visibility: directory default exclusion + filter, resource flags, write guards, receipt name fallback (GACC-03)
- [x] 09.1-06-PLAN.md — FX foundation: `config/currency.php`, `CurrencyConfig`, append-only `exchange_rates`, model, exception, lang (FX-01, FX-02)
- [x] 09.1-07-PLAN.md — Staff FX routes under `pricing.edit`, large-change guard, `$notYetBuilt` cleared (FX-02)
- [x] 09.1-08-PLAN.md — `GET /api/public/exchange-rates` (shape, staleness, Cache-Control, query budget) (FX-01)
- [x] 09.1-09-PLAN.md — Guides, changelog, Postman, tree, handoff, phase gate, SUMMARY, decision coverage, commit (DOCS-01, XCUT-01)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 9.1 → 10

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Access & Settings | 0/TBD | Not started | - |
| 2. Rooms — Status Lifecycle & Grids | 0/TBD | Not started | - |
| 3. Reservations Front-Desk Verbs | 0/TBD | Not started | - |
| 4. Guests & Stay | 0/TBD | Not started | - |
| 5. Folio Extensions | 0/TBD | Not started | - |
| 6. Housekeeping & Guest Services | 0/TBD | Not started | - |
| 7. Support Tickets & Queue | 11/11 | Complete | 2026-10-02 (0961153) |
| 8. Events & Dining | 10/10 | Complete | 2026-10-03 (3886416) |
| 9. Night Audit & Reports | 10/10 | Complete | 2026-10-04 |
| 9.1. Guest Account & App Support (INSERTED) | 9/9 | Complete | 2026-10-04 (uncommitted) |
| 10. Loyalty Points Program | 6/15 | In Progress|  |

### Phase 10: Loyalty Points Program

**Goal**: Guests earn integer points once per settled folio, see a FIFO-expiring balance and ledger, redeem catalog rewards into vouchers and pay part of a booking with points; cancellations undo every loyalty effect without ever producing a negative balance. Staff configure the six program values, manage the catalog, adjust points with an audited reason and report issued/redeemed/expired points.
**Depends on**: Phase 3 (`HotelClock`), Phase 4 (`NotificationService::pushToGuest`), Phase 5 (settle/payment actions, `IdempotentWrite`, `FolioLedger`). Ordered after Phase 9 only because both edit the permission catalogue, `routes/api.php`, five lang files and docs (soft dependency; execute after the Phase 9 commit and re-read permission counts at execution). Executes after Phase 9.1 (INSERTED). 9.1 adds no permission, so the baseline is still 30/13. Phase 10 must add one thing: when a guest account is deleted (9.1 `DeleteGuestAccountAction`), the loyalty balance is forfeited (expire entries), and the deletion activity counts stay PII-free.
**Requirements**: LOY-01 .. LOY-22
**Success Criteria** (what must be TRUE):

  1. Every settlement path (`SettleFolioAction` x2, `RecordFolioPaymentAction` auto-settle) credits half-up points per `stay`/`service` bucket inside the folio-locked transaction, exactly once, never for cancelled reservations, and nothing when `earn_rate` is unset.
  2. `GET /api/loyalty/account|ledger|rewards|vouchers` return only the caller's data with the agreed contract strings; expired-but-unswept points are never available; the daily jobs expire batches idempotently and warn each batch once in the guest's locale.
  3. `POST /api/loyalty/rewards/{reward}/redeem` and `POST /api/reservations` with `loyalty_points`/`voucher_code` are idempotent under `Idempotency-Key`, enforce min/cap/one-voucher server-side, and `GET /api/loyalty/preview` equals the booked `total_usd` for identical inputs.
  4. Cancelling a reservation refunds spent points (original batch or fresh `refund` batch), restores the voucher and claws back folio earnings with recorded shortfall; a second cancel is a no-op; `ReverseLoyaltyForFolioAction` is unit-tested.
  5. Staff with `loyalty.view/manage/adjust` use `/api/cms/loyalty/*` (settings, rewards + bin, guest ledger, adjustments, reports); catalogue grows by 3 permissions / 1 group, presets unchanged.
  6. Contract gate: happy / 401 / 403 / 422 per route, 5 locales, docs/Postman/tree updated, Flutter/React teams notified of the contract strings and error codes.

**Research**: Done (`10-RESEARCH.md`); rulings in `10-DISCUSSION-LOG.md`.
**Plans:** 6/15 plans executed

Plans:

- [x] 10-01-PLAN.md — Foundation: Phase 9 committed check, 7 tables, 6 enums, `config/loyalty.php`, models, factories, `BuildsLoyaltyFixtures` (LOY-02, LOY-04, LOY-08, LOY-17)
- [x] 10-02-PLAN.md — `LoyaltyMath`, `LoyaltyProgram`, 8 exceptions, error keys in 5 locales (LOY-02, LOY-08, LOY-21, LOY-22)
- [x] 10-03-PLAN.md — `LoyaltyLedger`: FIFO spend, refund, clawback, expire (LOY-08, LOY-13, LOY-17)
- [x] 10-04-PLAN.md — Settings API, 3 `loyalty.*` permissions, count re-pins (LOY-01, LOY-02, LOY-20, LOY-21, LOY-22)
- [x] 10-05-PLAN.md — Earn wired into the 3 settlement sites (LOY-02, LOY-03, LOY-04)
- [x] 10-06-PLAN.md — Guest account and ledger, staff guest view (LOY-06, LOY-07, LOY-08)
- [ ] 10-07-PLAN.md — Manual adjust (LOY-05, LOY-20)
- [ ] 10-08-PLAN.md — Rewards CRUD with recycle bin, guest catalog (LOY-11, LOY-12)
- [ ] 10-09-PLAN.md — Redeem into voucher, my vouchers (LOY-13, LOY-14, LOY-22)
- [ ] 10-10-PLAN.md — `PriceLoyaltyRedemptionAction` and `GET /loyalty/preview` (LOY-15, LOY-16, LOY-22)
- [ ] 10-11-PLAN.md — Booking with points or voucher, idempotent replay (LOY-16)
- [ ] 10-12-PLAN.md — Cancel reversals and folio-refund seam (LOY-17, LOY-18)
- [ ] 10-13-PLAN.md — Daily expiry and expiry-warning jobs (LOY-08, LOY-09, LOY-10)
- [ ] 10-14-PLAN.md — Loyalty reports (LOY-19, LOY-20)
- [ ] 10-15-PLAN.md — Guides, Postman, tree, phase gate, SUMMARY (LOY-01, LOY-21)
