# Carlton Hotel — API Guide: Guest Mobile App

> **Audience:** Flutter developers building the guest mobile app.
> **Surface:** The app is for guests running their stay. It has real auth sessions (a stored token), and exposes everything the website lacks: login, my-reservations, profile, and all tier-3 in-stay services. The app shares public endpoints (content, booking) with the website but, unlike the website, **keeps the token**.
> **Token storage:** Flutter Secure Storage. Never SharedPreferences or plain local storage.
> **Try it now:** `php artisan migrate:fresh --seed` populates realistic demo data, and `docs/postman/` has a ready-to-import Postman collection + environment with a working guest token pre-loaded (Ahmad Khalil, checked in). See `docs/postman/README.md`.

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
| `Content-Type` | Requests with body | `application/json` |
| `Authorization` | Authenticated requests | `Bearer <guest-token>` |

Mirror `guest.preferred_locale` (returned at login) into the app locale setting on first login.

**`Accept-Language` does NOT localize content fields.** Bilingual content (room names, menu items, page bodies, etc.) is always returned as `{ "en": "...", "ar": "..." }` — the header only picks the language of the envelope's `message` and validation error strings. The app is responsible for picking `field.en` or `field.ar` itself based on the app's own locale.

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
| **Public** | No token | Content, availability, price quote, event inquiry, public booking endpoints |
| **Authenticated guest** | Guest token (`Authorization: Bearer`) | Profile, my-reservations, cancel, booking (authenticated path), device registration, chat |
| **Pre-arrival guest** | Guest token + confirmed reservation covering the current/upcoming window | Tier-3a: document upload, e-check-in approval, service pre-bookings (spa/table/cabana/transfer) |
| **In-stay guest** | Guest token + reservation is `checked_in` | Tier-3b: in-room service requests, folio, express checkout, transport requests |

`GET /api/auth/guest/me` returns two entitlement booleans so the app knows which mode to render — the server enforces the gate server-side; hiding UI client-side is convenience only:
- `has_booking: bool` — unlocks the pre-arrival tier.
- `is_checked_in: bool` — unlocks the in-stay tier. A checked-in guest also has `has_booking: true` (same reservation).
- `has_active_reservation: bool` — **deprecated alias, equal to `has_booking`.** Kept for one release since existing app code reads it; new code should read `has_booking` / `is_checked_in` directly.

Both gates reject with `error_code: no_active_reservation` (403) when unmet — this is the single error code to handle for "you need to be further along in your stay to do this."

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
```

**Path C — Guest just verified a website booking:** the app can call `POST /auth/guest/link-booking-code` with the `booking_code` shown on the website's confirmation screen, same as Path B — this is how a website booking becomes manageable from the app.

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
| `booking_code` | string | ✅ | Format `CARL-XXXXXXXX` (printed on confirmation) |
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

**State notes:** Navigate to OTP entry. Submit with `purpose=booking_link`.

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

## Module: Content (tier-1, public)

All read-only, no token required. Same content the website shows — pulled by the app for the "explore the hotel" screens. `is_active = false` records 404.

| Type | List | Show |
|---|---|---|
| Room types | `GET /public/room-types` | `GET /public/room-types/{uuid}` |
| Rooms | `GET /public/rooms` | `GET /public/rooms/{uuid}` |
| Facilities | `GET /public/facilities` | `GET /public/facilities/{uuid}` |
| Dining venues | `GET /public/dining-venues` | `GET /public/dining-venues/{uuid}` |
| Event spaces | `GET /public/event-spaces` | `GET /public/event-spaces/{uuid}` |
| Pages | — | `GET /public/pages/{slug}` |
| Promotions | `GET /public/promotions` | `GET /public/promotions/{uuid}` |

List endpoints are paginated (`data.items` + `data.meta`, 15/page). Every type except `Page` carries an `images: [{uuid, url, file_name, sort_order}]` array. Room type/facility/dining/event-space/promotion names, descriptions, etc. are all `{en, ar}` objects. `RoomType.amenities` is a plain string array; `EventSpace.amenities` is a **translatable string** (`{en, ar}`) — don't share parsing logic between them.

---

## Module: Booking & Reservations

### GET /public/availability / GET /public/quote

Same as the website (public, tier-1) — see request/response shapes in `API_GUIDE_WEBSITE.md`. Used for the app's own booking flow and for showing price before a returning guest re-books.

### Two entry paths, one destination

- `POST /reservations` — **app-only, tier-2.** Guest already has a token; identity comes from the token, body contact fields are ignored. One step, no OTP.
- `POST /reservations/guest` + `POST /reservations/guest/verify` — **public, two-step** (same flow as the website — see `API_GUIDE_WEBSITE.md`'s Module: Availability, Quote & Booking). The app **keeps** the token this returns (the website discards it) — this is how a brand-new guest booking on the app becomes a logged-in session in one motion.

### POST /api/reservations

**Purpose:** One-step authenticated booking.

**Who can call:** Tier-2 (any guest token).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `room_type_uuid` | string | ✅ | Must exist |
| `check_in` | date | ✅ | Today or later |
| `check_out` | date | ✅ | After `check_in` |
| `payment_method` | string | ✅ | `cash` or `on_arrival` |
| `promo_code` | string | optional | |

**Response `data`** (HTTP 201) — a Reservation object, status starts at `pending`:
```json
{
  "uuid": "...", "booking_code": "CARL-XXXXXXXX", "status": "pending",
  "check_in": "2026-07-20", "check_out": "2026-07-22", "nights": 2,
  "source": "direct", "payment_method": "cash", "total_usd": "270.00", "hold_expires_at": null
}
```

**Failure `error_code`s:** `no_availability` (409), `invalid_promo` (422), `unauthorized` (401), `validation_failed` (422).

---

### GET /api/reservations

**Purpose:** List the logged-in guest's own reservations.

**Response:** paginated (`data.items` + `data.meta`), newest first, items are the Reservation shape above.

### GET /api/reservations/{uuid}

**Purpose:** View one of your own reservations.

**Failure:** `not_found` (404) if the reservation belongs to someone else — never `forbidden`, so ownership can't be probed.

### DELETE /api/reservations/{uuid}

**Purpose:** Cancel your own reservation.

**Cancellable from:** `pending_verification`, `pending`, `confirmed`. Anything past that (`checked_in`+) → `reservation_state` (422).

**Response:** HTTP 204, `data: null`.

**Failure `error_code`s:** `not_found` (404, not yours), `reservation_state` (422, too late to cancel).

---

### Reservation status lifecycle

| Status | Meaning |
|---|---|
| `pending_verification` | Public two-step booking, awaiting OTP — soft-held, expires with the OTP window |
| `pending` | Active, no room physically assigned yet |
| `confirmed` | Staff confirmed, or payment was settled while pending |
| `checked_in` | Room physically assigned at the front desk — **this is what flips `is_checked_in` to true** |
| `checked_out` | Guest approved express checkout (see Folio module) |
| `cancelled` | Terminal |

---

## Module: In-Stay & Pre-Arrival Services

### POST /api/service-bookings

**Purpose:** Book a scheduled extra — spa, restaurant table, pool cabana, or airport transfer — ahead of or during the stay.

**Who can call:** Tier-3a (`has_booking` — booked, checked-in not required).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `bookable_type` | string | ✅ | `spa_service`, `restaurant_table`, `pool_cabana`, or `transfer` |
| `bookable_uuid` | string | ✅ | uuid of the specific spa service/table/cabana/transfer |
| `scheduled_at` | datetime | ✅ | Must be in the future |
| `notes` | string | optional | Max 1000 |

**Response `data`** (HTTP 201):
```json
{
  "uuid": "...", "bookable_type": "spa_service",
  "bookable": { "uuid": "...", "label": "Deep Tissue Massage" },
  "scheduled_at": "2026-07-17T14:00:00.000000Z", "status": "pending", "notes": "..."
}
```
`status`: `pending`, `confirmed`, `cancelled`, `completed`.

**Failure `error_code`s:** `no_active_reservation` (403, not booked), `not_found` (404, bad `bookable_uuid`), `validation_failed` (422).

---

### POST /api/pre-arrival/documents

**Purpose:** Upload identity documents for e-check-in.

**Who can call:** Tier-3a (`has_booking`).

**Request body** (multipart):
```json
{ "documents": [ { "type": "passport", "file": "<binary>" } ] }
```
`documents` — required array, min 1 item. `type` — required string, free-form (e.g. `passport`, `id_card`, `visa`). `file` — required, `jpg`/`jpeg`/`png`/`pdf`, max 10MB.

**Response `data`** (HTTP 201):
```json
[ { "uuid": "...", "type": "passport" } ]
```
No file URL is returned to the guest.

Submitting documents (re)opens a `pending` check-in approval on the reservation — staff review and approve/reject it (dashboard-side).

**Failure `error_code`s:** `no_active_reservation` (403), `validation_failed` (422, bad mime/size).

---

### POST /api/service-requests + GET /api/service-requests

**Purpose:** Ad-hoc in-room requests — room service, housekeeping, wake-up calls, etc.

**Who can call:** Tier-3b (`is_checked_in` — stricter than the bookings above; a confirmed-but-not-checked-in guest is rejected here).

**Request body (`POST`):**

| Field | Type | Required | Notes |
|---|---|---|---|
| `type` | string | ✅ | Free-form; `room_service` and `housekeeping` route to specific departments, anything else routes to `concierge` |
| `priority` | string | optional | `low`, `normal` (default), `high` |
| `notes` | string | optional | Max 1000 |

**Response `data`** (HTTP 201):
```json
{ "uuid": "...", "type": "room_service", "department": "kitchen", "status": "new", "priority": "normal", "notes": "...", "created_at": "..." }
```
`status`: `new`, `in_progress`, `completed`, `cancelled`.

**`GET`** returns your own requests only, paginated, newest first.

**Failure `error_code`s:** `no_active_reservation` (403, booked but not checked in yet — or not booked at all), `validation_failed` (422).

---

## Module: Folio & Express Checkout

All tier-3b (`is_checked_in`).

### GET /api/folio

**Purpose:** Review your current bill.

**Response `data`:**
```json
{
  "uuid": "...", "reservation_uuid": "...", "status": "open",
  "subtotal_usd": "380.00", "total_usd": "380.00",
  "approved_by_guest_at": null, "settled_at": null,
  "items": [
    { "uuid": "...", "description": "Room charge", "amount_usd": "300.00", "source_type": "reservation" },
    { "uuid": "...", "description": "Pool Cabana", "amount_usd": "80.00", "source_type": "service_booking" }
  ]
}
```
The folio recalculates on every call (room charge + confirmed/completed priced service bookings) until it's `settled`, after which it's frozen. `status`: `open` or `settled`. Requesting service bookings with no price (e.g. a restaurant table) or plain service requests don't appear as line items — only priced bookable extras do, for now.

### POST /api/folio/approve

**Purpose:** Express checkout — approve your bill and finish your stay without going to the desk.

**Response `data`:** same Folio shape as above, now with `approved_by_guest_at` set. **This also transitions your reservation to `checked_out`.** It does not settle payment — that's still a front-desk/admin action (cash or already paid on arrival).

**Failure:** `no_active_reservation` (403).

### POST /api/transport-requests

**Purpose:** Request an airport transfer / transport pickup — a thin wrapper over service requests.

**Request body:** `{ "notes"?: string }` (max 1000).

**Response `data`** (HTTP 201): a service-request object with `type: "transport"`, `department: "concierge"`.

---

## Module: Notifications & Chat (tier-2 — any guest token)

### POST /api/device-tokens

**Purpose:** Register this device for push (FCM). Call on app launch/login whenever the stored token differs from the last registered one.

**Request body:** `{ "token": "<fcm-registration-token>", "platform": "ios" | "android" | "web" }`

**Response `data`:** `{ "uuid", "platform", "last_used_at" }`. Registering the same token again (e.g. app reopened) just refreshes `last_used_at` — safe to call idempotently.

### Chat

One ongoing support conversation with staff per guest — no thread management needed client-side.

- `GET /api/conversations` — paginated list of your conversation(s) (in practice, one).
- `GET /api/conversations/{uuid}/messages` — paginated history, oldest first.
- `POST /api/conversations` — send a message; body `{ "body"?: string, "attachment"?: file }` (at least one required, image only, max 5MB). Auto-opens a conversation on your first message and reuses it while open.

**Message shape:** `{ "uuid", "sender_type": "guest" | "staff", "body", "attachment_url", "created_at" }`.

Live delivery mirrors to Firestore (`chats` collection, one doc per message keyed by `uuid`, filter by `conversation_uuid`) — subscribe there for real-time updates instead of polling; MySQL via the endpoints above remains the source of truth for history/pagination.

**Push triggers already wired:** a welcome notification on first-ever device registration, and a "room ready" push when staff assign your room at check-in. Order-status and ticket-reply pushes land once P10's operations queue grows a status-change action (not yet built) and P11 ships the chatbot.

---

## Module: Event Inquiry (RFP)

`POST /event-inquiries` — public (tier-1), same endpoint and shape as the website (see `API_GUIDE_WEBSITE.md`'s Module: Event Inquiry). If called with a guest token attached, the inquiry is silently linked to your guest record; the response is identical either way.

---

## Coming in P11 — AI chatbot

`POST /chatbot/message` — public (tier-1) or authenticated.

---

## Coming in P12 — Profile editing

Update `first_name`, `last_name`, `preferred_locale`; change password; device management.
