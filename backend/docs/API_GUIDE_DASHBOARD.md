# Carlton Hotel — API Guide: Staff Dashboard

> **Audience:** frontend developers building the staff/admin dashboard.
> **Surface:** Staff-only, permission-gated. All requests require a staff bearer token obtained via `POST /auth/login`. Permissions control which sections are accessible — read the `permissions` array from the login response to decide what to render.
> **Try it now:** `php artisan migrate:fresh --seed` populates realistic demo data (staff, guests, bookings, tickets, everything), and `docs/postman/` has a ready-to-import Postman collection + environment pre-loaded with working tokens. See `docs/postman/README.md`.

---

## Base URL

```
https://api.carlton.example.com/api
```

All paths below are relative to this base.

## Standard headers

| Header | When | Value |
|---|---|---|
| `Accept` | Always | `application/json` |
| `Accept-Language` | Always | `en` or `ar` — controls only `message`/error/validation strings |
| `Content-Type` | Requests with a body | `application/json` |
| `Authorization` | All authenticated requests | `Bearer <staff-token>` |

**`Accept-Language` does NOT localize content fields.** Bilingual CMS content (room names, menu items, page bodies, etc.) is always returned as `{ "en": "...", "ar": "..." }` — the header only picks the language of the envelope's `message` and validation error strings. The dashboard is responsible for showing/editing both locales itself.

## Standard response envelope

Every response is wrapped in this envelope.

**Success:**
```json
{
  "success": true,
  "message": "Human-readable string (locale-aware).",
  "data": { "...resource fields..." },
  "request_id": "uuid-for-support-tracing"
}
```

**Paginated success** — `data` becomes:
```json
{
  "items": [...],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Human-readable error.",
  "error_code": "stable_snake_case_code",
  "context": {},
  "request_id": "uuid"
}
```

**Validation error:**
```json
{
  "success": false,
  "error_code": "validation_failed",
  "errors": { "field_name": ["message"] },
  "request_id": "uuid"
}
```

Log `request_id` on every response for support tracing.

## Permission model

After login, the `permissions` array in the user object is the source of truth for what the logged-in staff member can do. Use it to show/hide sections. The server enforces all permission gates server-side — hiding UI is convenience only.

A `super_admin` account bypasses all permission checks on the server.

**Full permission catalog** (seeded since P0, 8 modules): `reservations.view|create|cancel`, `folios.view|settle`, `cms.view|edit`, `service_requests.view|assign|update`, `tickets.view|assign|respond`, `pricing.edit`, `reports.view`, `staff.manage`. `cms.view` and `pricing.edit` are reserved — no route currently gates on them (`cms.edit` alone gates every CMS write route, and dynamic pricing has no admin UI yet).

**Role presets** (5): `reception`, `kitchen`, `housekeeping`, `concierge`, `events` — see Module: Reference Data below for exactly which permissions each preset grants.

---

## Module: System

### GET /api/health

**Purpose:** Liveness probe. Use for dashboard status indicators.

**Who can call:** Public (no token required).

**Response `data`:** `{ "status": "ok", "time": "2026-07-08T14:00:00Z" }`

---

## Module: Staff Auth

### POST /api/auth/login

**Purpose:** Staff login. Returns a bearer token + the staff member's full profile and permission list.

**Who can call:** Public — this is how a token is obtained.

**When in flow:** First call. Store the token and use it on all subsequent requests.

**Request body:**

| Field | Type | Required |
|---|---|---|
| `email` | string | ✅ |
| `password` | string | ✅ |

**Response `data`:**
```json
{
  "token": "1|abcdef...",
  "user": {
    "uuid": "...",
    "name": "John Smith",
    "email": "john@carlton.com",
    "type": "staff",
    "is_active": true,
    "roles": ["reception"],
    "permissions": ["reservations.view", "reservations.create", "folios.view", "folios.settle"]
  }
}
```

`type` is either `staff` or `super_admin`. The `permissions` array lists every permission the account effectively holds (role preset + direct grants − direct revokes).

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `credentials_invalid` | 401 | "Invalid credentials." Do NOT distinguish wrong email from wrong password. |
| `account_inactive` | 403 | "Account disabled. Contact your administrator." |
| `validation_failed` | 422 | Missing fields. |

**State notes:** Store the token. Persist the `permissions` array for local gate checks (server re-enforces on every request). The `user.uuid` is the stable identifier — never use integer IDs.

---

### POST /api/auth/logout

**Purpose:** Invalidate the current token server-side.

**Who can call:** Any authenticated staff member.

**Request:** `Authorization: Bearer <token>` header. No body.

**Response `data`:** `null`

**State notes:** Discard the stored token on success. Redirect to login.

---

### GET /api/auth/me

**Purpose:** Refresh the current staff member's profile and permission list (e.g. after a role or permission change by an admin).

**Who can call:** Any authenticated staff member.

**Request:** `Authorization: Bearer <token>`. No body.

**Response `data`:** Same shape as `user` in `/auth/login`.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `unauthorized` | 401 | Token expired or invalid — redirect to login. |

---

## Module: Staff Management

All endpoints below require `Authorization: Bearer <token>` and the account must hold the `staff.manage` permission (or be `super_admin`).

### Staff object shape

```json
{
  "uuid": "...",
  "name": "Jane Doe",
  "email": "jane@carlton.com",
  "type": "staff",
  "is_active": true,
  "roles": ["housekeeping"],
  "effective_permissions": ["cms.view", "service_requests.view"],
  "direct_permissions": ["cms.view"],
  "role_permissions": ["service_requests.view"]
}
```

- `effective_permissions` — ground truth for what the account can do (role permissions + direct grants − direct revokes).
- `direct_permissions` — manual per-account overrides added on top of the role.
- `role_permissions` — what the assigned role preset provides.

---

### GET /api/staff

**Purpose:** List all staff accounts (paginated).

**Response `data`:** Paginated list of staff objects.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `forbidden` | 403 | Missing `staff.manage` permission. |
| `unauthorized` | 401 | Token invalid/expired. |

---

### POST /api/staff

**Purpose:** Create a new staff account from a role preset.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | ✅ | |
| `email` | string | ✅ | Must be unique |
| `password` | string | ✅ | Min 8 characters |
| `role` | string | ✅ | One of the preset names from `GET /roles` |

**Response `data`:** The created staff object (HTTP 201).

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `forbidden` | 403 | Missing `staff.manage`. |
| `validation_failed` | 422 | Email taken, unknown role, password too short, etc. |

---

### GET /api/staff/{uuid}

**Purpose:** Get a single staff member's full profile including permission breakdown.

**Response `data`:** Staff object.

---

### PUT /api/staff/{uuid}

**Purpose:** Update a staff member's name and/or email. Does NOT change role or permissions.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | optional | |
| `email` | string | optional | Must be unique |

**Response `data`:** Updated staff object.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `forbidden` | 403 | Cannot edit a `super_admin` unless caller is also `super_admin`. |
| `validation_failed` | 422 | Email taken. |

---

### POST /api/staff/{uuid}/permissions

**Purpose:** Grant or revoke individual permissions on a staff account (overrides beyond the role preset).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `grant` | array of strings | optional | Permission names to add |
| `revoke` | array of strings | optional | Permission names to remove |

At least one of `grant` or `revoke` must be non-empty. The two arrays must not overlap.

**Response `data`:** Updated staff object with new `effective_permissions`.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `forbidden` | 403 | Escalation attempt (trying to grant a permission you don't hold yourself) OR target is `super_admin`. |
| `validation_failed` | 422 | Overlapping arrays, unknown permission name, both arrays empty. |

**Important:** A staff manager cannot grant permissions they don't hold themselves. The `effective_permissions` array on your own `GET /auth/me` response is the ceiling for what you can grant.

---

### PATCH /api/staff/{uuid}/deactivate

**Purpose:** Deactivate a staff account. Immediately invalidates all their active tokens — they are logged out on all devices.

**Request:** No body.

**Response `data`:** Updated staff object with `is_active: false`.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `forbidden` | 403 | Cannot deactivate yourself (`cannot_self_deactivate`) or a `super_admin`. |

---

## Module: Reference Data

### GET /api/permissions

**Purpose:** Full list of all permissions in the system, grouped by module. Use to populate the permission assignment UI.

**Who can call:** Authenticated staff with `staff.manage`.

**Response `data`:** Array of module groups:
```json
[
  {
    "module": "reservations",
    "permissions": ["reservations.view", "reservations.create", "reservations.cancel"]
  },
  {
    "module": "service_requests",
    "permissions": ["service_requests.view", "service_requests.assign", "service_requests.update"]
  }
]
```

8 modules: `reservations`, `folios`, `cms`, `service_requests`, `tickets`, `pricing`, `reports`, `staff`.

---

### GET /api/roles

**Purpose:** List all role presets and their included permissions. Use to populate the role dropdown when creating a staff account.

**Who can call:** Authenticated staff with `staff.manage`.

**Response `data`:** Array of presets:
```json
[
  { "name": "reception", "permissions": ["reservations.view", "reservations.create", "reservations.cancel", "folios.view", "folios.settle", "service_requests.view"] },
  { "name": "kitchen", "permissions": ["service_requests.view", "service_requests.update"] },
  { "name": "housekeeping", "permissions": ["service_requests.view", "service_requests.update"] },
  { "name": "concierge", "permissions": ["service_requests.view", "service_requests.assign", "service_requests.update"] },
  { "name": "events", "permissions": ["service_requests.view", "tickets.view", "tickets.assign", "tickets.respond"] }
]
```

---

## Module: CMS Content (`cms.edit`)

Admin CRUD for the 7 content types the website/app read publicly. Every type follows the same shape: `GET`/`POST` on the collection, `GET`/`PUT`/`DELETE` on `/{uuid}` (Page also gets these — admin addresses pages by uuid even though the public route uses `slug`). All under `auth:users` + `permission:cms.edit`.

| Type | Base path |
|---|---|
| Room types | `/cms/room-types` |
| Rooms | `/cms/rooms` |
| Facilities | `/cms/facilities` |
| Dining venues | `/cms/dining-venues` |
| Event spaces | `/cms/event-spaces` |
| Pages | `/cms/pages` |
| Promotions | `/cms/promotions` |

Response shapes are identical to the public read shapes in `API_GUIDE_WEBSITE.md`'s Module: Content — the same Resource class serves both admin and public routes. `destroy` returns HTTP 204, `data: null`.

### Create/update field reference

**Room type** — `name.en/ar`, `description.en/ar` (required strings), `amenities` (array of strings, optional), `base_occupancy`/`max_occupancy` (int 1–20, `max_occupancy >= base_occupancy`), `size_sqm` (optional numeric), `base_price_usd` (required numeric ≥0), `is_active`, `sort_order`.

**Room** — `room_type_uuid` (**not** `room_type_id` — required, must exist), `number` (required, unique), `floor` (optional int 0–200), `status` (`available`/`occupied`/`maintenance`), `is_active`.

**Facility** — `name.en/ar`, `description.en/ar` (required), `location.en/ar`, `hours.en/ar` (optional), `is_active`, `sort_order`.

**Dining venue** — `name.en/ar`, `description.en/ar` (required), `cuisine_type.en/ar`, `location.en/ar`, `hours.en/ar` (optional), `is_active`, `sort_order`.

**Event space** — `name.en/ar`, `description.en/ar` (required), `capacity` (optional int ≥1), `location.en/ar`, `amenities.en/ar` (translatable free text, optional), `is_active`, `sort_order`.

**Page** — `slug` (required, unique, `^[a-z0-9-]+$`), `title.en/ar`, `content.en/ar` (required), `is_active`, `sort_order`. No image gallery on this type.

**Promotion** — `title.en/ar`, `description.en/ar` (required), `terms.en/ar` (optional), `valid_from`/`valid_until` (optional dates, `valid_until >= valid_from`), `is_active`, `sort_order`.

On `update`, all fields become `sometimes` (translatable required fields become `sometimes|required` — you can't submit an incomplete translation, but you can omit the field entirely to leave it unchanged).

### Images

Every type except Page supports a gallery:

- `POST /cms/{type}/{uuid}/images` — multipart, `image` (required file, `jpg`/`jpeg`/`png`/`webp`, max 5MB), `sort_order` (optional int, default 0). Returns HTTP 201, the created media object: `{ "uuid", "url", "file_name", "mime_type", "size", "sort_order" }`.
- `DELETE /cms/{type}/{uuid}/images/{media}` — removes the file and the record. HTTP 204. There's no bulk-delete — each image is removed individually.

---

## Module: Reservations (`reservations.*`, `folios.settle`)

### GET /cms/reservations — `reservations.view`

Paginated, all reservations, newest first.

### GET /cms/reservations/{uuid} — `reservations.view`

**Response `data`:**
```json
{
  "uuid": "...", "booking_code": "CARL-XXXXXXXX", "status": "confirmed",
  "check_in": "2026-07-20", "check_out": "2026-07-22", "nights": 2,
  "source": "direct", "payment_method": "cash", "total_usd": "270.00", "hold_expires_at": null,
  "rooms": [ { "room_type": { "...room type..." }, "room_uuid": null, "room_number": null, "price_usd": "270.00" } ],
  "guest": { "uuid": "...", "name": "...", "phone": "...", "email": "..." },
  "promo_code": null
}
```

### POST /cms/reservations/{uuid}/confirm — `reservations.create`

**Purpose:** Confirm a `pending`/`pending_verification` reservation.

**Request:** No body. **Response:** updated reservation, `status: "confirmed"`.

**Failure:** `reservation_state` (422) if already confirmed or otherwise not confirmable.

### POST /cms/reservations/{uuid}/assign-room — `reservations.create`

**Purpose:** Physically assign a room at check-in. **This is the check-in action — there is no separate "check in" endpoint.**

**Request body:** `{ "room_uuid": "..." }` (required, must exist).

**Behavior:** requires the reservation to be `confirmed`, the room's type to match the booked type, and no date-overlapping assignment of that room elsewhere. On success, sets `room_id` and flips reservation `status` to `checked_in`.

**Response `data`:** updated reservation with the room now populated under `rooms[].room_uuid`/`room_number`.

**Failure `error_code`s:** `reservation_state` (422, wrong status or type mismatch), `room_already_assigned` (409, overlapping dates), `validation_failed` (422, bad `room_uuid`).

### DELETE /cms/reservations/{uuid} — `reservations.cancel`

**Purpose:** Cancel a reservation. Same cancellable-state rules as the guest's own cancel (`pending_verification`/`pending`/`confirmed`).

**Response:** HTTP 204. **Failure:** `reservation_state` (422).

### POST /cms/reservations/{uuid}/settle — `folios.settle`

**Purpose:** Record a cash/on-arrival payment against a reservation directly (independent of the folio flow in P8 — this settles the reservation total itself).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `method` | string | ✅ | `cash` or `on_arrival` |
| `amount_usd` | number | ✅ | Min 0.01 |
| `note` | string | optional | Max 1000 |

**Behavior:** creates a `Payment` record with `recorded_by` = your user; if the reservation was `pending`, transitions it to `confirmed` (no-op on already-confirmed/other states).

**Response `data`** (message: "Payment recorded successfully."):
```json
{ "uuid": "...", "method": "cash", "amount_usd": "270.00", "status": "completed", "note": "...", "recorded_by": "staff-uuid", "created_at": "..." }
```

**Failure `error_code`s:** `payment_failed` (422, gateway rejected — unreachable with the current cash-only driver), `validation_failed` (422).

### Reservation status reference

| Status | Set by |
|---|---|
| `pending_verification` | Guest booking created via the public two-step flow, awaiting OTP |
| `pending` | Booking active, no room assigned |
| `confirmed` | Admin `confirm`, or a settled payment while pending |
| `checked_in` | Admin `assign-room` |
| `checked_out` | Guest approves express checkout (P8) |
| `cancelled` | Terminal — guest or admin cancel, or an expired soft-hold auto-releasing |

---

## Module: Event Inquiries (RFP triage)

### GET /cms/event-inquiries — `tickets.view`

Paginated, newest first.

### GET /cms/event-inquiries/{uuid} — `tickets.view`

**Response `data`:**
```json
{
  "uuid": "...", "name": "...", "email": "...", "phone": "...", "company": "...",
  "event_type": "corporate", "event_date": "2026-08-01", "expected_guests": 120, "budget_usd": "5000.00",
  "notes": "...", "status": "new", "department": "sales", "assigned_to": null,
  "requirements": [ { "uuid": "...", "type": "av_equipment", "notes": "..." } ],
  "created_at": "..."
}
```

### PATCH /cms/event-inquiries/{uuid}/status — `tickets.assign`

**Request body:** `{ "status": "in_review" | "quoted" | "confirmed" | "cancelled" }` (note: you cannot transition back to `new`).

**Allowed transitions:** `new`→`in_review`/`cancelled`; `in_review`→`quoted`/`cancelled`; `quoted`→`confirmed`/`cancelled`; `confirmed`→`cancelled`. `cancelled` is terminal.

**Failure:** `inquiry_state` (422) on an invalid transition — a distinct code from `reservation_state`, don't conflate them.

### PATCH /cms/event-inquiries/{uuid}/assign — `tickets.assign`

**Request body:** `{ "user_uuid": "..." }` (required, must exist).

**Behavior:** sets `assigned_to`; if the inquiry was `new`, also auto-advances it to `in_review`.

### Department routing (informational — set at submit time, not editable)

`corporate`, `conference`, `product_launch` → `sales`; everything else → `events`.

---

## Module: In-Stay Service Catalog (`cms.edit`)

Standard `apiResource` CRUD (index/store/show/update/destroy) for the 6 bookable/orderable catalog types staff maintain. All under `permission:cms.edit`.

| Type | Base path | Fillable fields |
|---|---|---|
| Spa services | `/cms/spa-services` | `name.en/ar` (required), `duration_minutes` (required int ≥1), `price_usd` (required ≥0), `is_active` |
| Restaurant tables | `/cms/restaurant-tables` | `dining_venue_uuid` (optional, must exist), `table_number` (required, max 50), `capacity` (required int ≥1), `is_active` — **not translatable, no `name`** |
| Pool cabanas | `/cms/pool-cabanas` | `name.en/ar` (required), `capacity` (required int ≥1), `price_usd` (required ≥0), `is_active` |
| Transfers | `/cms/transfers` | `name.en/ar` (required), `price_usd` (required ≥0), `is_active` — no capacity/duration |
| Menu categories | `/cms/menu-categories` | `name.en/ar` (required), `sort_order` (optional int ≥0), `is_active` |
| Menu items | `/cms/menu-items` | `menu_category_uuid` (required, must exist), `name.en/ar` (required), `description.en/ar` (optional), `price_usd` (required ≥0), `is_active` |

Response shapes mirror the fillable fields 1:1 (bilingual fields as `{en, ar}`, foreign keys exposed as `_uuid`, never the internal integer id). Menu categories nest their items under `items: []` when the relation is loaded.

---

## Module: Pre-Arrival Check-In Approvals (`reservations.create`)

### GET /cms/check-in-approvals

**Purpose:** Review guests' uploaded pre-arrival documents before approving e-check-in.

**Response:** paginated, newest first.
```json
{
  "uuid": "...", "reservation_uuid": "...", "status": "pending", "approved_by": null, "notes": null,
  "documents": [ { "uuid": "...", "type": "passport" } ]
}
```

### PATCH /cms/check-in-approvals/{reservation}/approve

**Note the URL param is the reservation, not the approval row.**

**Request body:** `{ "status": "approved" | "rejected", "notes"?: string }` (max 1000).

**Response `data`:** updated approval object, `documents` re-populated, `approved_by` set to your uuid.

**Failure:** `not_found` (404) if the reservation has no submitted documents/approval row yet.

---

## Module: Folios & Express Checkout (`folios.view`, `folios.settle`)

### POST /cms/folios/{reservation}/generate — `folios.view`

**Purpose:** Generate/refresh a reservation's folio (idempotent — one folio per reservation).

**Request:** No body. **Response `data`:** Folio object (see shape below).

### POST /cms/folios/{folio}/settle — `folios.settle`

**Purpose:** Record cash/on-arrival payment against a folio and close it.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `method` | string | ✅ | `cash` or `on_arrival` |
| `amount_usd` | number | ✅ | Min 0.01 |
| `note` | string | optional | Max 1000 |

**Behavior:** row-locks the folio, guards against double-settle, records a `Payment`, sets folio `status: "settled"` + `settled_at`. Does not touch reservation status (that's the guest's `folio/approve` action, or a separate admin flow).

**Response `data`** (message: "Folio settled."):
```json
{
  "uuid": "...", "reservation_uuid": "...", "status": "settled",
  "subtotal_usd": "300.00", "total_usd": "300.00",
  "approved_by_guest_at": null, "settled_at": "2026-07-16T10:05:00+00:00",
  "items": [ { "uuid": "...", "description": "Room charge", "amount_usd": "300.00", "source_type": "reservation" } ]
}
```
Note this response is the Folio, not a Payment object — the settlement's own `Payment` record isn't surfaced inline here.

**Failure `error_code`s:** `reservation_state` (422, already settled — double-settle guard), `payment_failed` (422), `validation_failed` (422).

---

## Module: Chat (P9)

Guest↔staff messaging. `tickets.view` reads, `tickets.respond` replies (both seeded since P0).

- `GET /api/cms/conversations` — all conversations, most recent first (`tickets.view`).
- `GET /api/cms/conversations/{uuid}/messages` — paginated history, oldest first (`tickets.view`).
- `POST /api/cms/conversations/{uuid}/messages` — reply; body `{ "body"?: string, "attachment"?: file }` (`tickets.respond`). Claims the conversation (sets `assigned_user_id` to the replying staff member) on the first staff reply if unassigned.

Mirrors to Firestore the same way as the guest side (see `API_GUIDE_MOBILE.md`) — subscribe for live updates.

No push notification is sent to staff (the dashboard is web; it live-subscribes to Firestore instead of FCM).

---

## Module: Operations Queue & Dashboard (P10)

The unified read+assign layer over `service_requests` and `tickets` (chatbot-created — empty until P11 ships). Every mutation mirrors live to the same Firestore `ops_queue` collection service-request creation already writes to (see `API_GUIDE_MOBILE.md`). The queue only ever shows **active** work — completed/cancelled service requests and resolved/closed tickets are excluded, not just paginated away.

- `GET /api/operations/queue` — merged, newest-first, paginated. Requires `service_requests.view` **or** `tickets.view`; each table is included only if the caller holds its own `.view` permission (holding just one silently omits the other, not a 403). Each item: `{ type: "service_request"|"ticket", uuid, subject, department, status, priority, assigned_user_uuid, created_at }`. `subject` is the service request's `type` or the ticket's `subject`. `priority` is always a string (`low`/`normal`/`high`) — ticket priority is stored as a 1–3 int internally but normalized here so the field never changes type between rows.
- `PATCH /api/operations/queue/{type}/{uuid}/assign` — `{ "user_uuid": "..." }`. `{type}` is `service-requests` or `tickets`. Permission differs by type: `service_requests.assign` / `tickets.assign`.
- `PATCH /api/operations/queue/{type}/{uuid}/status` — `{ "status": "..." }`, validated against that item's own status enum. Permission: `service_requests.update` / `tickets.respond` (ticket status changes reuse the chat-reply permission — resolving a ticket is a form of responding to it).
- `GET /api/dashboard/summary` — `{ service_requests?: {status: count}, tickets?: {status: count}, event_inquiries?: {status: count} }`. Each block appears only if you hold the matching `.view` permission (`tickets.view` unlocks both `tickets` and `event_inquiries` — event inquiries reuse the same permission P6 already gated their own admin routes with). No permissions → `{}`, not a 403.

**Tickets are chatbot-only for now.** Nothing creates a `Ticket` until P11's `CreateTicketAction` — the table and queue support them from P10 onward so nothing needs to change when P11 lands.

---

## Error codes quick reference

| Code | HTTP | Meaning |
|---|---|---|
| `credentials_invalid` | 401 | Wrong email or password at login |
| `account_inactive` | 403 | Account deactivated |
| `unauthorized` | 401 | Token missing, expired, or wrong guard |
| `forbidden` | 403 | Missing permission, escalation attempt, or super_admin protection |
| `not_found` | 404 | Resource not found (or, for reservation ownership checks, deliberately masking "not yours") |
| `validation_failed` | 422 | Form validation failed |
| `too_many_requests` | 429 | Rate limited |
| `server_error` | 500 | Unexpected error — show generic message, log `request_id` |
| `no_availability` | 409 | Last room raced away during booking |
| `room_already_assigned` | 409 | Room already assigned to another reservation for overlapping dates |
| `invalid_promo` | 422 | Promo code invalid/expired |
| `reservation_state` | 422 | Action not valid for the reservation's/folio's current state |
| `hold_expired` | 422 | Soft-hold window passed before OTP verification |
| `payment_failed` | 422 | Payment gateway rejected the charge |
| `inquiry_state` | 422 | Invalid event-inquiry status transition |
| `no_active_reservation` | 403 | Guest-side entitlement gate — not relevant to dashboard requests, but appears in any guest-facing payload you might inspect while debugging |

---

## Coming in later phases

- **P11** — AI chatbot creates the first `Ticket` rows (source=chatbot); nothing new for the dashboard to integrate beyond what P10 already built
- **P12** — Reports (occupancy, revenue, reservations-by-source, request volume, ticket resolution — `reports.view`), guest directory (search + profile + history), hardening pass
