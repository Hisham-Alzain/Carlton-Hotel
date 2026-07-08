# Carlton Hotel — API Guide: Guest Mobile App

> **Audience:** Flutter developers building the guest mobile app.
> **Surface:** The app is for guests running their stay. It has real auth sessions (a stored token), and exposes everything the website lacks: login, my-reservations, profile, and all tier-3 in-stay services. The app shares public endpoints (content, booking) with the website but, unlike the website, **keeps the token**.
> **Token storage:** Flutter Secure Storage. Never SharedPreferences or plain local storage.

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
| `Accept-Language` | Always | `en` or `ar` — controls all `message` strings |
| `Content-Type` | Requests with body | `application/json` |
| `Authorization` | Authenticated requests | `Bearer <guest-token>` |

Mirror `guest.preferred_locale` (returned at login) into the app locale setting on first login.

## Standard response envelope

Every response uses the same envelope. Parse the outer shape first, then extract `data`.

**Success:**
```json
{
  "success": true,
  "message": "Human-readable string (locale-aware).",
  "data": { "...fields..." },
  "request_id": "uuid"
}
```

**Paginated success** — `data` becomes:
```json
{
  "items": [...],
  "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 56 }
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

**Validation error** — same as error plus `errors` keyed by field:
```json
{
  "success": false,
  "error_code": "validation_failed",
  "errors": { "field_name": ["message"] },
  "request_id": "uuid"
}
```

Always log `request_id` — include it in bug reports and support tickets.

## Access tiers

| Tier | Requirement | What it unlocks |
|---|---|---|
| **Public** | No token | Content, availability, price quote, chatbot, event inquiry, public booking endpoints |
| **Authenticated guest** | Guest token (`Authorization: Bearer`) | Profile, my-reservations, cancel, booking (authenticated path), chat, tickets |
| **Active-reservation guest** | Guest token + confirmed/checked-in stay covering today | Tier-3: in-room services, pre-arrival, room-service orders, folio, express checkout |

`GET /auth/me` (guest) returns `has_active_reservation: bool` so the app knows which mode to render. The server enforces the gate — hiding UI is convenience only.

---

## Module: System

### GET /api/health

**Purpose:** Liveness probe. Call on app launch to verify connectivity.

**Who can call:** Public (tier-1).

**Response `data`:** `{ "status": "ok", "time": "2026-07-08T14:00:00Z" }`

No failure states to handle — a failed response means the server is unreachable.

---

## Module: Guest Auth

Three entry paths to a guest token. All end at the same place: a verified `guests` record and a bearer token.

**Path A — New / returning guest:**
```
1. POST /auth/guest/request-otp   { channel, phone | email, purpose }
2. POST /auth/guest/verify-otp    { phone | email, channel, code, purpose }
   → { token, guest }  ←  KEEP the token
```

**Path B — Guest with existing hotel reservation:**
```
1. POST /auth/guest/link-booking-code   { booking_code, last_name | phone }
   → OTP sent to reservation contact
2. POST /auth/guest/verify-otp   { phone | email, channel, code, purpose: "booking_link" }
   → { token, guest }  ← KEEP the token
   ⚠ pre-P4: returns booking_link_unavailable (422); full linking ships with P4
```

---

### POST /api/auth/guest/request-otp

**Purpose:** Send a one-time code to the guest's phone or email.

**Who can call:** Public (tier-1).

**When:** Tap "Login / Sign Up" → choose channel → enter contact → call this.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `channel` | string | ✅ | `sms`, `whatsapp`, or `email` |
| `phone` | string | when channel = sms/whatsapp | Local format OK (`0912345678`); normalized to E.164 (`+963912345678`) server-side |
| `email` | string | when channel = email | |
| `purpose` | string | ✅ | `login` or `register` |

**Response `data`:**
```json
{ "identifier": "+963912345678", "channel": "sms", "expires_in": 300 }
```
No code in the response — delivered via the chosen channel. `expires_in` is seconds.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `identity_required` | 422 | "Please enter your phone or email." |
| `too_many_requests` | 429 | "Please wait before requesting another code." Disable resend for 60 s. |
| `validation_failed` | 422 | Show field errors. |

**State to track:** Remember `channel` and the identifier (E.164 phone or email) — both required for the next call.

---

### POST /api/auth/guest/verify-otp

**Purpose:** Verify the OTP. Returns a guest token on success.

**Who can call:** Public (tier-1).

**When:** After `request-otp` succeeds → guest enters the 6-digit code → call this.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `phone` | string | one of phone/email | E.164 format (`+963912345678`) |
| `email` | string | one of phone/email | |
| `code` | string | ✅ | 6-digit code received by the guest |
| `purpose` | string | ✅ | `login`, `register`, or `booking_link` |

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

`first_name`/`last_name` are null until the guest fills their profile (P12). A returning guest gets their existing record — no duplicate created.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `otp_expired` | 422 | "Code expired — tap Resend." Clear input. |
| `otp_invalid` | 422 | "Incorrect code." Allow retry (up to 5 attempts total). |
| `otp_locked` | 429 | "Too many attempts. Request a new code." Redirect to step 1. |
| `booking_link_unavailable` | 422 | (pre-P4) "This feature is coming soon." |
| `validation_failed` | 422 | Show field errors. |

**State notes:**
- Store `token` in Flutter Secure Storage. Use as `Authorization: Bearer <token>` on all tier-2/3 requests.
- `guest.uuid` is the stable guest identifier — never use integer IDs.
- Mirror `guest.preferred_locale` into the app locale on first login.

---

### POST /api/auth/guest/link-booking-code

**Purpose:** Connect a hotel reservation (booked on website or OTA) to this app account. Requires the booking code **and** a second factor (last name or phone on the reservation) — the code alone is never sufficient.

**Who can call:** Public (tier-1).

**When:** Guest taps "I already have a reservation" → enters booking code + last name or phone.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `booking_code` | string | ✅ | Format `CARL-XXXXXX` (printed on confirmation) |
| `last_name` | string | one of last_name/phone | |
| `phone` | string | one of last_name/phone | |

**Response `data` on success:**
```json
{
  "message": "OTP sent to reservation contact",
  "masked_contact": "**@ex***.com"
}
```
Show `masked_contact` so the guest knows where to look.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `booking_link_failed` | 404 | Generic "Reservation not found." Do NOT reveal whether the code alone was valid. |
| `validation_failed` | 422 | Missing second factor, invalid code format. |

**State notes:** Navigate to OTP entry. Submit with `purpose=booking_link`. Pre-P4, the verify step returns `booking_link_unavailable` — do not gate app release on this flow.

---

## Guest profile fields

| Field | Notes |
|---|---|
| `uuid` | Stable identifier. Use in all guest-specific paths. Never use integer IDs. |
| `phone` | E.164 format (`+963...`). |
| `phone_country` | ISO 3166-1 alpha-2 (e.g. `SY`). |
| `phone_verified` / `email_verified` | `false` = contact not yet OTP-verified. |
| `preferred_locale` | `en` or `ar`. Mirror into app locale on first login. |
| `first_name` / `last_name` | Null until profile filled (P12). |

---

## Coming in P3 — Public content (tier-1)

Rooms, facilities, dining, event spaces, promotions, CMS pages. Bilingual (`Accept-Language`). No token required; token if present must not break the response.

---

## Coming in P4 — Booking & reservations (tier-1 + tier-2)

- Availability check + price quote (public, tier-1)
- `POST /reservations` — **app-only, tier-2.** Identity from token; one step; ignores body contact fields.
- `POST /reservations/guest` + `/reservations/guest/verify` — public two-step flow (app keeps the returned token; website discards it).
- My reservations: `GET /reservations`, `GET /reservations/{uuid}`, cancel (tier-2)
- `GET /auth/me` (guest) gains `has_active_reservation: bool` + active reservation summary
- Full booking-code → guest linking (P4.R — un-skips the pre-P4 `booking_link_unavailable` error)

---

## Coming in P7 — In-stay services (tier-3, requires `has_active_reservation`)

Service requests, room-service ordering, pre-arrival documents & check-in approval, service pre-bookings (spa, restaurant, pool cabana, transfer). All rejected with `no_active_reservation` if no live stay.

---

## Coming in P8 — Folio & express checkout (tier-3)

Folio review and settlement, transport request.

---

## Coming in P9 — Push notifications & staff chat

Device token registration, guest↔staff real-time chat.

---

## Coming in P11 — AI chatbot

`POST /chatbot/message` — public (tier-1) or authenticated.

---

## Coming in P12 — Profile editing

Update `first_name`, `last_name`, `preferred_locale`; change password; device management.
