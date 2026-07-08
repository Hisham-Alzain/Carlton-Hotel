# Carlton Hotel — API Guide: Public Website

> **Audience:** frontend developers building the public website.
> **Surface:** The website is **anonymous-only**. It has no login, no token, no session, and no "my reservations." A visitor is always anonymous. The website's job is browse + book — that's its terminal action.
> **Key rule:** OTP on the website is **transaction verification** (confirming a booking), NOT a login mechanism. The verify step issues a guest token under the hood, but the website **discards it** — there is no logged-in state on the website. Guests use the mobile app to manage their stay.

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
| `Accept-Language` | Always | `en` or `ar` — all `message` strings honor this |
| `Content-Type` | Requests with a body | `application/json` |

No `Authorization` header is needed or used by the website.

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

**Validation error** — same as error but with an `errors` object keyed by field name:
```json
{
  "success": false,
  "error_code": "validation_failed",
  "errors": { "field": ["message"] },
  "request_id": "uuid"
}
```

Always log or surface `request_id` in error states — it identifies the exact request in backend logs.

---

## Module: System

### GET /api/health

**Purpose:** Liveness probe. Use on page load to verify API connectivity.

**Who can call:** Public — no headers required.

**Request:** No body, no headers required.

**Response `data`:**
```json
{ "status": "ok", "time": "2026-07-08T14:00:00Z" }
```
`time` is UTC ISO 8601.

**Failure:** If this endpoint fails, the API server is down. No `error_code` to handle — show a generic "service unavailable" state.

---

## Coming in P3 — Content (rooms, facilities, dining, event spaces, pages, promotions)

Public content endpoints added in P3 will be documented here. All are tier-1 (no token). They power the website's homepage, rooms page, dining section, event pages, etc. Each supports `Accept-Language` for bilingual output.

---

## Coming in P4 — Availability, price quote, and public booking flow

The public booking flow uses OTP **as transaction verification**, not login.

### Flow overview (to be documented in full at P4)

```
1. Guest fills booking form (dates, room type, name, phone/email)
   → POST /reservations/guest
   → Server creates a soft-hold + sends an OTP to the entered contact
   → Response: { reference: "...", expires_in: 300 }

2. Guest enters the OTP they received
   → POST /reservations/guest/verify   { reference, otp_code }
   → Server confirms the booking, returns a confirmation + booking_code
   → The token in this response is DISCARDED by the website — there is no logged-in state
   → Show: "Booking confirmed. Your code is CARL-XXXXXX. Download the app to manage your stay."
```

OTP here is proof that the guest controls the contact they entered — it is not a login. The website shows a confirmation screen and the guest's booking code. To manage the stay (check in, request services, view folio), the guest uses the mobile app and links their reservation with the booking code.

Availability check and price quote endpoints (no-auth GET requests) will also be documented here.

---

## Coming in P6 — Event & conference inquiry (RFP)

`POST /event-inquiries` — public, anonymous. Documented at P6.

---

## Coming in P11 — Public chatbot

`POST /chatbot/message` — anonymous, knowledge-and-triage. Documented at P11.

---

## What the website does NOT use

The following endpoints are **app-only** or **dashboard-only** and must not appear in website code:

- `POST /auth/guest/request-otp` and `/verify-otp` used as a login (session) flow — the website only uses OTP in the booking-verification context (P4 storeAsGuest step)
- `POST /auth/guest/link-booking-code` — app-only (§3.3 door 3; links a reservation to an app account)
- `GET /auth/guest/me`, `POST /auth/logout` — session endpoints; the website has no session
- All tier-2 and tier-3 endpoints (my-reservations, profile, in-room services, folio, checkout) — app-only
- All staff/dashboard endpoints (`/auth/login`, `/staff/*`, `/permissions`, `/roles`)
