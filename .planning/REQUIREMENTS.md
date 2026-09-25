# Requirements: Carlton Hotel Backend — API Gap Closure

**Defined:** 2026-09-25
**Core Value:** Every screen the dashboard and guest app already show works against a real, tested, convention-compliant `/api/v1` endpoint instead of mock data.

All routes are under `/api/v1`. Staff routes use `auth:users` + permission middleware; guest routes use `auth:guests` (+ `has_booking` / `is_checked_in` gates where the existing pattern applies). Public ids are UUIDs. Every requirement is done only when its feature tests (happy / 401 / 403 / 422), AR/EN keys, API guide + Postman entries exist and its node in `docs/carlton-tree.html` is flipped to `api:true`.

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

- [ ] **GUEST-01**: Staff can browse and search the guest directory with filters (name, phone, email, stay status) (`GET /guests`, permission `guests.view`)
- [ ] **GUEST-02**: Staff can open a guest profile that includes stay history, preferences and a derived pre-arrival checklist (documents, approval, preferences, arrival time) (`GET /guests/{guest}`)
- [ ] **GUEST-03**: Staff can add internal notes to a guest (`POST /guests/{guest}/notes`, permission `guests.edit`)
- [ ] **GUEST-04**: Guest preferences (bed type, pillow, floor, other) can be saved by the guest (`PATCH /auth/guest/preferences`) and by staff (`PATCH /guests/{guest}/preferences`)
- [ ] **GUEST-05**: Guest can complete online check-in for an upcoming stay by submitting an arrival time; a digital key code (random, high-entropy, expires at check-out and is invalidated on check-out/cancel; no lock-hardware integration) is issued when the check-in approval is approved (`POST /stays/{reservation}/online-check-in`, key returned on the stay resource)
- [ ] **GUEST-06**: Guest ID scan uploads use the existing `POST /pre-arrival/documents` route; mobile wiring documented (no new route)

### Folio & Payments

- [ ] **FOLIO-01**: Staff can read the folio of a reservation, including line items and payments (`GET /cms/reservations/{reservation}/folio`; 404 `folio_missing` if none)
- [ ] **FOLIO-02**: Staff can post a manual line item to an open folio; totals are recalculated in one locked transaction (`POST /cms/folios/{folio}/line-items`, permission `folios.post`)
- [ ] **FOLIO-03**: A line item can be disputed and the dispute resolved, by the guest (`PATCH /folio/items/{item}/dispute`) and by staff (`PATCH /cms/folios/{folio}/line-items/{item}/dispute`); open disputes are flagged on the folio and in night-audit checks but do not block check-out
- [ ] **FOLIO-04**: Staff can record a payment against a folio using the existing manual payment methods (`POST /cms/folios/{folio}/payments`)

### Housekeeping & Guest Services

- [ ] **HK-01**: Housekeeping and front desk can list housekeeping tasks with filters (status, room, assignee, type, due date) (`GET /housekeeping/tasks`)
- [ ] **HK-02**: A housekeeping task can be assigned to a staff member (`PATCH /housekeeping/tasks/{task}/assign`)
- [ ] **HK-03**: A housekeeping task can move through its statuses; completing a turnover task moves the room to inspected/available (`PATCH /housekeeping/tasks/{task}/status`)
- [ ] **HK-04**: A turnover task is created automatically on check-out, and a housekeeping-department service request creates a request-type task
- [ ] **HK-05**: Housekeeping tasks appear in the merged operations queue as a third item type (`/operations/queue/housekeeping-tasks/{uuid}`), reusing the polymorphic assign/status actions
- [ ] **SVC-01**: Staff can view a service-request board with filters and use the existing assign/status actions (`GET /cms/service-requests`, permission `service_requests.view`)
- [ ] **SVC-02**: Staff can list departure services (transfers, late checkout, luggage, express checkout) for departing reservations (`GET /departure-services`)
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
- [ ] **OPS-03**: Queue items expose their `type` so the dashboard can build `{type}/{uuid}` paths

### Events & Dining

- [ ] **EVENT-01**: Staff can toggle checklist items on an event inquiry (`PATCH /cms/event-inquiries/{inquiry}/checklist/{item}`)
- [ ] **EVENT-02**: Staff can record a deposit against an event inquiry using the existing payment action (`PATCH /cms/event-inquiries/{inquiry}/deposit`)
- [ ] **EVENT-03**: Staff can update event inquiry notes (`PATCH /cms/event-inquiries/{inquiry}/notes`)
- [ ] **DINING-01**: Staff can list restaurant table reservations with venue/date filters (`GET /cms/table-reservations`)
- [ ] **DINING-02**: Guest can download a venue menu (media URL or 204 when none) (`GET /public/dining-venues/{venue}/menu/download`)

### Night Audit & Reports

- [ ] **AUDIT-01**: Staff can open the night audit for a business date; it is created lazily and its checks (unsettled departures, unassigned arrivals, dirty rooms, open high-priority tickets) are evaluated and persisted (`GET /operations/night-audit?date`, permission `reports.view`)
- [ ] **AUDIT-02**: Staff can mark a night-audit check resolved/overridden with a note (`PATCH /operations/night-audit/checks/{check}`)
- [ ] **AUDIT-03**: Staff can resolve a night-audit blocker (`PATCH /operations/night-audit/blockers/{blocker}`)
- [ ] **REPORT-01**: Staff can read a reports dashboard (occupancy, arrivals/departures, revenue, open requests/tickets) (`GET /reports/dashboard`, permission `reports.view`)

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
| EVENT-01 | Phase 8 | Pending |
| EVENT-02 | Phase 8 | Pending |
| EVENT-03 | Phase 8 | Pending |
| DINING-01 | Phase 8 | Pending |
| DINING-02 | Phase 8 | Pending |
| AUDIT-01 | Phase 9 | Pending |
| AUDIT-02 | Phase 9 | Pending |
| AUDIT-03 | Phase 9 | Pending |
| REPORT-01 | Phase 9 | Pending |

**Coverage:**
- v1 requirements: 51 total
- Mapped to phases: 51
- Unmapped: 0 ✓

---
*Requirements defined: 2026-09-25*
*Last updated: 2026-09-25 after roadmap creation*
