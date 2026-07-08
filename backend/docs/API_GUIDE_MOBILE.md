# Carlton Hotel — Guest Mobile API Guide

This guide covers every API endpoint the **guest mobile app (Flutter)** needs for the current build (P0–P2). It is written for frontend developers — no backend internals, no server-side class names, no file paths.

---

## Base URL

```
https://<host>/api
```

Replace `<host>` with the environment URL provided by the backend team (staging vs. production). All paths below are relative to this base.

---

## Standard Headers

Send these on every request:

| Header | Value | Notes |
|--------|-------|-------|
| `Content-Type` | `application/json` | Required on all POST requests with a body. |
| `Accept` | `application/json` | Ensures error responses are JSON, not HTML. |
| `Accept-Language` | `en` or `ar` | Controls the language of all `message` strings in responses. |
| `Authorization` | `Bearer <token>` | Required on all authenticated endpoints. Omit on public endpoints. |

---

## Token Storage

After a successful OTP verification, you receive a bearer token. Store it using **Flutter Secure Storage** (or equivalent encrypted storage). Never store tokens in SharedPreferences or plain local storage. Send the token as `Authorization: Bearer <token>` on every request that requires authentication.

---

## Standard Response Envelope

Every response from this API uses the same envelope structure. Parse the outer envelope first, then extract `data`.

**Success**

```json
{
  "success": true,
  "message": "...",
  "data": { ... },
  "request_id": "uuid"
}
```

**Paginated success** — `data` becomes:

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

**Error**

```json
{
  "success": false,
  "message": "...",
  "error_code": "snake_case_stable_code",
  "context": { ... },
  "request_id": "uuid"
}
```

**Validation error** — same shape as error, plus:

```json
{
  "errors": {
    "field_name": ["Validation message."]
  }
}
```

**Important:** Always log the `request_id` from every response. It is the primary identifier for support debugging — include it in any bug report or support ticket.

---

## Module: System

### GET /api/health

**Purpose:** Liveness probe. Call this on app launch to verify connectivity to the backend.

**Auth:** None — public endpoint.

**Request:** No body, no special headers required.

**Response `data`:**

```json
{
  "status": "ok",
  "time": "2026-07-07T10:00:00Z"
}
```

`time` is an ISO 8601 UTC timestamp. There are no meaningful failure states for this endpoint — a failed HTTP response (timeout, 5xx) means the server is unreachable.

---

## Module: Auth — Guest

The guest auth module is the entry point for all guest users. There are two paths to obtain a token:

**Path A — New or returning guest:**
1. `POST /api/auth/guest/request-otp` — send a code to the guest's phone or email.
2. `POST /api/auth/guest/verify-otp` with `purpose=login` or `purpose=register` — verify the code and receive a token.

**Path B — Guest with an existing hotel reservation:**
1. `POST /api/auth/guest/link-booking-code` — verify the reservation using the booking code + a second factor. The guest receives an OTP to their reservation contact.
2. `POST /api/auth/guest/verify-otp` with `purpose=booking_link` — verify the OTP and receive a token.

Once the guest has a token, send it as `Authorization: Bearer <token>` on all subsequent requests.

---

### POST /api/auth/guest/request-otp

**Purpose:** Send a one-time code to the guest's phone or email. The first step in registering or logging in.

**Auth:** None — public endpoint.

**When to call:** When the guest taps "Login / Sign Up", chooses a channel, and enters their contact details.

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `channel` | string | Yes | `sms`, `whatsapp`, or `email` |
| `phone` | string | Conditional | Required when `channel` is `sms` or `whatsapp`. Local Syrian format accepted (e.g. `0912345678`) — the server stores it as E.164 (`+963912345678`) automatically. |
| `email` | string | Conditional | Required when `channel` is `email`. |

**Example request:**

```json
{
  "channel": "sms",
  "phone": "0912345678"
}
```

**Response `data`:**

```json
{
  "message": "OTP sent"
}
```

The code does not appear in the response — it is delivered via the chosen channel.

**Failure outcomes:**

| `error_code` | HTTP | When | Suggested UI action |
|--------------|------|------|---------------------|
| `validation_failed` | 422 | Missing or invalid `channel`, or invalid field format. Check `errors` object for per-field detail. | Show inline field errors. |
| `identity_required` | 422 | `phone` or `email` missing for the chosen channel. | Prompt the guest to enter the required contact. |
| `too_many_requests` | 429 | Rate limit hit: 1 request per minute per identifier, 5 per hour per identifier. | Show "Please wait before requesting another code." Surface the retry-after hint from `context` if present. |

**State notes:** Remember `channel` and the identifier (phone in E.164 format, or email) in local state — both are required for the next call.

---

### POST /api/auth/guest/verify-otp

**Purpose:** Verify the OTP the guest received. Returns a bearer token and guest profile on success.

**Auth:** None — public endpoint.

**When to call:** After `request-otp` succeeds, when the guest enters the 6-digit code.

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `identifier` | string | Yes | Phone in E.164 format (e.g. `+963912345678`) or email — same value used in `request-otp`. |
| `channel` | string | Yes | Same value used in `request-otp`. |
| `code` | string | Yes | The 6-digit code the guest received. |
| `purpose` | string | Yes | `login`, `register`, or `booking_link` (use `booking_link` only when coming from `link-booking-code`). |

**Example request:**

```json
{
  "identifier": "+963912345678",
  "channel": "sms",
  "code": "482910",
  "purpose": "login"
}
```

**Response `data` on success:**

```json
{
  "token": "1|abcdef...",
  "guest": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "phone": "+963912345678",
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

`first_name` and `last_name` are `null` for new guests until they complete their profile (available in P12).

**Failure outcomes:**

| `error_code` | HTTP | When | Suggested UI action |
|--------------|------|------|---------------------|
| `validation_failed` | 422 | Missing or invalid fields. | Show inline errors from the `errors` object. |
| `otp_expired` | 422 | Code is older than 5 minutes. | Show "Code expired — tap Resend." Clear the code input field. |
| `otp_invalid` | 422 | Wrong code entered. The `context` object may include a remaining-attempts hint. | Show remaining attempts if provided. Allow retry. |
| `otp_locked` | 429 | 5 wrong attempts exhausted. | Show "Too many incorrect attempts. Please request a new code." Navigate back to the request-otp screen. |

**State notes:**
- Store the returned `token` in Flutter Secure Storage immediately. Use it as `Authorization: Bearer <token>` on all authenticated requests.
- Use `guest.uuid` as the stable identifier for all future guest-specific API paths. Never use numeric IDs.
- A returning guest (same phone or email as a previous session) receives their existing record — no duplicate is created.
- Mirror `guest.preferred_locale` in the app's locale setting on first login.

---

### POST /api/auth/guest/link-booking-code

**Purpose:** Let a guest who has a hotel reservation (booked via the website or an OTA) connect it to their mobile account. Requires the booking code printed on their confirmation **and** a second factor (last name or phone on the reservation) — the code alone is not sufficient.

**Auth:** None — public endpoint.

**When to call:** When the guest taps "I have a reservation", enters their booking code and last name or phone.

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `booking_code` | string | Yes | Format `CARL-XXXXXX` — printed on the reservation confirmation. |
| `last_name` | string | Conditional | At least one of `last_name` or `phone` is required. |
| `phone` | string | Conditional | At least one of `last_name` or `phone` is required. |

**Example request:**

```json
{
  "booking_code": "CARL-A1B2C3",
  "last_name": "Al-Hassan"
}
```

**Response `data` on success:**

```json
{
  "message": "OTP sent to reservation contact",
  "masked_contact": "**@ex***.com"
}
```

Show the `masked_contact` value to the guest so they know where to look for the OTP.

**Failure outcomes:**

| `error_code` | HTTP | When | Suggested UI action |
|--------------|------|------|---------------------|
| `validation_failed` | 422 | Both `last_name` and `phone` are missing, or `booking_code` has an invalid format. | Show inline field errors. |
| `booking_link_failed` | 404 | The code + second factor did not match any reservation. | Show a generic "Reservation not found" message. Do not reveal whether the booking code was valid on its own. |

**State notes:**
- After this call succeeds, navigate to the OTP entry screen.
- Submit the OTP using `POST /api/auth/guest/verify-otp` with `purpose = booking_link`.
- **Current build (pre-P4):** Submitting `verify-otp` with `purpose=booking_link` will return `error_code: booking_link_unavailable` (503). The OTP request and second-factor check both work; only the final reservation-link step is deferred until P4. Do not block your app release on this flow.

---

## Guest Profile Shape

The `guest` object returned from `verify-otp` is the guest's identity record. Key fields:

| Field | Type | Notes |
|-------|------|-------|
| `uuid` | string | Stable guest identifier. Use this in all guest-specific API paths. Never expose or use integer IDs. |
| `phone` | string or null | Stored in E.164 international format (e.g. `+963912345678`). |
| `phone_country` | string or null | ISO 3166-1 alpha-2 country code (e.g. `SY`). |
| `phone_verified` | boolean | `true` if the phone has been verified via OTP. |
| `email` | string or null | `null` if the guest registered via phone. |
| `email_verified` | boolean | `true` if the email has been verified via OTP. |
| `first_name` | string or null | `null` for new guests until profile is completed (P12). |
| `last_name` | string or null | `null` for new guests until profile is completed (P12). |
| `preferred_locale` | string | `en` or `ar`. Mirror this in the app's locale setting on first login. |

---

## Coming in Later Phases

| Phase | Features |
|-------|----------|
| P4 | Room booking, availability check, pricing quote, reservation management, `has_active_reservation` flag on the guest profile. |
| P7 | In-room service requests, pre-arrival preferences, service pre-bookings. All require `has_active_reservation = true`. |
| P8 | Folio review, express checkout. |
| P9 | Push notifications, chat with hotel staff. |
| P11 | AI chatbot. |
| P12 | Guest profile editing (first name, last name, preferred locale). |
