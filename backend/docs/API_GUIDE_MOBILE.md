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

**Dev/testing note:** no real SMS/WhatsApp/email provider is wired yet (`OtpDispatcher` is still a stub — tracked for a future phase). In local/testing environments the code is **always `000000`** — no need to dig through logs. This is environment-gated (`app()->isLocal() || app()->environment('testing')`); real random codes are generated everywhere else, so this has no effect once deployed.

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
| `first_name` / `last_name` | Null until the profile is completed — see below. |

### PUT /api/auth/guest/profile

**Purpose:** Complete or edit the profile after OTP sign-in ("create profile" screen).

**Who can call:** Tier-2 (any guest token).

| Field | Type | Required | Notes |
|---|---|---|---|
| `first_name` | string | optional | Max 255 |
| `last_name` | string | optional | Max 255 |
| `phone` | string | optional | Normalized to E.164 server-side; send local format if you like |
| `email` | string | optional | Lower-cased server-side |
| `preferred_locale` | string | optional | `en` or `ar` |

Send only the fields you are changing — omitted fields are left alone.

**You may fill in a phone or email the guest does not have yet** (the usual case: signed in by phone, now adding an email). **You may not replace one that is already verified** — that would move the login identifier without proving ownership of the new one. Route the guest back through `request-otp` / `verify-otp` for that.

**Response `data`:** the full guest object (same shape as `GET /api/auth/guest/me`).

**Failure `error_code`s:** `verified_contact_immutable` (409), `validation_failed` (422 — includes a phone that could not be parsed, or an email/phone already taken by another guest).

---

## Module: Content (tier-1, public)

All read-only, no token required. Same content the website shows — pulled by the app for the "explore the hotel" screens. `is_active = false` records 404.

| Type | List | Show |
|---|---|---|
| Home sliders | `GET /public/home-sliders` | — |
| Room types | `GET /public/room-types` | `GET /public/room-types/{uuid}` |
| Rooms | `GET /public/rooms` | `GET /public/rooms/{uuid}` |
| Amenities | `GET /public/amenities` | — |
| Facilities | `GET /public/facilities` | `GET /public/facilities/{uuid}` |
| Dining venues | `GET /public/dining-venues` | `GET /public/dining-venues/{uuid}` |
| Event spaces | `GET /public/event-spaces` | `GET /public/event-spaces/{uuid}` |
| Pages | — | `GET /public/pages/{slug}` |
| Promotions (offers) | `GET /public/promotions` | `GET /public/promotions/{uuid}` |
| Service catalog | `GET /public/service-catalog` | — |
| Reviews | `GET /public/reviews/{type}/{uuid}` | — |

List endpoints are paginated (`data.items` + `data.meta`, 15/page) unless noted. Every type except `Page` carries an `images: [{uuid, url, file_name, sort_order}]` array. Names, descriptions, etc. are all `{en, ar}` objects — pick the key matching your locale. `EventSpace.amenities` is a **translatable string** (`{en, ar}`), unlike the room-type amenity objects below — don't share parsing logic between them.

### Home screen mapping

| Home section | Endpoint | Fields |
|---|---|---|
| Hero slider | `GET /public/home-sliders` | `photo`, `header_text`, `location`, `description_text` |
| Rooms | `GET /public/room-types` | `name`, `banner`, `view_type`, `size_sqm`, `bed_types`, `base_price_usd` |
| Restaurants | `GET /public/dining-venues` | `banner`, `name`, `cuisine_type`, `hours`, `location` |
| Offers | `GET /public/promotions` | `title`, `description`, `banner`, `secondary_description` |

`banner` is the first image by `sort_order`, or `null` when nothing is uploaded. The full `images` array is still available for galleries.

### Room details — `GET /public/room-types/{uuid}`

| Field | Notes |
|---|---|
| `images` | Gallery |
| `banner` | First image |
| `name` / `description` | `{en, ar}` |
| `base_price_usd` | Nightly rate |
| `size_sqm` | Room area |
| `view_type` | `city`, `garden`, `pool`, `courtyard`, `mountain`, `interior`, or `null` |
| `bed_types` | Array of `king`, `queen`, `double`, `twin`, `single`, `extra` |
| `rating` / `rating_count` | From published guest reviews; `rating` is `null` until the first one |
| `highlights` | Exactly 4 amenities for the card — flagged ones first, topped up from the head of the list |
| `amenities` | Full list: `[{uuid, slug, name, icon, sort_order}]` |
| `cancellation_hours` | Hours before check-in that cancellation is still free |

`icon` on an amenity is a stable key (`balcony`, `jacuzzi`, `desk`, `tv`, `safe`, `coffee`) — map it to your own icon set, it is never a URL.

### Restaurant details

- `GET /public/dining-venues/{uuid}` — about (`description`), `hours`, `location`, `cuisine_type`, `rating`, `rating_count`, `images` (gallery).
- `GET /public/dining-venues/{uuid}/menu-categories` — the filter chips: `[{uuid, slug, name, sort_order}]`. Un-paginated.
- `GET /public/dining-venues/{uuid}/menu?type={slug}` — paginated menu items. Omit `type` for the whole menu.

Menu item shape: `{ uuid, type, name, description, price_usd, is_vegan, photo }` where `type` is the category slug (`breakfast`, `starters`, `main`, `dessert`).

### Reviews

- `GET /public/reviews/{type}/{uuid}` — published reviews, newest first, paginated. `{type}` is `room_type` or `dining_venue`.
- `POST /api/reviews/{type}/{uuid}` — tier-2. Body `{ "rating": 1-5, "comment"?: string }`. Submitting again **edits** your existing review rather than adding a second one (201 the first time, 200 after).

Review shape: `{ uuid, rating, comment, is_verified_stay, created_at, author: { first_name, last_name } }`. `is_verified_stay` is derived server-side from your reservation history — you cannot set it.

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

### POST /api/dining-venues/{uuid}/table-reservations

**Purpose:** Reserve a table at a restaurant. Use this instead of `POST /api/service-bookings` for dining — the guest picks a party size, not a table.

**Who can call:** Tier-3a (`has_booking`).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `date` | string | ✅ | `Y-m-d`, today or later |
| `time` | string | ✅ | `H:i` (24h) |
| `guest_count` | integer | ✅ | 1–20 |
| `special_request` | string | optional | Max 1000 — lands in the booking's `notes` |

The backend assigns the smallest table that seats the party and is free for a two-hour seating window. You never send a table uuid.

**Response `data`** (HTTP 201): a service booking with `bookable_type: "restaurant_table"`, the assigned table as `bookable.label`, plus `guest_count`.

**Failure `error_code`s:** `no_availability` (409, nothing free for that slot/party size), `no_active_reservation` (403), `not_found` (404, inactive venue), `validation_failed` (422).

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
| `service_item_uuid` | string | see note | The catalog item the guest tapped — from `GET /public/service-catalog` |
| `type` | string | see note | Legacy free-string path. `room_service`/`housekeeping`/`laundry`/`maintenance` route to specific departments, anything else to `concierge` |
| `priority` | string | optional | `low`, `normal` (default), `high` |
| `notes` | string | optional | Max 1000 — this is the "special instructions" field |

Send **either** `service_item_uuid` (preferred) **or** `type`. When an item is sent, `type` and `department` are derived from its category and ignored if you also send them.

**Response `data`** (HTTP 201):
```json
{ "uuid": "...", "type": "room_service", "department": "kitchen", "status": "new",
  "priority": "normal", "notes": "...", "created_at": "...",
  "category_code": "room_service",
  "service_item": { "uuid": "...", "name": {"en": "Carlton Breakfast", "ar": "..."},
                    "description": {"en": "...", "ar": "..."},
                    "expected_minutes": 30, "price_usd": "18.00" } }
```
`status`: `new`, `in_progress`, `completed`, `cancelled`. `service_item` and `category_code` are `null` for legacy free-string requests.

**Billing:** an item with a non-null `price_usd` is charged to your folio when it is generated. Items with `price_usd: null` are complimentary. Cancelled requests are never charged.

**`GET`** returns your own requests only, paginated, newest first.

**Failure `error_code`s:** `no_active_reservation` (403, booked but not checked in yet — or not booked at all), `validation_failed` (422 — neither field sent, or an unknown/inactive item uuid).

---

## Module: Service Catalog

### GET /public/service-catalog

**Purpose:** The services screen — eight categories, each with its microservices.

**Who can call:** Tier-1 (public). Un-paginated; `data` is a plain array ordered by `sort_order`.

```json
[{
  "uuid": "...", "code": "room_service", "kind": "catalog",
  "name": {"en": "Room Service", "ar": "..."},
  "description": {"en": "...", "ar": "..."},
  "icon": "room_service", "link_target": null, "default_item_uuid": null,
  "department": "kitchen", "sort_order": 0, "is_active": true,
  "items": [{ "uuid": "...", "name": {"en": "Carlton Breakfast", "ar": "..."},
              "description": {"en": "Full breakfast selection with fresh juice", "ar": "..."},
              "expected_minutes": 30, "price_usd": "18.00" }]
}]
```

**Switch on `kind` — one screen algorithm covers all eight categories:**

| `kind` | Categories | What the app does |
|---|---|---|
| `catalog` | Room Service, House Keeping, Laundry | Show `items`, then `POST /api/service-requests` with the chosen `service_item_uuid` |
| `direct` | Concierge, Transport, Maintenance | `items` is empty. Open a notes sheet and post `default_item_uuid` straight away |
| `link` | Restaurant | Navigate by `link_target` (`dining`) to the dining venue screens. No request row |
| `toggle` | Do Not Disturb | Render a switch bound to `PATCH /api/stays/active/dnd` |

`expected_minutes` is an integer — format and localize it yourself ("~30 min" / "٣٠ دقيقة"). `null` means no time is promised. Adding a category server-side needs no app release, so treat an unknown `kind` as "hide".

---

## Module: Stays

Read projections over your reservations for the three stay screens. All three
reads are **tier-2** (`auth:guests` only) — having no active stay is an empty
state, not an error, so don't treat a `null`/`[]` payload as a failure.

### GET /api/stays/active

`data` is a single object, or `null` when you are not currently checked in.

| Field | Notes |
|---|---|
| `room_number` | e.g. `"812"` — assigned at check-in, so non-null here |
| `room_name` | `{en, ar}` room type name |
| `checked_in_at` | ISO 8601. **`null` for stays that predate this feature** — fall back to `check_in` |
| `check_in` / `check_out` | Dates |
| `nights` / `nights_remaining` | `nights_remaining` floors at 0 |
| `dnd` | `{enabled, until}` |
| `folio_total_usd` | `null` until staff generate the folio |

### GET /api/stays/upcoming

`data` is an array (you may hold several future bookings), soonest first. `pending_verification` holds are excluded.

| Field | Notes |
|---|---|
| `booking_code` | The "reservation code" |
| `room_number` | ⚠ **usually `null`** — rooms are assigned at check-in. Render `room_name` pre-arrival |
| `room_name` | `{en, ar}` |
| `price_usd` | Reservation total |
| `check_in` / `check_out` / `nights` | |
| `is_cancellable` | Whether `DELETE /api/reservations/{uuid}` will succeed |

### GET /api/stays/past

Paginated (`data.items` + `data.meta`), most recent checkout first. Includes cancelled stays.

| Field | Notes |
|---|---|
| `room_name` | `{en, ar}` |
| `total_nights` | |
| `check_in` / `check_out` / `checked_out_at` | `checked_out_at` is `null` for older stays |
| `total_charge_usd` | The folio total when one exists (frozen at settlement), else the reservation total |
| `status` | ⚠ raw `checked_out` or `cancelled` — there is **no** `complete` status. Label `checked_out` as "Completed" client-side |
| `has_receipt` | `false` when nothing was ever billed (e.g. a cancelled stay) |
| `room_type_uuid` | Powers **Book again** |

### Checkout, cancel, book again

These reuse endpoints you already have — there are no new ones:

| Action | Endpoint |
|---|---|
| Check out of the active stay | `POST /api/folio/approve` (approves the bill *and* flips the stay to `checked_out`) |
| Cancel an upcoming stay | `DELETE /api/reservations/{uuid}` |
| Book again | Deep-link your own booking flow with `room_type_uuid`, then `GET /public/availability` → `GET /public/quote` → `POST /api/reservations` |

Book again is deliberately client-side: a new booking needs fresh dates, current availability and current price, none of which the server can infer from a past stay.

### GET /api/stays/{uuid}/receipt

The bill for any stay of yours that has a folio — including past ones. Read-only; it never recalculates the folio.

```json
{ "reservation": { "uuid", "booking_code", "check_in", "check_out", "nights", "guest_name" },
  "folio": { "uuid", "status", "subtotal_usd", "total_usd", "approved_by_guest_at", "settled_at" },
  "items": [{ "description", "amount_usd", "source_type" }],
  "payments": [{ "method", "amount_usd", "status", "created_at" }],
  "balance_due_usd": 0 }
```

⚠ `items[].description` is a plain string, not `{en, ar}` — line items render as recorded regardless of locale.

### GET /api/stays/{uuid}/receipt/pdf

Returns raw `application/pdf` with `Content-Disposition: attachment` — **the one endpoint that does not use the JSON envelope.** Errors still return the normal error envelope. Labels follow `Accept-Language`; Arabic renders correctly (shaped, RTL).

**Failure `error_code`s (both receipt endpoints):** `not_found` (404 — no folio, or not your stay; someone else's stay always 404s, never 403).

### PATCH /api/stays/active/dnd

**Who can call:** Tier-3b (`is_checked_in`) — do-not-disturb needs a stay in progress.

Body `{ "enabled": bool, "until"?: ISO 8601 }`. Enabling without `until` defaults to the end of the current hotel day. Returns `{ "enabled", "until" }`.

DND is stored as an expiry, not a flag, so a toggle the guest forgets clears itself. It does **not** create a service request.

**Failure `error_code`s:** `no_active_reservation` (403), `validation_failed` (422).

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
