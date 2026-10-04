# Requirements: Carlton Hotel Backend — API Gap Closure

**Defined:** 2026-09-25
**Core Value:** Every screen the dashboard and guest app already show works against a real, tested, convention-compliant `/api` endpoint instead of mock data.

All routes are under `/api` (the code has no `/v1` segment — `bootstrap/app.php` sets no `apiPrefix`; the code wins, as in Phases 2–9). Staff routes use `auth:users` + permission middleware; guest routes use `auth:guests` (+ `has_booking` / `is_checked_in` gates where the existing pattern applies). Public ids are UUIDs. Every requirement is done only when its feature tests (happy / 401 / 403 / 422), keys in all 5 locales (en/ar/fr/tr/es), API guide + Postman entries exist and its node in `docs/carlton-tree.html` is flipped to `api:true`.

## v1 Requirements

### Access & Settings

- [ ] **ACCESS-01**: Guest can sign out; the current Sanctum token is revoked (`POST /auth/guest/logout`)
- [ ] **ACCESS-02**: Staff member can view and update their own profile (`GET/PUT /auth/profile`: name, email, phone, locale)
- [ ] **ACCESS-03**: Staff member can change their password with current-password verification (`PUT /auth/password`), other tokens revoked

### Rooms & Inventory

- [ ] **ROOMS-01**: Front desk can view a room board of every room with housekeeping status and today's occupancy/arrival/departure state (`GET /front-desk/room-board`)
- [ ] **ROOMS-02**: Authorized staff can change a room's housekeeping status through an explicit transition table (available ↔ dirty; available/dirty → maintenance; maintenance → dirty), independent of availability, with a `room_status_history` row recording who/when/from/to (`PATCH /cms/rooms/{room}/status`, new permission `rooms.status`)
- [ ] **ROOMS-03**: Front desk can view a 14-day availability grid per room type (`GET /front-desk/availability-grid?from&days`)
- [ ] **ROOMS-04**: Front desk can view a 14-day read-only nightly rate grid per room type (`GET /front-desk/rates-grid?from&days`)

### Reservations (front-desk verbs)

- [ ] **RESV-01**: Staff can set and update free-text notes on a reservation (`PATCH /cms/reservations/{reservation}/notes`)
- [ ] **RESV-02**: Staff can list the rooms available for a reservation's type and dates (`GET /cms/reservations/{reservation}/available-rooms`)
- [ ] **RESV-03**: Staff can check a reservation in with one verb that assigns the room (given, pre-assigned or auto-picked), sets `status = checked_in` and stamps `checked_in_at`; the room board derives it as occupied (no `rooms.status` write); check-in outside the hotel-local stay window returns 422 `reservation_outside_stay_window` unless `early_check_in` with a reason is sent for the day before (`POST /cms/reservations/{reservation}/check-in`); `assign-room` becomes pure assignment
- [ ] **RESV-04**: Staff can check a reservation out with one verb that refuses an open folio with 422 `folio_unsettled` unless a `folios.settle` holder sends `force: true` with a reason (recorded in the activity log; folio stays open), stamps `checked_out_at`, moves `available`/`maintenance` rooms to `dirty` via `UpdateRoomStatusAction` (system actor), and emits `ReservationCheckedOut`; guest express checkout shares the same action (`POST /cms/reservations/{reservation}/check-out`)

### Guests & Stay

- [ ] **GUEST-01**: Staff can browse and search the guest directory with `search` (name, phone, email) and a derived `stay_status` filter (`in_house | departing | arriving | upcoming | past | none`), paginated with `per_page` (default 15, max 100) (`GET /guests`, permission `guests.view`; `GET /guests/{guest}/notes` lists notes)
- [ ] **GUEST-02**: Staff can open a guest profile that includes stay history (25 most recent + `stays_total`/`has_more`), preferences, the current/next reservation with approval, document metadata and digital-key status, and a derived six-item pre-arrival checklist (`documents_uploaded, check_in_approved, preferences_set, arrival_time_set, room_assigned, digital_key_issued`) (`GET /guests/{guest}`)
- [ ] **GUEST-03**: Staff can add append-only internal notes to a guest (`POST /guests/{guest}/notes`, permission `guests.edit`; readable under `guests.view`; never in guest-facing responses or the activity log)
- [ ] **GUEST-04**: Guest preferences (bed type, pillow, floor, other) can be saved by the guest (`PATCH /auth/guest/preferences`) and by staff (`PATCH /guests/{guest}/preferences`)
- [ ] **GUEST-05**: Guest can complete online check-in for a confirmed upcoming stay by submitting an arrival time (`POST /stays/{reservation}/online-check-in`; non-owner 403 `forbidden`, closed window 422 `online_check_in_closed`); a display-only digital key code (random ~60-bit, encrypted at rest, hidden from staff responses/pushes/activity log, expires at hotel check-out time, revoked on check-out/cancel/rejection/expiry) is issued when the check-in approval is approved regardless of online check-in, and returned on `GET /stays/upcoming|active|status` while active; no lock hardware
- [ ] **GUEST-06**: Guest ID scan uploads use the existing `POST /pre-arrival/documents` route; mobile wiring documented (no new route)

### Folio & Payments

- [ ] **FOLIO-01**: Staff can read the folio of a reservation, including line items and payments (`GET /cms/reservations/{reservation}/folio`; 404 `folio_missing` if none)
- [ ] **FOLIO-02**: Staff can post a charge or credit line to an open folio (`kind`, `quantity × unit_price_usd`, credits need a reason and may reverse a charge); totals are recalculated by a DB SUM in one locked transaction; a settled folio returns 422 `folio_settled`, a credit may not exceed the reversed charge or take the balance below zero; an optional `Idempotency-Key` makes retries safe (`POST /cms/folios/{folio}/line-items`, permission `folios.post`); `GenerateFolioAction` reconciles by source instead of rebuilding so posted rows survive refreshes
- [ ] **FOLIO-03**: A line item can be disputed (history table, one open dispute per item) and the dispute resolved or rejected, by the guest (`PATCH /folio/items/{item}/dispute`; another guest's item → 404 `not_found`) and by staff (`PATCH /cms/folios/{folio}/line-items/{item}/dispute`, permission `folios.dispute`); open disputes are flagged on the folio and in night-audit checks, never block check-out and never move money (refunds are posted as credit lines)
- [ ] **FOLIO-04**: Staff can record a manual payment against a folio (`POST /cms/folios/{folio}/payments`, permission `folios.settle`, `Idempotency-Key` required); the balance counts folio and reservation-level payments; overpayment → 422 `folio_overpayment`, settled folio → 422 `folio_settled`; a payment that brings the balance to zero settles the folio automatically, and a folio whose balance is already zero can be closed without a payment via the settle route

### Housekeeping & Guest Services

- [ ] **HK-01**: Housekeeping and front desk can list housekeeping tasks with filters (status, room, assignee, type, due date) (`GET /housekeeping/tasks`)
- [ ] **HK-02**: A housekeeping task can be assigned to a staff member (`PATCH /housekeeping/tasks/{task}/assign`)
- [ ] **HK-03**: A housekeeping task moves through `pending → assigned → in_progress → done` (or `cancelled`) with a status history; completing a turnover task moves a `dirty` room to `available` via `UpdateRoomStatusAction` (a `maintenance` room is left untouched; no `inspected` state) (`PATCH /housekeeping/tasks/{task}/status`, permission `housekeeping.update`)
- [ ] **HK-04**: A turnover task is created automatically on check-out (exactly one open turnover per room, deduped at the database level), and a service request routed to `Department::HOUSEKEEPING` creates a request-type task; the room board's `dirty → available` click closes the open turnover task
- [ ] **HK-05**: Housekeeping tasks appear in the merged operations queue as a third item type (`/operations/queue/housekeeping-tasks/{uuid}`), reusing the polymorphic assign/status actions
- [ ] **SVC-01**: Staff can view a service-request board with filters and use the existing assign/status actions (`GET /cms/service-requests`, permission `service_requests.view`)
- [ ] **SVC-02**: Staff can list departure services (transfer bookings, `late_checkout` and `luggage` requests from two newly seeded direct categories, express checkouts derived from `reservations.check_out_mode`) for reservations departing on a hotel-local date, as an unpaginated projection with `stage`, `allowed_statuses` and `meta.truncated` (`GET /departure-services`)
- [ ] **SVC-03**: Staff can progress a departure service's status; the change is delegated to the underlying booking or request (`PATCH /departure-services/{uuid}/status`)
- [ ] **SVC-04**: Guest quick-request chips map to direct-category catalogue items via the existing service-request route; mapping documented for mobile (no new route)

### Support Tickets & Operations

- [ ] **TICKET-01**: Staff can list and view support tickets with filters (status, priority, department, assignee, source) (`GET /support-tickets`, `GET /support-tickets/{ticket}`)
- [ ] **TICKET-02**: Staff can create a support ticket on behalf of a guest or internally (`POST /support-tickets`, source `staff`)
- [ ] **TICKET-03**: Staff can change a ticket's status with a valid transition and a recorded action (`PATCH /support-tickets/{ticket}/status`)
- [ ] **TICKET-04**: Staff can assign a ticket to a staff member (`PATCH /support-tickets/{ticket}/assign`)
- [ ] **TICKET-05**: Staff can reply on a ticket; replies are stored as ticket actions (mirroring into chat is reserved, not implemented) (`POST /support-tickets/{ticket}/reply`)
- [ ] **TICKET-06**: Staff can record a service-recovery action (type, amount/description) on a ticket (`POST /support-tickets/{ticket}/recovery-actions`)
- [ ] **TICKET-07**: Staff can escalate a ticket to another staff member with a reason; escalation is timestamped (`POST /support-tickets/{ticket}/escalate`)
- [ ] **OPS-01**: Staff can claim an operations-queue item for themselves (`PATCH /operations/queue/{type}/{uuid}/claim`)
- [ ] **OPS-02**: Staff can list assignable staff filtered by department/permission (`GET /operations/staff`)
- [ ] **OPS-03**: Queue items expose their `queue_type` path segment so the dashboard can build `{queue_type}/{uuid}` paths

### Events & Dining

- [x] **EVENT-01**: Staff can toggle checklist items on an event inquiry (`PATCH /cms/event-inquiries/{inquiry}/checklist/{item}`)
- [x] **EVENT-02**: Staff can record a deposit against an event inquiry using the existing payment action, idempotent via `Idempotency-Key` (`PATCH /cms/event-inquiries/{inquiry}/deposit`)
- [x] **EVENT-03**: Staff can update internal event inquiry notes (`staff_notes`) (`PATCH /cms/event-inquiries/{inquiry}/notes`)
- [x] **DINING-01**: Staff can list restaurant table reservations with venue/date filters (`GET /cms/table-reservations`)
- [x] **DINING-02**: Guest can download a venue menu (media URL, 204 when none, 404 for unknown/inactive venue; staff manage the file via `/cms/dining-venues/{venue}/menu-file`) (`GET /public/dining-venues/{venue}/menu/download`)

### Night Audit & Reports

- [x] **AUDIT-01**: Staff can open the night audit for a business date; it is created lazily and its checks (unsettled departures, unassigned arrivals, dirty rooms, open high-priority tickets, open folio disputes) are evaluated and persisted (`GET /operations/night-audit?date`, permission `reports.view` or `night_audit.manage`; initializing the business date requires `night_audit.manage`)
- [x] **AUDIT-02**: Staff can mark a night-audit check resolved/overridden with a note (`PATCH /operations/night-audit/checks/{check}`, permission `night_audit.manage`)
- [x] **AUDIT-03**: Staff can resolve a night-audit blocker (`PATCH /operations/night-audit/blockers/{blocker}`, permission `night_audit.manage`)
- [x] **AUDIT-04** (scope completion, Phase 9): A manager closes the current business date once every check is terminal and every blocker resolved (`POST /operations/night-audit/{audit}/close`, permission `night_audit.manage`); this advances the business date by one day
- [x] **REPORT-01**: Staff can read a reports dashboard (occupancy, arrivals/departures, revenue, open requests/tickets) (`GET /reports/dashboard`, permission `reports.view`)

### Loyalty Points Program

- [ ] **LOY-01**: Staff can read and update the six program settings (earn rate, redeem value, expiry months, expiry-warning days, minimum points to redeem, max % payable with points); changes are audited
- [x] **LOY-02**: With no rates configured the program is inactive (no earning, redemption refused); no rates are seeded
- [ ] **LOY-03**: Settling a folio credits integer points (round half up) on room-stay spend and on services/F&B spend, once, atomically with settlement, under every settlement path, never on a cancelled reservation
- [x] **LOY-04**: Earning is idempotent: a retried or concurrent settlement never double-credits; guests with no account and unconfigured programs earn nothing; no historical backfill
- [ ] **LOY-05**: Staff can award or deduct points manually with a mandatory reason, idempotently and audited; a deduction can never exceed the available balance
- [ ] **LOY-06**: A guest sees available points, points expiring soon, and a paginated ledger (earn / redeem / expire / adjust / clawback / refund) tied to bookings
- [ ] **LOY-07**: Staff can view any guest's balance and ledger
- [x] **LOY-08**: Each earn batch expires after the configured months (hotel-local end of day); points are consumed FIFO; expired points are never spendable even before the sweep runs
- [ ] **LOY-09**: A daily job expires batches and writes expire ledger entries idempotently
- [ ] **LOY-10**: A guest is notified once per batch N days before expiry through the existing notification system, in the guest's language
- [ ] **LOY-11**: Staff manage a rewards catalog (AR/EN name and description, points cost, type discount voucher / free night / room upgrade) with a recycle bin
- [ ] **LOY-12**: A guest can browse active rewards
- [ ] **LOY-13**: A guest redeems a reward into a voucher (code, status, expiry); the redeem is idempotent and transactional and spends points FIFO
- [ ] **LOY-14**: A guest lists their own vouchers by status
- [ ] **LOY-15**: A guest can preview points earnable and the discount for a prospective booking without side effects
- [ ] **LOY-16**: A guest can pay part of a booking with points (free-form) subject to the minimum and the max-% cap, or apply one voucher; the discount reduces the reservation total, is atomic with reservation creation and idempotent via `Idempotency-Key` (replay returns the same reservation)
- [x] **LOY-17**: Cancelling a reservation refunds spent points, restores a used voucher and claws back points earned from its settled folio, idempotently, never producing a negative balance
- [ ] **LOY-18**: A callable, tested folio-refund reversal exists for the future refund flow
- [ ] **LOY-19**: Staff can report points issued (earn + positive adjust), redeemed, expired, refunded, clawed back, adjusted out and outstanding points over a period
- [ ] **LOY-20**: New `loyalty.*` permissions are seeded, enforced by route middleware and shown in the permission picker; no preset changes
- [x] **LOY-21**: Every new string exists in all five locale files; every new route has happy / 401 / 403 / 422 tests; docs, Postman and the tree are updated
- [x] **LOY-22**: With `redeem_value_usd` or `max_redeem_percent` unset, points-to-discount is refused (`loyalty_program_inactive`); an unset cap never behaves as 100%, and catalog redemption stays available

### Documentation & Contract

- [ ] **DOCS-01**: For each phase the dashboard/mobile API guides, Postman collection and `docs/carlton-tree.html` are updated, and path changes the dashboard must adopt are listed in the phase summary
- [ ] **XCUT-01**: Every new permission follows the `{domain}.{action}` convention, is seeded in `RolesAndPermissionsSeeder` with the role presets that receive it, and is listed in the phase summary for the dashboard team

## v2 Requirements

### Providers & Realtime

- **PROV-01**: SMS / WhatsApp OTP provider replaces the static OTP
- **PROV-02**: Online payment gateway driver alongside the manual driver
- **RT-01**: Websocket/live updates for the operations queue
- **TICKET-08**: Staff ticket replies mirrored into the guest chat when a conversation exists

### AI

- **AI-01**: AI concierge (P11) with its own AI-SPEC

## Out of Scope

| Feature | Reason |
|---------|--------|
| Alias routes at the dashboard's mocked paths | Doubles the 401/403/422 test matrix and splits permissions; dashboard adopts `/cms/{resource}/{uuid}` |
| Rate editing in the rate grid | Grid is read-only in the UI; pricing rules already have CMS |
| Guest-visible ticket replies | No confirmed requirement; council decision 2026-09-25 keeps replies in `ticket_actions` only |
| Frontend changes | Separate repos/teams; backend documents the paths they must adopt |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ACCESS-01 | Phase 1 | Pending |
| ACCESS-02 | Phase 1 | Pending |
| ACCESS-03 | Phase 1 | Pending |
| DOCS-01 | Phase 1 (enforced as contract gate in every phase) | Pending |
| XCUT-01 | Phase 1 (enforced as contract gate in every phase) | Pending |
| ROOMS-01 | Phase 2 | Pending |
| ROOMS-02 | Phase 2 | Pending |
| ROOMS-03 | Phase 2 | Pending |
| ROOMS-04 | Phase 2 | Pending |
| RESV-01 | Phase 3 | Pending |
| RESV-02 | Phase 3 | Pending |
| RESV-03 | Phase 3 | Pending |
| RESV-04 | Phase 3 | Pending |
| GUEST-01 | Phase 4 | Pending |
| GUEST-02 | Phase 4 | Pending |
| GUEST-03 | Phase 4 | Pending |
| GUEST-04 | Phase 4 | Pending |
| GUEST-05 | Phase 4 | Pending |
| GUEST-06 | Phase 4 | Pending |
| FOLIO-01 | Phase 5 | Pending |
| FOLIO-02 | Phase 5 | Pending |
| FOLIO-03 | Phase 5 (night-audit flag surfaced in Phase 9) | Pending |
| FOLIO-04 | Phase 5 | Pending |
| HK-01 | Phase 6 | Pending |
| HK-02 | Phase 6 | Pending |
| HK-03 | Phase 6 | Pending |
| HK-04 | Phase 6 | Pending |
| HK-05 | Phase 6 | Pending |
| SVC-01 | Phase 6 | Pending |
| SVC-02 | Phase 6 | Pending |
| SVC-03 | Phase 6 | Pending |
| SVC-04 | Phase 6 | Pending |
| TICKET-01 | Phase 7 | Pending |
| TICKET-02 | Phase 7 | Pending |
| TICKET-03 | Phase 7 | Pending |
| TICKET-04 | Phase 7 | Pending |
| TICKET-05 | Phase 7 | Pending |
| TICKET-06 | Phase 7 | Pending |
| TICKET-07 | Phase 7 | Pending |
| OPS-01 | Phase 7 | Pending |
| OPS-02 | Phase 7 | Pending |
| OPS-03 | Phase 7 | Pending |
| EVENT-01 | Phase 8 | Complete (3886416) |
| EVENT-02 | Phase 8 | Complete (3886416) |
| EVENT-03 | Phase 8 | Complete (3886416) |
| DINING-01 | Phase 8 | Complete (3886416) |
| DINING-02 | Phase 8 | Complete (3886416) |
| AUDIT-01 | Phase 9 | Complete |
| AUDIT-02 | Phase 9 | Complete |
| AUDIT-03 | Phase 9 | Complete |
| AUDIT-04 | Phase 9 | Complete |
| REPORT-01 | Phase 9 | Complete |

**Coverage:**
- v1 requirements: 74 total (AUDIT-04 added 2026-10-04 as Phase 9 scope completion; LOY-01..22 added 2026-10-04 for Phase 10)
- Mapped to phases: 74
- Unmapped: 0 ✓

---
*Requirements defined: 2026-09-25*
*Last updated: 2026-10-04 — Phase 10 planning: LOY-01..LOY-22 added*
