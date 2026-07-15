# Carlton Hotel — API Guide: Staff Dashboard

> **Audience:** frontend developers building the staff/admin dashboard.
> **Surface:** Staff-only, permission-gated. All requests require a staff bearer token obtained via `POST /auth/login`. Permissions control which sections are accessible — read the `permissions` array from the login response to decide what to render.

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
| `Accept-Language` | Always | `en` or `ar` |
| `Content-Type` | Requests with a body | `application/json` |
| `Authorization` | All authenticated requests | `Bearer <staff-token>` |

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
  {
    "name": "reception",
    "permissions": ["reservations.view", "reservations.create", "folios.view", "folios.settle"]
  }
]
```

5 presets: `reception`, `kitchen`, `housekeeping`, `concierge`, `events`.

---

## Error codes quick reference (P0–P2)

| Code | HTTP | Meaning |
|---|---|---|
| `credentials_invalid` | 401 | Wrong email or password at login |
| `account_inactive` | 403 | Account deactivated |
| `unauthorized` | 401 | Token missing, expired, or wrong guard |
| `forbidden` | 403 | Missing permission, escalation attempt, or super_admin protection |
| `not_found` | 404 | Resource not found |
| `validation_failed` | 422 | Form validation failed |
| `too_many_requests` | 429 | Rate limited |
| `server_error` | 500 | Unexpected error — show generic message, log `request_id` |

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

The unified read+assign layer over `service_requests` and `tickets` (chatbot-created — empty until P11 ships). Every mutation mirrors live to the same Firestore `ops_queue` collection service-request creation already writes to (see `API_GUIDE_MOBILE.md`).

- `GET /api/operations/queue` — merged, newest-first, paginated. Requires `service_requests.view` **or** `tickets.view`; each table is included only if the caller holds its own `.view` permission (holding just one silently omits the other, not a 403). Each item: `{ type: "service_request"|"ticket", uuid, subject, department, status, priority, assigned_user_uuid, created_at }`. `subject` is the service request's `type` or the ticket's `subject`.
- `PATCH /api/operations/queue/{type}/{uuid}/assign` — `{ "user_uuid": "..." }`. `{type}` is `service-requests` or `tickets`. Permission differs by type: `service_requests.assign` / `tickets.assign`.
- `PATCH /api/operations/queue/{type}/{uuid}/status` — `{ "status": "..." }`, validated against that item's own status enum. Permission: `service_requests.update` / `tickets.respond` (ticket status changes reuse the chat-reply permission — resolving a ticket is a form of responding to it).
- `GET /api/dashboard/summary` — `{ service_requests?: {status: count}, tickets?: {status: count}, event_inquiries?: {status: count} }`. Each block appears only if you hold the matching `.view` permission (`tickets.view` unlocks both `tickets` and `event_inquiries` — event inquiries reuse the same permission P6 already gated their own admin routes with). No permissions → `{}`, not a 403.

**Tickets are chatbot-only for now.** Nothing creates a `Ticket` until P11's `CreateTicketAction` — the table and queue support them from P10 onward so nothing needs to change when P11 lands.

---

## Coming in later phases

- **P3** — CMS CRUD (admin create/edit rooms, facilities, dining, etc. — `cms.edit` gated)
- **P4** — Reservations list/show, room assignment at check-in, booking-code linking management
- **P5** — Cash payment settlement (`folios.settle`)
- **P6** — Event inquiry triage, assignment, status
- **P7** — Service request queue, menu catalog management, pre-arrival approvals
- **P8** — Folio generation and settlement
- **P11** — AI chatbot (creates the first `Ticket` rows; RAG over CMS content)
