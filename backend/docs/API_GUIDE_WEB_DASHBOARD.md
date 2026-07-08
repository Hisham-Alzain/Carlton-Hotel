# Carlton Hotel API — Web Dashboard & Public Website Guide

Frontend reference for staff/admin dashboard and public website developers. Covers endpoints delivered in phases P0–P2.

---

## Base URL & Standard Headers

All endpoints are prefixed with the API base URL:

```
https://<host>/api
```

**Required headers on every request:**

| Header | Value |
|---|---|
| `Content-Type` | `application/json` (on requests with a body) |
| `Accept` | `application/json` |
| `Accept-Language` | `en` or `ar` — all response messages honor this |
| `Authorization` | `Bearer <token>` — required on all protected endpoints |

Track the `request_id` returned in every response. Include it in any support or debugging reports.

---

## Standard Response Envelope

Every response from this API is wrapped in a consistent envelope. Do not document this per endpoint — it always applies.

### Success

```json
{
  "success": true,
  "message": "...",
  "data": { ... },
  "request_id": "uuid"
}
```

### Paginated Success

When a list endpoint returns pages, `data` becomes:

```json
{
  "items": [...],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

### Error

```json
{
  "success": false,
  "message": "...",
  "error_code": "snake_case_stable_code",
  "context": { ... },
  "request_id": "uuid"
}
```

### Validation Error

Same shape as an error response, with an additional `errors` object keyed by field name:

```json
{
  "success": false,
  "message": "Validation failed.",
  "error_code": "validation_failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  },
  "request_id": "uuid"
}
```

---

## Module: System

### GET /api/health

**Purpose:** Liveness probe. Use this to power a health indicator in the dashboard shell.

**Auth:** None — public endpoint.

**Request:** No body, no headers required.

**Response `data`:**

```json
{
  "status": "ok",
  "time": "2026-07-08T10:30:00Z"
}
```

`time` is an ISO 8601 UTC timestamp.

**Failure states:** None meaningful. If this endpoint fails, the server is unreachable.

---

## Module: Auth — Staff

### POST /api/auth/login

**Purpose:** Authenticate a staff member or admin. Returns a bearer token and the account's full permission list.

**Auth:** None — this is how a token is obtained.

**Request body:**

```json
{
  "email": "staff@carlton.com",
  "password": "your-password"
}
```

| Field | Type | Required |
|---|---|---|
| `email` | string | Yes |
| `password` | string | Yes |

**Response `data`:**

```json
{
  "token": "Bearer token string",
  "user": {
    "uuid": "...",
    "name": "...",
    "email": "...",
    "type": "staff | super_admin",
    "is_active": true,
    "roles": ["reception"],
    "permissions": ["reservations.view", "reservations.create", "folios.view"]
  }
}
```

**Failure outcomes:**

| `error_code` | HTTP | When to show |
|---|---|---|
| `credentials_invalid` | 401 | Show a generic "Invalid credentials" message. Do not distinguish wrong email from wrong password. |
| `account_inactive` | 403 | Show "Your account has been disabled. Contact an administrator." |
| `validation_failed` | 422 | Show field-level errors from the `errors` object. |

**Notes:**
- Store the token and send it as `Authorization: Bearer <token>` on all subsequent requests.
- The `permissions` array is the source of truth for which dashboard sections to show or hide. Build your route guards from this list.

---

### POST /api/auth/logout

**Purpose:** Invalidate the current token server-side. Call this on user sign-out.

**Auth:** Any authenticated staff account.

**Request:** No body. Send only the `Authorization` header.

**Response `data`:** `null`

**Notes:**
- Discard the stored token as soon as you receive a success response.
- A missing or expired token returns a 401 but is not a meaningful error in this context.

---

### GET /api/auth/me

**Purpose:** Refresh the current staff member's profile and permission list. Call this after a role or permission change to sync the dashboard without requiring re-login.

**Auth:** Any authenticated staff account.

**Request:** No body.

**Response `data`:** Same shape as the `user` object in `/api/auth/login`.

**Failure outcomes:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `unauthorized` | 401 | Token is missing or has expired. Redirect to login. |

---

## Module: Auth — Guest

These endpoints are shared between the web dashboard and the guest mobile app. They are documented here for completeness (e.g. if the dashboard needs to verify a guest or trigger OTP flows on their behalf).

### POST /api/auth/guest/request-otp

**Purpose:** Send a one-time code to a guest's phone or email. This is the first step of guest authentication.

**Auth:** None — public endpoint.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `channel` | string | Yes | `sms`, `whatsapp`, or `email` |
| `phone` | string | Conditional | Required when channel is `sms` or `whatsapp`. Local format accepted (e.g. `0912345678`); normalized to E.164 server-side. |
| `email` | string | Conditional | Required when channel is `email`. |

**Response `data`:**

```json
{
  "message": "OTP sent"
}
```

The OTP code is never returned in the response.

**Failure outcomes:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `identity_required` | 422 | Phone or email missing for the chosen channel. |
| `too_many_requests` | 429 | Rate limited: 1 request per minute per identifier; 5 per hour. Show "Please wait before requesting another code." |
| `validation_failed` | 422 | Missing or invalid `channel` value. |

---

### POST /api/auth/guest/verify-otp

**Purpose:** Submit the OTP code the guest received. On success, returns a guest session token.

**Auth:** None — public endpoint.

**When to call:** After `request-otp` succeeds and the guest has entered the code.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `identifier` | string | Yes | The phone (E.164) or email used in `request-otp`. |
| `channel` | string | Yes | Same channel used in `request-otp`. |
| `code` | string | Yes | The 6-digit code the guest received. |
| `purpose` | string | Yes | `login` or `register`. |

**Response `data`:**

```json
{
  "token": "Bearer token string",
  "guest": {
    "uuid": "...",
    "phone": "+9639XXXXXXXX",
    "phone_country": "SY",
    "phone_verified": true,
    "email": null,
    "email_verified": false,
    "first_name": null,
    "last_name": null,
    "preferred_locale": "en"
  }
}
```

**Failure outcomes:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `otp_expired` | 422 | Code is older than 5 minutes. Prompt the guest to request a new one. |
| `otp_invalid` | 422 | Wrong code. Up to 5 attempts are allowed before lockout. |
| `otp_locked` | 429 | 5 wrong attempts consumed. Show "Too many attempts. Please request a new code." |

---

### POST /api/auth/guest/link-booking-code

**Purpose:** Allow a guest with an existing reservation (booked directly or via OTA) to link it to their account. Requires the booking code plus a second factor (last name or phone on the reservation). On success, sends an OTP to the contact stored on the reservation.

**Auth:** None — public endpoint.

> Do not assume which contact method will be used for the OTP — the server selects it from the reservation record and returns a masked hint.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `booking_code` | string | Yes | Format: `CARL-XXXXXXXX` |
| `last_name` | string | Conditional | At least one of `last_name` or `phone` is required. |
| `phone` | string | Conditional | At least one of `last_name` or `phone` is required. |

**Response `data`:**

```json
{
  "message": "OTP sent to reservation contact",
  "masked_contact": "**@ex***.com"
}
```

**Failure outcomes:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `booking_link_failed` | 404 | The booking code and second factor did not match any reservation. Show a generic "Reservation not found" message — do not reveal whether the code itself was valid. |
| `validation_failed` | 422 | Second factor (last name or phone) was not provided. |

**Next step:** After this call, prompt the guest to enter the OTP they received and submit it via `POST /api/auth/guest/verify-otp` with `purpose: "booking_link"`.

> **P4 notice:** In the current build (P0–P2), submitting an OTP with `purpose=booking_link` via `verify-otp` returns `error_code: booking_link_unavailable` (503). Full reservation linking is available from P4 onward. Do not build UI that depends on this flow completing successfully until P4 ships.

---

## Module: Staff Management

**Required permission:** `staff.manage` on all endpoints in this section.
`super_admin` accounts bypass this check automatically.

**Auth header required:** `Authorization: Bearer <staff token>`

---

### Staff Object Shape

All staff endpoints return this object (or a paginated list of it):

```json
{
  "uuid": "...",
  "name": "...",
  "email": "...",
  "type": "staff | super_admin",
  "is_active": true,
  "roles": ["reception"],
  "effective_permissions": ["reservations.view", "reservations.create", "folios.view"],
  "direct_permissions": ["reservations.cancel"],
  "role_permissions": ["reservations.view", "reservations.create", "folios.view"]
}
```

**Permission fields explained:**

| Field | Meaning |
|---|---|
| `effective_permissions` | The ground truth of what the account can do. = role permissions + direct grants − direct revokes. Use this for all access checks. |
| `role_permissions` | Permissions inherited from the assigned role preset. |
| `direct_permissions` | Manual permission overrides added on top of the role. |

---

### GET /api/staff

**Purpose:** List all staff accounts.

**Response `data`:** Paginated list of staff objects.

**Failure:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `forbidden` | 403 | Caller does not have `staff.manage`. |

---

### POST /api/staff

**Purpose:** Create a new staff account from a role preset.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | Yes | |
| `email` | string | Yes | Must be unique across all staff accounts. |
| `password` | string | Yes | Minimum 8 characters. |
| `role` | string | Yes | One of the preset names returned by `GET /api/roles` (e.g. `reception`, `kitchen`, `housekeeping`, `concierge`, `events`). |

**Response `data`:** Created staff object (HTTP 201).

**Failure outcomes:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `forbidden` | 403 | Missing `staff.manage`. |
| `validation_failed` | 422 | Email already in use, role name not recognized, or password too short. |

---

### GET /api/staff/{uuid}

**Purpose:** Fetch a single staff member's full profile, including permission breakdown.

**Response `data`:** Staff object.

---

### PUT /api/staff/{uuid}

**Purpose:** Update a staff member's name and/or email. Does not change their role or permissions.

**Request body:**

| Field | Type | Required |
|---|---|---|
| `name` | string | Optional |
| `email` | string | Optional — must remain unique if changed. |

**Response `data`:** Updated staff object.

**Failure:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `forbidden` | 403 | Cannot edit a `super_admin` account unless the caller is also a `super_admin`. |
| `validation_failed` | 422 | Email already taken. |

---

### POST /api/staff/{uuid}/permissions

**Purpose:** Grant or revoke individual permissions on a staff account beyond what their role provides.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `grant` | array of strings | Optional | Permission names to add. |
| `revoke` | array of strings | Optional | Permission names to remove. |

At least one of `grant` or `revoke` must be non-empty. The two arrays must not overlap.

**Response `data`:** Updated staff object with the new `effective_permissions`.

**Failure outcomes:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `forbidden` | 403 | Escalation attempt (trying to grant a permission the caller doesn't hold themselves), or the target is a `super_admin`. |
| `validation_failed` | 422 | Arrays overlap, contain an unknown permission name, or both are empty. |

---

### PATCH /api/staff/{uuid}/deactivate

**Purpose:** Deactivate a staff account. All active tokens for that account are invalidated immediately.

**Request:** No body.

**Response `data`:** Updated staff object with `is_active: false`.

**Failure outcomes:**

| `error_code` | HTTP | Meaning |
|---|---|---|
| `forbidden` | 403 | Cannot deactivate your own account or a `super_admin` account. |

---

## Module: Reference Data

### GET /api/permissions

**Purpose:** Retrieve the full list of all permissions in the system, grouped by module. Use this to build the permission assignment UI (e.g. checkbox lists grouped by module).

**Auth:** `staff.manage` required.

**Response `data`:**

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

**Available modules (8 total):** `reservations`, `folios`, `cms`, `service_requests`, `tickets`, `pricing`, `reports`, `staff`.

---

### GET /api/roles

**Purpose:** List all role presets and their included permissions. Use this to populate the role dropdown in the "Create Staff" form.

**Auth:** `staff.manage` required.

**Response `data`:**

```json
[
  {
    "name": "reception",
    "permissions": ["reservations.view", "reservations.create", "folios.view", "folios.settle"]
  }
]
```

**Available presets (5 total):** `reception`, `kitchen`, `housekeeping`, `concierge`, `events`.

---

## Quick Reference — Error Codes

| `error_code` | HTTP | Typical cause |
|---|---|---|
| `credentials_invalid` | 401 | Wrong email or password at login. |
| `account_inactive` | 403 | Staff account has been deactivated. |
| `unauthorized` | 401 | Token missing, invalid, or expired. |
| `forbidden` | 403 | Authenticated but lacking the required permission. |
| `validation_failed` | 422 | Request body failed validation. Check `errors` object. |
| `identity_required` | 422 | Phone or email not provided for OTP channel. |
| `too_many_requests` | 429 | Rate limited — wait before retrying. |
| `otp_expired` | 422 | OTP code has passed its 5-minute window. |
| `otp_invalid` | 422 | Wrong OTP code. |
| `otp_locked` | 429 | Too many wrong OTP attempts. Request a new code. |
| `booking_link_failed` | 404 | Booking code + second factor did not match a reservation. |
| `booking_link_unavailable` | 503 | Booking linking not yet active (available from P4). |
