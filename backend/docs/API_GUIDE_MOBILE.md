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
| `Accept-Language` | Always | `en`, `ar`, `fr`, `tr` or `es` (negotiated server-side; falls back to the app default locale) — controls only `message`/error/validation strings |
| `Content-Type` | Requests with body | `application/json` |
| `Authorization` | Authenticated requests | `Bearer <guest-token>` |

Mirror `guest.preferred_locale` (returned at login) into the app locale setting on first login.

**`Accept-Language` does NOT localize content fields.** Translatable content (room names, menu items, page bodies, etc.) is returned as a locale-keyed map over the configured locales (`{ "en": "...", "ar": "...", "fr": "..." }`; a locale with no content may be absent) — the header only picks the language of the envelope's `message` and validation error strings. The app is responsible for picking `field[locale]` itself based on the app's own locale, falling back to `en`.

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

## Endpoint index

Every endpoint the app can reach — 81 in total. Tier column: **P** public (no token), **G** any guest token, **A** pre-arrival (token + booking), **S** in-stay (token + `checked_in`). Anything not on this list is dashboard-only and will 401/403 for a guest token.

| Tier | Method | Path | Section |
|---|---|---|---|
| P | GET | `/health` | [System](#module-system) |
| P | POST | `/auth/guest/request-otp` | [Guest Auth](#module-guest-auth) |
| P | POST | `/auth/guest/verify-otp` | [Guest Auth](#module-guest-auth) |
| P | POST | `/auth/guest/link-booking-code` | [Guest Auth](#module-guest-auth) |
| G | GET | `/auth/guest/me` | [Guest Auth](#module-guest-auth) |
| G | PUT | `/auth/guest/profile` | [Guest Auth](#module-guest-auth) |
| G | PATCH | `/auth/guest/preferences` | [Guest Auth](#module-guest-auth) |
| G | DELETE | `/auth/guest/me` | [Guest Auth](#module-guest-auth) |
| G | POST | `/auth/guest/logout` | [Guest Auth](#module-guest-auth) |
| P | GET | `/public/exchange-rates` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/home-sliders` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/room-types` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/room-types/{uuid}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/rooms` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/rooms/{uuid}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/amenities` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/facilities` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/facilities/{uuid}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/dining-venues` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/dining-venues/{uuid}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/dining-venues/{uuid}/menu-categories` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/dining-venues/{uuid}/menu` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/dining-venues/{uuid}/tables` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/event-spaces` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/event-spaces/{uuid}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/pages/{slug}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/promotions` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/promotions/{uuid}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/experiences` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/experiences/{uuid}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/faqs` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/testimonials` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/gallery-categories` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/gallery` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/journal` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/journal/{slug}` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/settings` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/dining-venues/{uuid}/menu/download` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/service-catalog` | [Service Catalog](#module-service-catalog) |
| P | GET | `/public/spa-services` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/pool-cabanas` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/transfers` | [Content](#module-content-tier-1-public) |
| P | GET | `/public/reviews/{type}/{uuid}` | [Reviews](#reviews) |
| G | POST | `/reviews/{type}/{uuid}` | [Reviews](#reviews) |
| P | GET | `/public/availability` | [Booking](#module-booking--reservations) |
| P | GET | `/public/quote` | [Booking](#module-booking--reservations) |
| P | POST | `/reservations/guest` | [Booking](#module-booking--reservations) |
| P | POST | `/reservations/guest/verify` | [Booking](#module-booking--reservations) |
| G | POST | `/reservations` | [Booking](#module-booking--reservations) |
| G | GET | `/reservations` | [Booking](#module-booking--reservations) |
| G | GET | `/reservations/{uuid}` | [Booking](#module-booking--reservations) |
| G | DELETE | `/reservations/{uuid}` | [Booking](#module-booking--reservations) |
| G | GET | `/stays/status` | [Stays](#module-stays) |
| G | GET | `/stays/active` | [Stays](#module-stays) |
| G | GET | `/stays/upcoming` | [Stays](#module-stays) |
| G | GET | `/stays/past` | [Stays](#module-stays) |
| G | GET | `/stays/{uuid}/receipt` | [Stays](#module-stays) |
| G | GET | `/stays/{uuid}/receipt/pdf` | [Stays](#module-stays) |
| G | POST | `/stays/check-in` | [Stays](#module-stays) |
| G | POST | `/stays/{uuid}/online-check-in` | [Stays](#module-stays) |
| S | PATCH | `/stays/active/dnd` | [Stays](#module-stays) |
| A | POST | `/service-bookings` | [In-Stay & Pre-Arrival](#module-in-stay--pre-arrival-services) |
| A | POST | `/dining-venues/{uuid}/table-reservations` | [In-Stay & Pre-Arrival](#module-in-stay--pre-arrival-services) |
| A | POST | `/pre-arrival/documents` | [In-Stay & Pre-Arrival](#module-in-stay--pre-arrival-services) |
| S | POST | `/service-requests` | [In-Stay & Pre-Arrival](#module-in-stay--pre-arrival-services) |
| S | GET | `/service-requests` | [In-Stay & Pre-Arrival](#module-in-stay--pre-arrival-services) |
| S | POST | `/transport-requests` | [Folio](#module-folio--express-checkout) |
| S | GET | `/folio` | [Folio](#module-folio--express-checkout) |
| S | POST | `/folio/approve` | [Folio](#module-folio--express-checkout) |
| S | PATCH | `/folio/items/{item}/dispute` | [Folio](#module-folio--express-checkout) |
| G | GET | `/loyalty/account` | [Loyalty](#module-loyalty) |
| G | GET | `/loyalty/ledger` | [Loyalty](#module-loyalty) |
| G | GET | `/loyalty/rewards` | [Loyalty](#module-loyalty) |
| G | POST | `/loyalty/rewards/{uuid}/redeem` | [Loyalty](#module-loyalty) |
| G | GET | `/loyalty/vouchers` | [Loyalty](#module-loyalty) |
| G | GET | `/loyalty/preview` | [Loyalty](#module-loyalty) |
| G | POST | `/device-tokens` | [Notifications & Chat](#module-notifications--chat-tier-2--any-guest-token) |
| G | GET | `/conversations` | [Notifications & Chat](#module-notifications--chat-tier-2--any-guest-token) |
| G | POST | `/conversations` | [Notifications & Chat](#module-notifications--chat-tier-2--any-guest-token) |
| G | GET | `/conversations/{uuid}/messages` | [Notifications & Chat](#module-notifications--chat-tier-2--any-guest-token) |
| P | POST | `/event-inquiries` | [Event Inquiry](#module-event-inquiry-rfp) |

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
| `too_many_requests` | 429 | "Please wait before requesting another code." Disable resend for 60 s. |
| `validation_failed` | 422 | Show field errors. Sending neither phone nor email lands here too, with the message under `errors.identity` (`errors.phone` / `errors.email` for a channel mismatch). |

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
- When this call **creates** a new guest, `preferred_locale` is seeded from the request's negotiated `Accept-Language` locale (`en`/`ar`/`fr`/`tr`/`es`, falling back to the app default). An existing guest's `preferred_locale` is never changed by the header — send `Accept-Language` in the app locale on this call.

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

**Response on success:** the envelope `message` is the localized "OTP sent" text; `data` is:
```json
{
  "identifier_masked": "+963*********",
  "channel": "sms"
}
```
`channel` is `sms` or `email`. Show `identifier_masked` so the guest knows where to look.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `booking_link_failed` | 404 | Generic "Reservation not found." Returned for every miss (unknown code, wrong last name/phone, booking with no contact on file) with an identical body (`context: null`), so it never reveals which part was wrong. |
| `validation_failed` | 422 | Missing second factor (`errors.booking_code`), malformed code format. |
| `too_many_requests` | 429 | 10 requests per minute per IP, plus the per-contact OTP limits after a match. Back off. |

**State notes:** Navigate to OTP entry. Submit with `purpose=booking_link`. If `verify-otp` is called with a `booking_code` that does not match, it still signs the guest in but links nothing - call `GET /api/reservations` afterwards to confirm the booking is there.

---

### GET /api/auth/guest/me

**Purpose:** The signed-in guest plus the two entitlement flags that decide which mode the app renders. Call on launch after restoring a stored token, and again after any check-in/checkout.

**Who can call:** Tier-2 (any guest token).

**Request:** No body.

**Response `data`:**
```json
{
  "uuid": "...", "name": "Ahmad Khalil", "first_name": "Ahmad", "last_name": "Khalil",
  "phone": "+963900000001", "phone_country": "SY", "phone_verified": true,
  "email": "ahmad.khalil@example.com", "email_verified": true,
  "preferred_locale": "en",
  "has_booking": true,
  "is_checked_in": true,
  "has_active_reservation": true,
  "active_reservation": {
    "uuid": "...", "booking_code": "CARL-DEMO0001", "status": "checked_in",
    "check_in": "2026-07-26", "check_out": "2026-07-29"
  }
}
```

| Field | Notes |
|---|---|
| `has_booking` | Unlocks the pre-arrival tier. True while you hold a `confirmed` or `checked_in` reservation whose `check_out` has not passed. |
| `is_checked_in` | Unlocks the in-stay tier. |
| `has_active_reservation` | **Deprecated alias of `has_booking`** — read the two flags above instead. |
| `active_reservation` | The latest booking by `check_in`, or `null`. A trimmed shape — use `GET /stays/active` for the full in-stay payload. |
| `phone_verified` / `email_verified` | Which contacts the guest has proven. A guest created by reception has neither until they link their booking. |

**Failure `error_code`s:** `unauthenticated` (401, token missing/expired).

> Prefer `GET /stays/status` when all you need is the check-in state — it answers the same entitlement question without loading the profile.

---

## Guest profile fields

| Field | Notes |
|---|---|
| `uuid` | Stable identifier. Use in all guest-specific paths. Never use integer IDs. |
| `phone` | E.164 format (`+963...`). |
| `phone_country` | ISO 3166-1 alpha-2 (e.g. `SY`). |
| `phone_verified` / `email_verified` | `false` = contact not yet OTP-verified. |
| `preferred_locale` | One of `en`, `ar`, `fr`, `tr`, `es`. Mirror into app locale on first login. |
| `first_name` / `last_name` | Null until the profile is completed — see below. |
| `preferences` | `{ bed_type, pillow_type, floor_preference, other, updated_at }` on `me`, on the profile PUT response and wherever the guest object is nested (e.g. `GET /reservations` items' `guest`) — the same object `PATCH /auth/guest/preferences` returns (Phase 4, D-09). |

### PUT /api/auth/guest/profile

**Purpose:** Complete or edit the profile after OTP sign-in ("create profile" screen).

**Who can call:** Tier-2 (any guest token).

| Field | Type | Required | Notes |
|---|---|---|---|
| `first_name` | string | optional | Max 255 |
| `last_name` | string | optional | Max 255 |
| `phone` | string | optional | Normalized to E.164 server-side; send local format if you like |
| `email` | string | optional | Lower-cased server-side |
| `preferred_locale` | string | optional | `en`, `ar`, `fr`, `tr` or `es`. Trimmed and lower-cased (`"FR"` becomes `fr`). Region tags (`fr-FR`), unknown values (`de`), `""` and `null` are 422 `validation_failed` on `preferred_locale`. Omitting it leaves the stored value unchanged. |

Send only the fields you are changing — omitted fields are left alone.

**You may fill in a phone or email the guest does not have yet** (the usual case: signed in by phone, now adding an email). **You may not replace one that is already verified** — that would move the login identifier without proving ownership of the new one. Route the guest back through `request-otp` / `verify-otp` for that.

**Response `data`:** the full guest object (same shape as `GET /api/auth/guest/me`).

**Failure `error_code`s:** `verified_contact_immutable` (409), `validation_failed` (422 — includes a phone that could not be parsed, or an email/phone already taken by another guest).

---

### PATCH /api/auth/guest/preferences

**Purpose:** Save the guest's own room preferences (bed, pillow, floor, free-text note) — surfaced back to reception on the guest's profile.

**Who can call:** Tier-2 (any guest token).

**Request body:** PATCH semantics — a present key is written, an explicit `null` clears it, an absent key is left alone. Sending none of the four keys is `422` on `errors.preferences`.

| Field | Type | Required | Notes |
|---|---|---|---|
| `bed_type` | string, nullable | sometimes | `king`, `queen`, `double`, `twin`, `single` — **`extra` is refused** (inventory-only bed, never a guest preference). |
| `pillow_type` | string, nullable | sometimes | `soft`, `medium`, `firm`, `feather`, `hypoallergenic`. |
| `floor_preference` | string, nullable | sometimes | `low`, `high`, `any` (`any` is a positive "no preference" choice, not the absence of one). |
| `other` | string, nullable | sometimes | Max 500. |

**Response `data`** (HTTP 200): `{ "bed_type": "king", "pillow_type": "firm", "floor_preference": "high", "other": "Extra towels please", "updated_at": "2026-09-26T10:00:00+00:00" }`, message `"Preferences updated."`. The same object also appears as `preferences` on `GET /api/auth/guest/me`.

**Failure `error_code`s:** `unauthenticated` (401), `validation_failed` (422 — unknown enum value, `extra` for `bed_type`, or `errors.preferences` on a body with none of the four keys).

---

### DELETE /api/auth/guest/me

**Purpose:** In-app account deletion (Apple 5.1.1(v)). The account is anonymized, never hard-deleted.

**Who can call:** Tier-2 (any guest token). Throttled 5/min per guest.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `confirm` | boolean | ✅ | Must be accepted: `true`, `1`, `"yes"` or `"on"`. Missing, `false` or `"no"` is 422 `validation_failed` with `errors.confirm`. |

**Response** (HTTP 200): `{ "success": true, "message": "Your account has been deleted.", "data": null, "request_id": "..." }`. All of the guest's tokens on every device are revoked, so a second call (or any later call with the same token) is `401`.

**Loyalty:** deleting the account forfeits every loyalty point and closes every unused voucher (status `void`, or `expired` if it was already past its expiry). This cannot be undone. Before the confirm step the app should read `GET /api/loyalty/account` (`available_points`) and `GET /api/loyalty/vouchers?status=active`, and when either is non-empty show a warning with the point total and voucher count. The response is unchanged.

**Blocked** — 422 `guest_account_deletion_blocked`, message (en): "Your account can't be deleted while you have an active stay, an open bill or an upcoming booking. Please contact the front desk."
```json
{
  "success": false,
  "message": "...",
  "error_code": "guest_account_deletion_blocked",
  "context": { "reasons": ["active_reservation"], "booking_codes": ["CARL-7K2M9QXA"] },
  "request_id": "..."
}
```

`context.reasons` is in a fixed order and any subset of:

| Reason | Meaning |
|---|---|
| `active_reservation` | A pending/confirmed stay with `check_out` today or later, or any `checked_in` stay. |
| `open_folio` | A reservation whose folio is still open (e.g. after express check-out — staff must settle). |
| `upcoming_service_booking` | A pending/confirmed service booking scheduled in the future. |

`context.booking_codes`: sorted, at most 10, of the blocking reservations. Not blocking: cancelled, `pending_verification`, checked-out with a settled folio, and past no-show stays.

**What happens**
- **Erased:** name, first/last name, phone, email, verification timestamps, preferences; all sign-in tokens, device push tokens, in-app notifications, staff notes and OTP codes; chat conversations are closed and the guest's own messages' text and attachments removed; ID documents except those of completed (checked-out) stays; the phone copy on reservations.
- **Kept (legal/accounting):** reservations (booking code, last name), folios, payments, refunds, disputes, service bookings/requests, tickets, event inquiries, reviews (shown without a name), ID registration documents of checked-out stays.
- **Re-registration:** signing in again with the same phone/email creates a **new** account (new `uuid`). Old stays are not visible to it and cannot be re-linked. The new account starts with zero points; the old balance is not carried over.

**App guidance:** show a confirmation dialog explaining what is deleted vs kept, then call. On 200, clear the token and local data and go to sign-in. On 422 `guest_account_deletion_blocked`, show the front-desk message and the `booking_codes`.

**Failure `error_code`s:** `validation_failed` (422), `guest_account_deletion_blocked` (422), `unauthenticated` (401), `too_many_requests` (429, after 5 calls per minute).

**Store compliance:** Apple 5.1.1(v) requires in-app deletion (this route). Google Play also needs a web path — the website offers it via guest OTP sign-in plus this same route.

---

### POST /api/auth/guest/logout

**Purpose:** Sign out and revoke this device's token server-side.

**Who can call:** Tier-2 (any guest token).

| Field | Type | Required | Notes |
|---|---|---|---|
| `device_token` | string | optional | Max 500. The FCM token this device registered via `POST /api/device-tokens`; send it so pushes stop reaching this device. A token that is not this guest's is ignored. |

**Response `data`:** `null`, message "Logged out successfully.".

**State notes:** Discard the stored token and clear local storage even when the call returns 401. Without `device_token`, the device registration is kept and re-assigned at the next login. Only this token is revoked; other devices stay signed in.

**Failure `error_code`s:** `unauthorized` (401 — the token was already revoked or expired, treat it as signed out), `validation_failed` (422 — `device_token` is not a string or exceeds 500 characters).

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
| Spa services | `GET /public/spa-services` | — |
| Pool cabanas | `GET /public/pool-cabanas` | — |
| Transfers | `GET /public/transfers` | — |
| Restaurant tables | `GET /public/dining-venues/{uuid}/tables` | — |
| Testimonials | `GET /public/testimonials` | — |
| FAQs | `GET /public/faqs` | — |
| Experiences | `GET /public/experiences` | `GET /public/experiences/{uuid}` |
| Gallery chips | `GET /public/gallery-categories` | — |
| Gallery photographs | `GET /public/gallery` | — |
| Journal | `GET /public/journal` | `GET /public/journal/{slug}` |
| Site settings | `GET /public/settings` | — (flat object) |
| Dining menu file | — | `GET /public/dining-venues/{uuid}/menu/download` (see [Restaurant details](#restaurant-details)) |

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

#### Menu download

`GET /api/public/dining-venues/{uuid}/menu/download` — no auth. Returns the link to the venue's downloadable menu file (PDF or image). It is a **URL, not a file stream**: open it externally (browser / system viewer).

- **200** (envelope):

```json
{
  "success": true,
  "message": "...",
  "data": {
    "url": "https://.../storage/media/menu.pdf",
    "file_name": "menu.pdf",
    "mime_type": "application/pdf",
    "size": 482113,
    "updated_at": "2026-10-03T09:15:00+00:00"
  },
  "request_id": "..."
}
```

- **204** with an **empty body and no envelope** when the venue has no menu file. Do not try to parse JSON; show "no menu available".
- **404** `not_found` for an unknown, inactive, or deleted venue.

### Reviews

- `GET /public/reviews/{type}/{uuid}` — published reviews, newest first, paginated. `{type}` is `room_type` or `dining_venue`.
- `POST /api/reviews/{type}/{uuid}` — tier-2. Body `{ "rating": 1-5, "comment"?: string }`. Submitting again **edits** your existing review rather than adding a second one (201 the first time, 200 after).

Review shape: `{ uuid, rating, comment, is_verified_stay, created_at, author: { first_name, last_name } }`. `is_verified_stay` is derived server-side from your reservation history — you cannot set it.

### Public content routes (added to the index 2026-10-07)

These routes already existed (they serve the website); the app guide now lists them. Common rules: no token, `is_active = false` records are hidden (404 on a detail route), lists are paginated `data.items` + `data.meta` at 15/page (`?per_page=` up to 100) unless noted. Translatable fields are whole locale maps `{en, ar, ...}`; an unset map can arrive as an empty array `[]`, so read defensively. Images: `image` (first image URL or `null`) and `images` (`[{uuid, url, file_name, sort_order}]`).

| Route | Purpose | Item fields |
|---|---|---|
| `GET /public/testimonials` | Curated marketing quotes (not guest reviews). No detail route. | `uuid`, `author_name` (plain string), `author_title` (map), `quote` (map), `rating` (1-5 or `null`), `is_active`, `sort_order`, `avatar`, `images` |
| `GET /public/faqs` | One accordion. No detail route. | `uuid`, `category` (free-form string or `null`), `question` (map), `answer` (map, may contain HTML), `is_active`, `sort_order` |
| `GET /public/experiences` | Concierge experiences list. | `uuid`, `slug`, `title` (map), `description` (map), `category` (free-form string), `group_size` (map), `duration_minutes` (int or `null`), `duration_label` (map), `price_usd` (decimal string or `null`), `is_active`, `sort_order`, `image`, `images` |
| `GET /public/experiences/{uuid}` | One experience, same fields. Binds by **uuid** (the `slug` is only for pretty URLs). Inactive or unknown: `404 not_found`. | as above |
| `GET /public/gallery-categories` | The chip row for the gallery screen. | `uuid`, `slug`, `name` (map), `is_active`, `sort_order` |
| `GET /public/gallery` | The photographs, ordered by chip then item `sort_order`; only items whose chip is also published. Filter by chip client-side (fetch with `?per_page=100`). | `uuid`, `caption` (map), `is_active`, `sort_order`, `category_slug`, `category` (chip object), `image` (`null` until uploaded), `images` |
| `GET /public/journal` | Articles, newest `published_on` first. `published_on` is a display date, not a schedule. | `uuid`, `slug`, `title`, `excerpt`, `body` (maps; body may contain HTML), `category` (map, may be empty), `published_on` (`YYYY-MM-DD`), `is_active`, `sort_order`, `cover_image`, `images` |
| `GET /public/journal/{slug}` | One article, bound by **slug** (not uuid). Inactive or unknown: `404 not_found`. | as above |
| `GET /public/settings` | Global site copy. **Not paginated**: `data` is a flat `{group: {key: value}}` map (no `items`/`meta`). `value` is free-form JSON, usually a locale map; a missing group or key means "not configured". | `{ "contact": { "phone": {"en": "..."} }, ... }` |
| `GET /public/dining-venues/{uuid}/menu/download` | Link to the venue's menu file. `200 {url, file_name, mime_type, size, updated_at}`; `204` with an empty body and no envelope when there is no file; `404` for an unknown or inactive venue. Details under Restaurant details above. | `url`, `file_name`, `mime_type`, `size`, `updated_at` |

`GET /public/transfers` items are `{uuid, name (map), description (map or null), max_passengers (int or null), price_usd, is_active}`. `description` and `max_passengers` are optional on the hotel side, so treat `null` as "not stated"; show `max_passengers` as a capacity hint but do not enforce it client-side.

### GET /api/public/exchange-rates

**Purpose:** Display-only currency conversion rates for the SYP/TRY price labels.

**Who can call:** Public (no token needed; a guest token also works). Throttled 60/min. Sent with `Cache-Control: public, max-age=300`. Not paginated — a flat object like `/public/settings`.

**Response `data`:**
```json
{
  "base": "USD",
  "stale_after_hours": 168,
  "rates": [
    { "currency": "SYP", "rate": "13000.000000", "display_decimals": 0, "updated_at": "2026-10-04T08:00:00Z", "is_stale": false },
    { "currency": "TRY", "rate": null, "display_decimals": 2, "updated_at": null, "is_stale": true }
  ]
}
```

| Field | Notes |
|---|---|
| `rates[]` | One entry per configured currency, in config order (`SYP`, `TRY` today). |
| `rate` | Units of the currency per 1 USD, a **decimal string with 6 decimals** — parse as decimal, never float. `null` when no rate has been set yet; keep the app's built-in fallback. |
| `display_decimals` | Round the converted amount to this many decimals. |
| `updated_at` | ISO 8601 UTC, or `null` when no rate yet. |
| `is_stale` | `true` when there is no rate or it is older than `stale_after_hours`. |

**Conversion is display-only:** `usd × rate`, rounded to `display_decimals`. The server never charges or settles in SYP/TRY — every payment stays USD.

**App guidance:** fetch on launch and on the checkout screen, keep the last good copy, and show "rates as of {updated_at}" when `is_stale`.

---

## Module: Booking & Reservations

### GET /public/availability

**Purpose:** Is this room type free for these dates? Call before showing the "Select Room" button as enabled.

**Who can call:** Public (tier-1).

**Query params:** `room_type_uuid` (required, must exist), `check_in` (required, today or later), `check_out` (required, after `check_in`).

**Response `data`:**
```json
{ "room_type_uuid": "...", "check_in": "2026-09-05", "check_out": "2026-09-08", "available": true, "rooms_available": 3 }
```

**Failure `error_code`s:** `not_found` (404, unknown or inactive room type), `validation_failed` (422).

### GET /public/quote

**Purpose:** Price a stay before booking — base rate → seasonal/weekend rules → promo. This is what fills the price-details breakdown on the review screen.

**Who can call:** Public (tier-1).

**Query params:** `room_type_uuid`, `check_in`, `check_out` (required, same rules as availability), `promo_code` (optional).

**Response `data`:**
```json
{ "daily_rate_usd": 280, "nights": 2, "subtotal_usd": 560, "discount_usd": 56, "total_usd": 504, "promo_code_id": 4, "rules_applied": 0 }
```

`discount_usd` is `0` and `promo_code_id` `null` when no promo applies. `rules_applied` counts the seasonal/weekend pricing rules that moved the rate — `0` means the flat base rate was used. Taxes are **not** in this payload — apply your own display rate on `subtotal_usd` if the design shows a tax line.

**Failure `error_code`s:** `invalid_promo` (422, promo missing/expired/inactive), `not_found` (404), `validation_failed` (422).

### Two entry paths, one destination

- `POST /reservations` — **app-only, tier-2.** Guest already has a token; identity comes from the token, body contact fields are ignored. One step, no OTP.
- `POST /reservations/guest` + `POST /reservations/guest/verify` — **public, two-step.** The app **keeps** the token step 2 returns (the website discards it) — this is how a brand-new guest booking on the app becomes a logged-in session in one motion.

```
1. POST /reservations/guest          → soft-holds a room for 5 min, sends an OTP
   → { reservation_uuid, identifier_masked, channel }
2. POST /reservations/guest/verify   → activates the booking
   → { reservation, guest, token }   ← KEEP the token
```

**Dev/testing note:** no SMS/WhatsApp/email provider is wired yet. In local and testing environments the code is always `000000`.

### POST /reservations/guest

**Purpose:** Step 1 of the public path — submit booking details, get an OTP sent to the guest's contact.

**Who can call:** Public (tier-1).

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
`channel` is `sms` or `email`. The OTP TTL is a fixed 5 minutes and is not echoed — the room stays soft-held for exactly that long, then auto-releases if step 2 never completes.

**Failure `error_code`s:** `no_availability` (409, last room raced away), `invalid_promo` (422), `too_many_requests` (429, OTP rate limit — 1/min, 5/hr per contact), `validation_failed` (422, includes an `identity` key when neither phone nor email was given).

### POST /reservations/guest/verify

**Purpose:** Step 2 — verify the OTP, activate the booking, and receive the token that signs the guest in.

**Who can call:** Public (tier-1).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `reservation_uuid` | string | ✅ | From step 1 |
| `phone` | string | one of phone/email | Must match the contact used in step 1 |
| `email` | string | one of phone/email | |
| `otp_code` | string | ✅ | 6 digits |

**Response `data`:**
```json
{
  "reservation": {
    "uuid": "...", "booking_code": "CARL-7K2M9XQR", "status": "pending",
    "check_in": "2026-09-05", "check_out": "2026-09-08", "nights": 3,
    "source": "direct", "payment_method": "cash", "total_usd": "840.00", "hold_expires_at": null
  },
  "guest": { "uuid": "...", "name": "...", "phone": "+963...", "email": null, "preferred_locale": "en" },
  "token": "1|abcdef..."
}
```

**Store `token`** — the guest is now signed in and every tier-2 endpoint is open to them. No separate login step is needed after booking.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `not_found` | 404 | Reservation not in the right state, or the contact doesn't match step 1 — generic "Booking not found." |
| `otp_invalid` | 422 | "Incorrect code." Allow retry. |
| `otp_expired` | 422 | "Code expired." The hold is gone too — send the guest back to step 1. |
| `otp_locked` | 429 | Too many attempts — back to step 1. |
| `hold_expired` | 422 | The 5-minute window passed — back to step 1; the room may no longer be free. |

**Booking code format:** `CARL-` + 8 Crockford-Base32 characters (excludes `I`, `L`, `O`, `U` to avoid ambiguity), e.g. `CARL-7K2M9XQR`.

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
| `adults` | integer | optional | Party size, `1`-`20`, default `1`. `adults + children` must not exceed the room type's `max_occupancy` |
| `children` | integer | optional | `0`-`20`, default `0` |
| `loyalty_points` | integer | optional | Phase 10. Pay part of the booking with points: `1`–`100000000`. Needs the `Idempotency-Key` header and cannot be combined with `voucher_code`. See [Module: Loyalty](#module-loyalty) |
| `voucher_code` | string | optional | Phase 10. A voucher the guest owns (`LOY-…`), case and spaces ignored; max 16 characters. Needs the `Idempotency-Key` header and cannot be combined with `loyalty_points` |

**Header `Idempotency-Key`** (max 64) is **required only when `loyalty_points` or `voucher_code` is sent** (`422 validation_failed` with `errors.idempotency_key` otherwise). A booking without loyalty fields ignores the header. Never send a discount or total — the server prices the booking itself.

**Response `data`** (HTTP 201) — a Reservation object, status starts at `pending`:
```json
{
  "uuid": "...", "booking_code": "CARL-XXXXXXXX", "status": "pending",
  "check_in": "2026-07-20", "check_out": "2026-07-22", "nights": 2,
  "source": "direct", "payment_method": "cash", "total_usd": "270.00", "hold_expires_at": null,
  "adults": 1, "children": 0,
  "loyalty": null
}
```

`GET /public/availability` and `GET /public/quote` ignore party size, and `POST /reservations/guest` takes no party fields (always `1` / `0`). Bookings made before 2026-10-07 read `1` / `0` because the value was never collected.

`total_usd` is **net** of any promo and loyalty discount. `loyalty` (Phase 10, additive) is `null` for a booking that used no points or voucher, otherwise `{ "points_redeemed": 5000, "points_discount_usd": "50.00", "voucher": null, "voucher_discount_usd": "0.00", "upgrade_requested": false, "status": "applied" }` — `voucher` is `{ "code", "type" }` or `null`; `status` becomes `reversed` after the booking is cancelled. The same block is on `GET /reservations` and `GET /reservations/{uuid}`.

**Replay:** retrying with the same `Idempotency-Key` and the same inputs answers **`200`** with the same reservation and spends nothing twice (even if the last room has meanwhile been taken or the voucher is already marked used). The same key with any different input (party size included) answers `409 idempotency_conflict`.

**Failure `error_code`s:** `no_availability` (409 — nothing is spent), `occupancy_exceeded` (422 — `adults + children` is over the room type's `max_occupancy`; `context` is `{ "max_occupancy": 2, "requested": 3 }`; nothing is written), `invalid_promo` (422), `unauthorized` (401), `validation_failed` (422), and the loyalty codes `loyalty_insufficient_points`, `loyalty_below_minimum`, `loyalty_over_cap`, `loyalty_voucher_invalid`, `loyalty_discount_conflict`, `loyalty_program_inactive` (all 422, see [Module: Loyalty](#module-loyalty)). A refusal writes nothing: no reservation, no spent points, no used voucher.

---

### GET /api/reservations

**Purpose:** List the logged-in guest's own reservations.

**Query:** `per_page` is honoured (default 15; above 100 is clamped to 100, below 1 falls back to 15; never an error).

**Response:** paginated (`data.items` + `data.meta`), newest first, items are the Reservation shape above (including `adults` and `children`).

### GET /api/reservations/{uuid}

**Purpose:** View one of your own reservations.

**Failure:** `not_found` (404) if the reservation belongs to someone else — never `forbidden`, so ownership can't be probed.

### DELETE /api/reservations/{uuid}

**Purpose:** Cancel your own reservation.

**Cancellable from:** `pending_verification`, `pending`, `confirmed`. Anything past that (`checked_in`+) → `reservation_state` (422).

**Response:** HTTP 204, `data: null`.

**Loyalty (Phase 10):** a cancellation undoes every loyalty effect once, in the same transaction — points spent on the booking are refunded (a `refund` ledger row; back into the original batch if it is still valid, otherwise into a new batch with a full term), a used voucher becomes `active` again, and any points earned from this stay's folio are taken back (never below a zero balance). Cancelling twice is `422 reservation_state` and changes nothing.

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
| `bookable_uuid` | string | ✅ | uuid of the specific spa service/table/cabana/transfer — list them via `GET /public/spa-services`, `/public/pool-cabanas`, `/public/transfers`, `/public/dining-venues/{uuid}/tables` |
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
| `date` | string | ✅ | `Y-m-d`, today or later. Hotel-local; "today" is the hotel's day |
| `time` | string | ✅ | `H:i` (24h), hotel-local |
| `guest_count` | integer | ✅ | 1–20 |
| `special_request` | string | optional | Max 1000 — lands in the booking's `notes` |

The backend assigns the smallest table that seats the party and is free for a two-hour seating window. You never send a table uuid.

**Time zone:** `date` + `time` are in the hotel's local time (Asia/Damascus). The returned `scheduled_at` is the true UTC instant of that slot (19:00 Damascus becomes `16:00Z`); convert it to the device zone for display, or show the hotel-local slot you submitted.

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

**ID scan (GUEST-06):** the camera/ID-scan screen uploads its captured image through this same route — there is no separate ID-scan endpoint and no OCR. Set `documents[0][type]` to a label such as `id_card` (a **client convention**, not a server-enforced value — the server accepts any string up to 255 characters for `type`). The upload counts toward the pre-arrival checklist's `documents_uploaded` item the same as any other document, and staff review it in the same check-in approval screen. `SubmitDocumentsRequest` and this route are unchanged by Phase 4.

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

**`GET`** returns your own requests only, paginated, newest first. Optional `status` filter: `?status=new` or `?status[in]=new,in_progress`; values are `new`, `in_progress`, `completed`, `cancelled`. An unknown value answers `422 validation_failed` (`errors.status` or `errors["status.in"]`) rather than an empty list; any other query parameter is ignored.

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

### Quick-request chips (Phase 6)

The home screen's request chips are the `is_default` item of each **`kind: direct`** category from `GET /public/service-catalog`: **concierge, transport, maintenance, late_checkout, luggage**. `late_checkout` and `luggage` are new this phase — seeded unpriced (`price_usd: null`), so an un-granted request never lands on a folio. `late_checkout` routes to the reception department and `luggage` to concierge; both are free, and a granted late checkout does **not** change the stay's `check_out` date in this version (it completes as a note for the desk to act on).

A chip taps straight through to `POST /api/service-requests { "service_item_uuid": "<default_item_uuid>", "notes"? }` — the same route as every other catalog request. `default_item_uuid` is present on a `direct` category even though its `items` array is always empty, so the chip never needs a second call to find the item to post.

Do-not-disturb stays on its own toggle route, `PATCH /api/stays/active/dnd` — it is not a catalogue category and never creates a service request. `POST /api/transport-requests` still works but is legacy; prefer the `transport` chip above, which posts through the catalogue like every other request. Use the app's own default chip icon for any `icon` value the app does not recognize — the catalogue can grow `icon` values with no app release.

This phase adds no guest-facing route: the endpoint index above is unchanged.

---

## Module: Stays

Read projections over your reservations for the three stay screens. All three
reads are **tier-2** (`auth:guests` only) — having no active stay is an empty
state, not an error, so don't treat a `null`/`[]` payload as a failure.

### GET /api/stays/status

**Purpose:** The cheap entitlement probe — "is the bearer of this token in the hotel right now?" Poll it on app resume to decide whether to render the in-stay home or the browse home, without pulling the full profile or stay payload.

**Who can call:** Tier-2 (any guest token). Deliberately **not** behind the in-stay gate: "not checked in" is the answer this endpoint exists to give, so gating it would make `false` impossible to return.

**Response `data`:**
```json
{
  "has_booking": true,
  "is_checked_in": true,
  "reservation": {
    "uuid": "...", "booking_code": "CARL-DEMO0001", "status": "checked_in",
    "check_in": "2026-07-26", "check_out": "2026-07-29",
    "checked_in_at": "2026-07-26T12:00:00+00:00",
    "nights_remaining": 2,
    "room_number": "812"
  }
}
```

| Field | Notes |
|---|---|
| `has_booking` / `is_checked_in` | The same two flags `GET /auth/guest/me` returns, resolved from the same source the `has_booking` / `is_checked_in` middleware use — so the app can never disagree with the gate that will reject its next request. |
| `reservation` | The in-progress stay when there is one, otherwise the latest booking. **`null`** when the guest has no booking at all. |
| `checked_in_at` | `null` for a booking not yet arrived at, and for stays predating this column. |
| `room_number` | Assigned at check-in — `null` before then. |

Both flags are `false` with `reservation: null` for a guest who has only ever browsed. That is a success response, not an error.

**Failure `error_code`s:** `unauthenticated` (401).

**Phase 4 (D-12):** when `reservation` is present it also carries `online_check_in`, `digital_key` and `pre_arrival_checklist` — see the block below `GET /api/stays/active`. This response, like the other two stay reads, is sent with `Cache-Control: no-store, private` because it can carry the digital key.

### POST /api/stays/check-in

**Purpose:** Self check-in on the arrival day: the guest taps "Check in" and the app moves from the browse home to the in-stay home.

**Who can call:** Tier-2 (any guest token). No request body.

**Behavior:** checks the guest into their earliest `confirmed` reservation whose `check_in` is on or before the hotel-local today and whose `check_out` is after it. It is **arrival day only**: it does not open before the arrival day, and a `pending` booking (the hotel has not confirmed it yet) does not qualify. Hotel-local time follows `HOTEL_TIMEZONE` (default `Asia/Damascus`), not the phone's clock. Already checked in is idempotent: it answers `200` with the current active stay.

**Response `data`** (HTTP 200): the same shape as `GET /api/stays/active`, with `room_number` assigned.

**Failure `error_code`s:**

| Code | HTTP | Notes |
|---|---|---|
| `reservation_state` | 422 | Too early (before the arrival day), a booking still `pending`, no booking at all, or a stay whose `check_out` has passed. `message` is "Check-in opens on your arrival day, once the hotel has confirmed your booking." (localized), `context: null`. Show the server message; do not invent date logic in the app. |
| `no_availability` | 409 | No room could be assigned for the booked room type. |
| `room_already_assigned` | 409 | The reserved room is taken by another stay. |
| `room_out_of_order` | 422 | The reserved room is in maintenance. `context: { room_uuid, housekeeping_status }`. |
| `unauthenticated` | 401 | |

### POST /api/stays/{uuid}/online-check-in

**Purpose:** Submit the hotel-local arrival time ahead of a confirmed stay and open the pre-arrival approval, up to and including the arrival day.

**Who can call:** Tier-2 (any guest token) — ownership of the reservation is checked in the request, not by a stay-state gate.

**Request body:** `{ "arrival_time": "18:30" }` — required, `H:i` hotel-local wall time (`7:30` and `18:30:00` are both rejected).

**Behavior:** the reservation must be `confirmed`; hotel-today must be on or before the stay's `check_in` date. A resubmission overwrites the previous `arrival_time` and `online_check_in_submitted_at` — both calls return `200`. Opens a `pending` check-in approval if none exists yet; it never downgrades one that is already `approved` or `rejected`. Does **not** touch the digital key — issuance is a staff decision made on approval, not gated on online check-in.

**Response `data`** (HTTP 200): the reservation in the `GET /api/stays/upcoming` item shape (see below), message `"Online check-in submitted."`. `Cache-Control: no-store, private` — the payload can carry the digital key.

**Failure `error_code`s:**

| Code | HTTP | Notes |
|---|---|---|
| `forbidden` | 403 | The reservation belongs to another guest. |
| `reservation_state` | 422 | Not `confirmed`. `context: { status, allowed: ["confirmed"] }`. |
| `online_check_in_closed` | 422 | Hotel-today is after `check_in`. `context: { check_in, today }` (`Y-m-d`). Online check-in is available up to and including the arrival date, never after. |
| `validation_failed` | 422 | Bad `arrival_time` format. |
| `unauthenticated` | 401 | |

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
| `online_check_in` | `{ arrival_time, submitted_at, approval_status }` (Phase 4, D-12). `arrival_time` is `H:i` hotel-local, `null` before submission. |
| `digital_key` | `{ code, issued_at, expires_at }` while a key is active for this stay, else `null` (Phase 4, D-11). `code` looks like `XXXX-XXXX-XXXX`. **Display-only, NOT lock-grade — it opens no lock.** Never cache or log this field. Expires at `check_out` at the hotel's configured check-out time. |
| `pre_arrival_checklist` | Six-item derived checklist, same shape the staff profile shows — see `API_GUIDE_DASHBOARD.md` Module: Guests. |

This response carries `Cache-Control: no-store, private` because of `digital_key`.

### GET /api/stays/upcoming

`data` is an array (you may hold several future bookings), soonest first. `pending_verification` holds are excluded. `Cache-Control: no-store, private` on this response too.

| Field | Notes |
|---|---|
| `booking_code` | The "reservation code" |
| `room_number` | e.g. `"801"` — a specific room is reserved when the booking is made, so this is populated straight away |
| `room_name` | `{en, ar}` |
| `price_usd` | Reservation total |
| `check_in` / `check_out` / `nights` | |
| `is_cancellable` | Whether `DELETE /api/reservations/{uuid}` will succeed |
| `online_check_in` / `digital_key` / `pre_arrival_checklist` | Same three Phase 4 blocks as `GET /api/stays/active` above — see that row for shapes. `digital_key` is display-only and **NOT lock-grade**. |

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
  "uuid": "...", "status": "open",
  "subtotal_usd": "389.00", "total_usd": "389.00",
  "approved_by_guest_at": null, "settled_at": null,
  "items": [
    {
      "uuid": "...", "description": "Room charge", "amount_usd": "300.00", "source_type": "reservation",
      "quantity": 1, "unit_price_usd": null, "posted_by": null, "posted_at": null,
      "reason": null, "reverses_item_uuid": null, "dispute": null
    },
    {
      "uuid": "...", "description": "Pool Cabana", "amount_usd": "80.00", "source_type": "service_booking",
      "quantity": 1, "unit_price_usd": null, "posted_by": null, "posted_at": null,
      "reason": null, "reverses_item_uuid": null,
      "dispute": {
        "uuid": "...", "status": "open", "reason": "I did not book this.", "raised_by": "guest",
        "raised_at": "2026-09-26T11:00:00+00:00", "resolved_at": null, "resolution_note": null
      }
    },
    {
      "uuid": "...", "description": "Minibar", "amount_usd": "9.00", "source_type": "manual",
      "quantity": 2, "unit_price_usd": "4.50",
      "posted_by": { "uuid": "...", "name": "Front Desk" }, "posted_at": "2026-09-26T10:05:00+00:00",
      "reason": null, "reverses_item_uuid": null, "dispute": null
    }
  ],
  "payments": [
    { "uuid": "...", "method": "cash", "amount_usd": "100.00", "status": "completed", "note": null, "created_at": "..." }
  ],
  "paid_usd": "100.00",
  "balance_due_usd": "289.00",
  "open_disputes_count": 1
}
```
The folio is reconciled on every call until it's `settled`, after which it's frozen. Item `uuid`s stay the same between calls, and lines added by the front desk (`source_type` `manual` for a charge, `credit` for a negative correction), credited lines and disputed lines always stay on the bill. `status`: `open` or `settled`. Money fields are 2-decimal strings. `paid_usd` counts completed payments; `balance_due_usd` is `total_usd` minus `paid_usd`, and it is signed: a negative value means the hotel owes you (refunds are handled at the desk). `dispute` is the item's latest dispute or `null`; disputes never change amounts. `open_disputes_count` counts the items with an open dispute.

### POST /api/folio/approve

**Purpose:** Express checkout — approve your bill and finish your stay without going to the desk.

**Response `data`:** same Folio shape as above, now with `approved_by_guest_at` set. **This also transitions your reservation to `checked_out`.** It does not settle payment — that's still a front-desk/admin action (cash or already paid on arrival).

**Failure:** `no_active_reservation` (403), `reservation_state` (422, your most recent booking is not the checked-in stay).

### PATCH /api/folio/items/{uuid}/dispute

**Purpose:** Dispute a line on your bill ("I didn't order this").

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `reason` | string | ✅ | Max 500 |

**Response `data`** (HTTP 200, message "Dispute raised."): the item, in the item shape of `GET /api/folio`, with its new `dispute` (`status: "open"`, `raised_by: "guest"`).

**Behavior:** one open dispute per item; once the hotel decides, the item's `dispute.status` becomes `resolved` or `rejected` with a `resolution_note`, and you may dispute it again. Disputing never changes an amount; if the hotel agrees, it adds a credit line to your bill. Works on open and settled bills.

**Failure `error_code`s:** `not_found` (404: not your item, or no such item — someone else's item always answers 404, never 403), `no_active_reservation` (403), `folio_item_dispute_open` (422, `context: { item_uuid, dispute_uuid }`), `validation_failed` (422).

### POST /api/transport-requests

**Purpose:** Request an airport transfer / transport pickup — a thin wrapper over service requests.

**Request body:** `{ "notes"?: string }` (max 1000).

**Response `data`** (HTTP 201): a service-request object with `type: "transport"`, `department: "concierge"`.

---

## Module: Loyalty

Phase 10. Every route below is tier **G** (any guest token); a staff token or no token answers `401`. Nothing here takes a guest id — the guest is always the token's owner. All changes are additive; there is no breaking change.

**How it works**

- A guest earns **integer points once, when a folio is settled** (rounded half up on net spend, one stay amount and one service amount). Points expire on a rolling basis per earn batch and are spent **first-expiring-first (FIFO)** — the batch that expires earliest is used up first.
- Points buy **rewards** (a catalogue of vouchers) or pay **part of a booking** directly. Both need the program to be configured by the hotel; `program` in the account tells you which parts are on.
- **There are no tiers.** The mock's tier ladder has no API: no tier name, next tier, progress or member id exists.
- Everything is USD. `Accept-Language` localizes `message`, `label`, `source_label` and `type_label`; reward and voucher names are whole `{en, ar, …}` maps.

### GET /api/loyalty/account

**Purpose:** The balance screen.

```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "available_points": 12000,
    "expiring_soon_points": 0,
    "expiring_soon_window_days": 30,
    "next_expiry_at": "2028-10-05T20:59:59+00:00",
    "lifetime_earned_points": 20000,
    "lifetime_redeemed_points": 7000,
    "program": { "earning": true, "points_discount": true, "rewards": true },
    "redeem_value_usd": "0.0100",
    "min_redeem_points": 100,
    "max_redeem_percent": "50.00"
  }
}
```

| Field | Notes |
|---|---|
| `available_points` | Spendable now: active batches whose expiry is still in the future. A batch that expired a moment ago is excluded even before the nightly job runs. `0` for a guest with no points. |
| `expiring_soon_points`, `expiring_soon_window_days` | Points expiring within the window (the hotel's warning period, default 30 days). |
| `next_expiry_at` | When the next batch expires, ISO 8601 UTC; `null` when there is no balance. |
| `lifetime_earned_points` | Earned + positive staff adjustments − clawbacks, floored at 0. |
| `lifetime_redeemed_points` | Redeemed − refunded (cancelled bookings), floored at 0. Expiry counts toward neither. |
| `program` | Three **independent** switches: `earning` (hotel set an earn rate), `points_discount` (hotel set a point value **and** a booking cap — paying part of a booking with points), `rewards` (always `true`). Hide a feature whose switch is `false`. |
| `redeem_value_usd` | USD value of one point (decimal string) or `null` when not set. |
| `min_redeem_points` | Smallest free-form points payment on a booking; `1` when the hotel set none. Does **not** apply to catalogue rewards. |
| `max_redeem_percent` | Largest share of a booking payable with points (`"50.00"` = 50%) or `null` when points payment is off. It is never treated as 100%. |

An unconfigured program still answers `200`: `program` is `{ "earning": false, "points_discount": false, "rewards": true }` and `redeem_value_usd` / `max_redeem_percent` are `null`.

### GET /api/loyalty/ledger

**Purpose:** The points history. Paginated (`data.items` + `data.meta`), newest first. Query: `type` (`eq`/`in`), `source` (`eq`/`in`), `occurred_at` (`gte`/`lte`), `points` (`gte`/`lte`, must be an integer — `?points[gte]=abc` is `422 validation_failed`), `per_page`.

```json
{
  "uuid": "cd327c8d-f4db-4350-a58d-0a4abecc4828",
  "type": "redeem",
  "label": "Points redeemed",
  "source": null,
  "source_label": null,
  "points": -5000,
  "shortfall_points": 0,
  "discount_usd": "50.00",
  "occurred_at": "2026-10-05T12:11:21+00:00",
  "expires_at": null,
  "reservation": { "uuid": "e9a80460-3be2-4cee-ba06-f58944d335d0", "booking_code": "CARL-QKSK8WZA" },
  "folio": null,
  "voucher": null
}
```

**Contract strings** (stable — branch on these, never on `label`):

| Field | Values |
|---|---|
| `type` | `earn` \| `redeem` \| `expire` \| `adjust` \| `clawback` \| `refund` |
| `source` | `stay` \| `service` \| `manual` \| `refund`, or `null` (redeem / expire / clawback rows have no source) |

- `points` is **signed**: credits positive (`earn`, positive `adjust`, `refund`), debits negative (`redeem`, `expire`, `clawback`, negative `adjust`).
- `reservation.booking_code` ties a row to a stay or booking; it is `null` for manual adjustments and catalogue redemptions. `voucher` is set on a catalogue redemption.
- `expires_at` is the expiry of the batch **this row created** (earn, positive adjust, a refund that needed a fresh batch); `null` otherwise.
- `shortfall_points` is non-zero only on a `clawback` row, when a cancelled stay's earned points had already been spent.
- Staff notes — the adjustment `reason` and who made it — are **never** sent to guests.

### GET /api/loyalty/rewards

**Purpose:** The catalogue. Paginated; only active rewards, ordered by `sort_order` then creation. Works even before the hotel configures the program.

```json
{
  "uuid": "7c2838e5-12a1-4597-ab1c-8d353daf840d",
  "name": { "en": "25 USD off", "ar": "خصم 25 دولارًا" },
  "description": { "en": "Applies to one booking", "ar": "ينطبق على حجز واحد" },
  "type": "discount_voucher",
  "type_label": "Discount voucher",
  "points_cost": 2000,
  "discount_usd": "25.00",
  "voucher_valid_days": 90,
  "is_active": true,
  "sort_order": 1,
  "created_at": "2026-10-05T12:10:25.000000Z",
  "updated_at": "2026-10-05T12:10:25.000000Z",
  "deleted_at": null
}
```

`type` is `discount_voucher` \| `free_night` \| `room_upgrade`. `discount_usd` is set for `discount_voucher` only (`null` for the other two). `description` may be `null`.

### POST /api/loyalty/rewards/{uuid}/redeem

**Purpose:** Spend points on a reward and receive a voucher.

**Headers:** `Idempotency-Key` (required, max 64; generate one UUID per tap and reuse it for retries). **Body:** none. **Throttle:** 30 requests per minute.

**Response `data`** — HTTP **201** the first time (message `"Reward redeemed. Your voucher is ready."`):
```json
{
  "uuid": "1f42ad1a-965c-4527-9111-6395e8bb90ac",
  "code": "LOY-T9B13P8W",
  "type": "discount_voucher",
  "type_label": "Discount voucher",
  "reward_name": { "en": "25 USD off", "ar": "خصم 25 دولارًا" },
  "value_usd": "25.00",
  "points_spent": 2000,
  "status": "active",
  "expires_at": "2027-01-03T20:59:59+00:00",
  "used_at": null,
  "reservation": null
}
```

- **Replay:** the same key for the same reward answers **`200`** with the **same voucher** and spends nothing more. The same key for a **different reward** answers **`409 idempotency_conflict`**. A missing or blank key is `422 validation_failed` with `errors.idempotency_key`.
- Points are taken FIFO in one transaction; the voucher snapshots the reward's type, name and value, so later catalogue edits never change a voucher you hold. `value_usd` is `null` for `free_night` and `room_upgrade`.
- The voucher expires at the **end of the hotel's local day**, `voucher_valid_days` days from today.
- A catalogue redemption ignores the booking minimum and cap and needs no program settings.
- **Failure `error_code`s:** `loyalty_insufficient_points` (422, `context: { available_points, requested_points }`), `loyalty_reward_unavailable` (422, the reward was deactivated), `not_found` (404, unknown or deleted reward), `idempotency_conflict` (409), `validation_failed` (422), `too_many_requests` (429).

### GET /api/loyalty/vouchers

**Purpose:** The guest's own vouchers, newest first, paginated. Query: `status` (`eq`/`in`), `type` (`eq`/`in`), `points_spent` (`gte`/`lte`, integer), `sort` ∈ `created_at|expires_at`. Item shape = the redeem response above; after use, `status` is `used`, `used_at` is set and `reservation` is `{ "uuid", "booking_code" }`.

**Voucher lifecycle:** `active` → `used` when applied to a booking → **back to `active`** when that booking is cancelled (the original expiry is kept; if it has passed, the voucher gets a short grace period). `active` → `expired` after `expires_at` (swept nightly). `void` means the voucher was closed because the account was deleted; treat any status you don't know as not usable.

**What a voucher is worth on a booking:** `discount_voucher` takes `value_usd` off (never more than the booking total); `free_night` takes off one night at the booking's daily rate (capped at the total); `room_upgrade` takes **0.00** off — the reservation shows `loyalty.upgrade_requested: true` and **staff perform the upgrade** at the desk. A booking can use **one voucher, or points, never both**.

### GET /api/loyalty/preview

**Purpose:** Price a booking with points or a voucher **before** committing. Call it as the guest edits the points field; it changes nothing.

**Query** (the same inputs as the booking): `room_type_uuid`, `check_in` (today or later), `check_out`, optional `promo_code`, and at most one of `loyalty_points` (integer 1–100000000) or `voucher_code`. **Throttle:** 30 requests per minute. Any other key (a discount, a total) is ignored.

```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "quote": { "nights": 2, "daily_rate_usd": "150.00", "subtotal_usd": "300.00", "promo_discount_usd": "0.00", "total_usd": "300.00" },
    "loyalty": { "points_redeemed": 5000, "points_discount_usd": "50.00", "voucher": null, "voucher_discount_usd": "0.00", "upgrade_requested": false },
    "net_total_usd": "250.00",
    "available_points": 17000,
    "max_points": 15000,
    "points_earnable_estimate": 250,
    "program": { "earning": true, "points_discount": true, "rewards": true }
  }
}
```

| Field | Notes |
|---|---|
| `quote` | The ordinary price quote (after any promo). The points cap is measured against `quote.total_usd`. |
| `loyalty` | What the sent points / voucher would take off; `voucher` is `{ code, type }` or `null`. All zero/`null` when neither was sent. |
| `net_total_usd` | The total the booking will actually carry; never below `0.00`. **`POST /reservations` with the same inputs produces exactly this total.** |
| `max_points` | The most points the guest can use on this booking: the smaller of their balance and what the cap allows. `null` when `program.points_discount` is `false`. |
| `points_earnable_estimate` | Points the stay would earn on `net_total_usd`; `null` when `program.earning` is `false`. Points paid for with points or a voucher do not earn. |

**Failure `error_code`s** (checked in this order): `loyalty_discount_conflict` (points and voucher together), `loyalty_program_inactive` (`context.capability: "points_discount"` — points sent while paying with points is off), `loyalty_below_minimum` (`context: { min_redeem_points }`), `loyalty_over_cap` (`context: { max_points }` — limited by the cap, not the balance), `loyalty_insufficient_points` (`context: { available_points, requested_points }`), `loyalty_voucher_invalid` (no context — unknown, someone else's, used, void and expired are deliberately indistinguishable), `validation_failed`.

### Booking with points or a voucher

1. `GET /loyalty/preview` with the booking inputs (and the points or voucher the guest chose) to show the discount and the net total.
2. `POST /reservations` with the **same inputs** plus `loyalty_points` **or** `voucher_code` and a fresh `Idempotency-Key` (see [POST /api/reservations](#post-apireservations)). The response carries `total_usd` (= the preview's `net_total_usd`) and the `loyalty` block.
3. If the request times out, retry with the **same key and same body**: you get `200` and the same reservation, never a second charge of points. A different body under the same key is `409 idempotency_conflict`.
4. Cancelling the booking (`DELETE /reservations/{uuid}`) refunds the points / restores the voucher. Check `GET /loyalty/account` afterwards.

Bookings made through the website or by staff never apply loyalty.

### Notification: points about to expire

The hotel runs a daily job that warns a guest once per batch before it expires. You receive one push per run (all batches in the window combined): type `loyalty_points_expiring`, title "Your points expire soon", body "1200 points expire on 2027-01-03. Use them before they go." (localized from the guest's `preferred_locale`), with `data: { "points": 1200, "expires_at": "2027-01-03T20:59:59+00:00" }` — `expires_at` is the earliest expiry among the warned batches; the date in the body text is the hotel-local date. `data` carries only those two keys. Open the Loyalty screen on tap.

### Error codes (this module)

| `error_code` | HTTP | When | `context` |
|---|---|---|---|
| `loyalty_program_inactive` | 422 | Points sent while the hotel has not set a point value and cap | `{ capability }` |
| `loyalty_insufficient_points` | 422 | Not enough unexpired points (redeem, preview, booking) | `{ available_points, requested_points }` |
| `loyalty_below_minimum` | 422 | Fewer points than `min_redeem_points` | `{ min_redeem_points }` |
| `loyalty_over_cap` | 422 | More points than `max_redeem_percent` of the booking allows | `{ max_points }` |
| `loyalty_voucher_invalid` | 422 | Bad / foreign / used / void / expired code | none |
| `loyalty_reward_unavailable` | 422 | Reward deactivated | none |
| `loyalty_adjustment_invalid` | 422 | Staff-side only (zero or over-limit adjustment); the app does not receive it | `{ max_adjust_points }` |
| `loyalty_discount_conflict` | 422 | Points and a voucher on one booking | none |

Plus the reused `idempotency_conflict` (409), `validation_failed` (422), `no_availability` (409) and `reservation_state` (422). The eight `loyalty_*` codes are stable contracts. Error details are always under `context`.

### Flutter mapping note (replacing the mock in `loyalty.dart`)

The mock's shapes do not map one to one; use this table when swapping the repository:

| Mock (`loyalty.dart` / `loyalty_controller.dart`) | API |
|---|---|
| `balance` | `available_points` |
| `earnedTotal` / `redeemedTotal` | `lifetime_earned_points` / `lifetime_redeemed_points` |
| `tierLabel`, `nextTierLabel`, `pointsToNextTier`, `tierProgress`, `memberId` | **Not provided.** There are no tier fields (no tiers exist); drop the tier card or hide it. `memberId` has no equivalent either. |
| `staysCount` | **Not provided.** Do not compute it from the ledger; remove the stat or source it elsewhere (e.g. `GET /stays/past`). |
| `LoyaltyEntryKind.earned` / `redeemed` / `expired` | `type` `earn` / `redeem` / `expire` — plus three kinds the mock lacks: `adjust` (staff correction, either sign), `clawback` (points taken back after a cancelled stay) and `refund` (points returned after a cancelled booking). Style by the sign of `points` for anything unknown. |
| `LoyaltyEntrySource.stay` | `source: stay` |
| `LoyaltyEntrySource.dining` / `spa` / `other` | **Collapse to `service`.** The API does not say which service earned the points. `manual` (staff adjustment) and `refund` are new. |
| `title` | `label` (localized) |
| `bookingRef` | `reservation.booking_code`; **optional** — `null` on manual adjustments and catalogue redemptions |
| `date` | `occurred_at` |
| `points` | `points` (already signed; the mock stored an unsigned amount plus a kind) |

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

**Message shape:** `{ "uuid", "conversation_uuid", "sender_type": "guest" | "staff", "body", "attachment_url", "created_at" }`. `conversation_uuid` is on every message, including the one `POST /api/conversations` returns, so the app can open `GET /api/conversations/{uuid}/messages` right after the first send without listing conversations first.

Live delivery mirrors to Firestore (`chats` collection, one doc per message keyed by `uuid`, filter by `conversation_uuid`) — subscribe there for real-time updates instead of polling; MySQL via the endpoints above remains the source of truth for history/pagination.

**Push triggers already wired:** a loyalty expiry warning (`loyalty_points_expiring`, Phase 10 — see [Module: Loyalty](#module-loyalty)), a welcome notification on first-ever device registration, a "room ready" push when staff check you in (and again if you are moved to another room during your stay), and, since Phase 4, a "check-in approved" push (`notifications.check_in_approved`: title "Your check-in is approved", body "Your digital key is ready in the app.") when staff approve the pre-arrival check-in — **the push never contains the key code**, only the notice that one is ready; fetch `GET /api/stays/active` or `/status` for the actual value. Order-status and ticket-reply pushes land once P10's operations queue grows a status-change action (not yet built) and P11 ships the chatbot.

---

## Module: Event Inquiry (RFP)

### POST /api/event-inquiries

**Purpose:** Submit a wedding/conference/corporate-event inquiry. Fire-and-forget — there is no confirmation flow, the inquiry just routes to the right department.

**Who can call:** Public (tier-1). If a guest token *is* attached the inquiry is silently linked to that guest record; the response is identical either way, so send the token when you have one.

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

## Coming in P11 — AI chatbot

`POST /chatbot/message` — public (tier-1) or authenticated.
