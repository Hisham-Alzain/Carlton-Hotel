# Carlton Hotel — API Guide: Public Website

> **Audience:** frontend developers building the public website.
> **Surface:** The website is **anonymous-only**. It has no login, no token, no session, and no "my reservations." A visitor is always anonymous. The website's job is browse + book — that's its terminal action.
> **Key rule:** OTP on the website is **transaction verification** (confirming a booking), NOT a login mechanism. The verify step issues a guest token under the hood, but the website **discards it** — there is no logged-in state on the website. Guests use the mobile app to manage their stay.
> **Try it now:** `php artisan migrate:fresh --seed` populates realistic demo data, and `docs/postman/` has a ready-to-import Postman collection + environment. See `docs/postman/README.md`.

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
| `Accept-Language` | Always | `en` or `ar` — controls only the human-readable `message`/error/validation strings |
| `Content-Type` | Requests with a body | `application/json` |

No `Authorization` header is needed or used by the website.

**`Accept-Language` does NOT localize content fields.** Every bilingual content field (room type names, page bodies, promotion terms, etc.) is always returned as a `{ "en": "...", "ar": "..." }` object, regardless of `Accept-Language` — the header only picks which language the envelope's `message` string and validation error text are written in. The frontend is responsible for picking `field.en` or `field.ar` itself.

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

**Response `data`:**
```json
{ "status": "ok", "time": "2026-07-08T14:00:00Z" }
```
`time` is UTC ISO 8601.

**Failure:** If this endpoint fails, the API server is down. No `error_code` to handle — show a generic "service unavailable" state.

---

## Module: Content

All public, no token. Every list/show endpoint filters `is_active = true` server-side (inactive records 404, never leak). All 7 types are image-galleried via an `images` array except `Page`. Route key is `uuid` for everything except `Page`, which is addressed by `slug`.

| Type | List | Show |
|---|---|---|
| Room types | `GET /public/room-types` | `GET /public/room-types/{uuid}` |
| Rooms | `GET /public/rooms` | `GET /public/rooms/{uuid}` |
| Facilities | `GET /public/facilities` | `GET /public/facilities/{uuid}` |
| Dining venues | `GET /public/dining-venues` | `GET /public/dining-venues/{uuid}` |
| Event spaces | `GET /public/event-spaces` | `GET /public/event-spaces/{uuid}` |
| Pages | — | `GET /public/pages/{slug}` |
| Promotions | `GET /public/promotions` | `GET /public/promotions/{uuid}` |

List endpoints are paginated (`data.items` + `data.meta`, 15/page); `show` returns a single object in `data`.

### Room type
```json
{
  "uuid": "...", "name": {"en":"Deluxe King","ar":"..."}, "description": {"en":"...","ar":"..."},
  "amenities": ["wifi", "minibar"], "base_occupancy": 2, "max_occupancy": 4,
  "size_sqm": "35.00", "base_price_usd": "150.00", "is_active": true, "sort_order": 0,
  "images": [ { "uuid":"...", "url":"https://...", "file_name":"...", "sort_order":0 } ]
}
```
`amenities` here is a plain JSON array of strings (not translatable).

### Room
```json
{
  "uuid": "...", "number": "101", "floor": 1, "status": "available", "is_active": true,
  "room_type": { "...room type shape above..." }, "images": [ ... ]
}
```

### Facility
```json
{
  "uuid": "...", "name": {"en":"...","ar":"..."}, "description": {"en":"...","ar":"..."},
  "location": {"en":"...","ar":"..."}, "hours": {"en":"...","ar":"..."},
  "is_active": true, "sort_order": 0, "images": [ ... ]
}
```

### Dining venue
```json
{
  "uuid": "...", "name": {"en":"...","ar":"..."}, "description": {"en":"...","ar":"..."},
  "cuisine_type": {"en":"...","ar":"..."}, "location": {"en":"...","ar":"..."}, "hours": {"en":"...","ar":"..."},
  "is_active": true, "sort_order": 0, "images": [ ... ]
}
```

### Event space
```json
{
  "uuid": "...", "name": {"en":"...","ar":"..."}, "description": {"en":"...","ar":"..."},
  "capacity": 100, "location": {"en":"...","ar":"..."}, "amenities": {"en":"...","ar":"..."},
  "is_active": true, "sort_order": 0, "images": [ ... ]
}
```
Note: `amenities` here is a **translatable free-text string**, not the array it is on room types — don't share a parsing path between the two.

### Page
```json
{ "uuid": "...", "slug": "about-us", "title": {"en":"...","ar":"..."}, "content": {"en":"...","ar":"..."}, "is_active": true, "sort_order": 0 }
```
No `images` field. `GET /public/pages/{slug}` 404s (`not_found`) if the slug doesn't exist or the page is inactive — there is no `GET /public/pages` list endpoint.

### Promotion
```json
{
  "uuid": "...", "title": {"en":"...","ar":"..."}, "description": {"en":"...","ar":"..."}, "terms": {"en":"...","ar":"..."},
  "valid_from": "2026-07-01", "valid_until": "2026-08-31", "is_active": true, "sort_order": 0, "images": [ ... ]
}
```
`valid_from`/`valid_until` are `YYYY-MM-DD` or `null`.

---

## Module: Availability, Quote & Booking

### GET /public/availability

**Purpose:** Check room-type availability for a date range.

**Request query params:** `room_type_uuid` (required, must exist), `check_in` (required, date, today or later), `check_out` (required, date, after `check_in`).

**Response `data`:**
```json
{ "room_type_uuid": "...", "check_in": "2026-07-20", "check_out": "2026-07-22", "available": true, "rooms_available": 3 }
```

**Failure `error_code`s:** `not_found` (404, unknown/inactive room type), `validation_failed` (422).

---

### GET /public/quote

**Purpose:** Price a stay before booking (base rate → seasonal/weekend rules → promo).

**Request query params:** `room_type_uuid` (required), `check_in`/`check_out` (required, same rules as availability), `promo_code` (optional).

**Response `data`:**
```json
{ "daily_rate_usd": 150.0, "nights": 2, "subtotal_usd": 300.0, "discount_usd": 30.0, "total_usd": 270.0, "promo_code_id": 5, "rules_applied": 1 }
```

**Failure `error_code`s:** `invalid_promo` (422, promo missing/expired/inactive), `not_found` (404), `validation_failed` (422).

---

### The public booking flow (two steps, OTP as verification not login)

```
1. Guest fills the booking form (dates, room type, name, contact)
   → POST /reservations/guest
   → Server soft-holds the room for the OTP window and sends a code
   → Response: { reservation_uuid, identifier_masked, channel }

2. Guest enters the code they received
   → POST /reservations/guest/verify   { reservation_uuid, phone|email, otp_code }
   → Server activates the booking, returns the confirmation + a token
   → The token is DISCARDED by the website — there is no logged-in state
   → Show: "Booking confirmed. Your code is CARL-XXXXXXXX. Download the app to manage your stay."
```

### POST /reservations/guest

**Purpose:** Step 1 — submit booking details, get an OTP sent to the guest's contact.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `room_type_uuid` | string | ✅ | Must exist |
| `check_in` | date | ✅ | Today or later |
| `check_out` | date | ✅ | After `check_in` |
| `first_name` | string | ✅ | Max 100 |
| `last_name` | string | ✅ | Max 100 |
| `phone` | string | one of phone/email | Normalized to E.164 server-side |
| `email` | string | one of phone/email | Lowercased/trimmed |
| `payment_method` | string | optional | `cash` or `on_arrival` |
| `promo_code` | string | optional | |

**Response `data`:**
```json
{ "reservation_uuid": "...", "identifier_masked": "+963****", "channel": "sms" }
```
`channel` is `sms` or `email`. The OTP TTL is a fixed 5 minutes server-side (not echoed in the response) — the room stays soft-held for exactly that long, then auto-releases if step 2 never completes.

**Failure `error_code`s:** `no_availability` (409, last room raced away), `invalid_promo` (422), `too_many_requests` (429, OTP rate limit — 1/min, 5/hr per contact), `validation_failed` (422, includes `identity` key if neither phone nor email was given).

---

### POST /reservations/guest/verify

**Purpose:** Step 2 — verify the OTP, activate the booking.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `reservation_uuid` | string | ✅ | From step 1 |
| `phone` | string | one of phone/email | Must match the contact used in step 1 |
| `email` | string | one of phone/email | |
| `otp_code` | string | ✅ | 6 digits |

**Response `data` on success:**
```json
{
  "reservation": {
    "uuid": "...", "booking_code": "CARL-XXXXXXXX", "status": "pending",
    "check_in": "2026-07-20", "check_out": "2026-07-22", "nights": 2,
    "source": "direct", "payment_method": "cash", "total_usd": "270.00", "hold_expires_at": null
  },
  "guest": { "uuid": "...", "name": "...", "phone": "+963...", "email": null, "preferred_locale": "en" },
  "token": "1|abcdef..."
}
```
**Discard `token`.** Show the guest their `booking_code` and point them at the app to manage the stay.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `not_found` | 404 | Reservation not in the right state, or the contact doesn't match step 1 — generic "Booking not found." |
| `otp_invalid` | 422 | "Incorrect code." Allow retry. |
| `otp_expired` | 422 | "Code expired." The hold is gone too — send the guest back to step 1. |
| `otp_locked` | 429 | Too many attempts — back to step 1. |
| `hold_expired` | 422 | The 5-minute hold window passed — back to step 1, room may no longer be available. |

**Booking code format:** `CARL-` + 8 Crockford-Base32 characters (excludes `I`, `L`, `O`, `U` to avoid ambiguity), e.g. `CARL-7K2M9XQR`.

---

## Module: Event Inquiry (RFP)

### POST /event-inquiries

**Purpose:** Submit a wedding/conference/corporate-event inquiry. Public, anonymous — no confirmation flow, just routes to the right department.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | ✅ | Max 255 |
| `email` | string | ✅ | |
| `phone` | string | optional | Normalized to E.164 if valid |
| `company` | string | optional | |
| `event_type` | string | ✅ | `wedding`, `corporate`, `conference`, `gala`, `birthday`, `product_launch`, `other` |
| `event_date` | date | optional | Must be after today |
| `expected_guests` | integer | optional | Min 1 |
| `budget_usd` | number | optional | |
| `notes` | string | optional | Max 5000 |
| `requirements` | array | optional | `[{ "type": "av_equipment", "notes": "..." }]` |

**Response `data`** (HTTP 201):
```json
{
  "uuid": "...", "name": "...", "email": "...", "event_type": "corporate", "event_date": "2026-08-01",
  "status": "new", "department": "sales",
  "requirements": [ { "uuid": "...", "type": "av_equipment", "notes": "..." } ]
}
```

**Department routing:** `corporate`, `conference`, `product_launch` → `sales`; everything else (`wedding`, `gala`, `birthday`, `other`) → `events`.

**Failure `error_code`s:** `validation_failed` (422).

---

## Coming in P11 — Public chatbot

`POST /chatbot/message` — anonymous, knowledge-and-triage. Documented at P11.

---

## What the website does NOT use

The following endpoints are **app-only** or **dashboard-only** and must not appear in website code:

- `POST /auth/guest/request-otp` and `/verify-otp` used as a login (session) flow — the website only uses OTP in the booking-verification context above
- `POST /auth/guest/link-booking-code` — app-only (links a reservation to an app account)
- `GET /auth/guest/me`, `POST /auth/logout` — session endpoints; the website has no session
- All tier-2 and tier-3 endpoints (my-reservations, profile, device tokens, chat, in-room services, folio, checkout) — app-only
- All staff/dashboard endpoints (`/auth/login`, `/staff/*`, `/permissions`, `/roles`, `/cms/*` admin routes, `/operations/*`, `/dashboard/*`)
