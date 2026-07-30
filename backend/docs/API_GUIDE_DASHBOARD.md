# Carlton Hotel — API Guide: Staff Dashboard

> **Audience:** frontend developers building the staff/admin dashboard.
> **Surface:** Staff-only, permission-gated. All requests require a staff bearer token obtained via `POST /auth/login`. Permissions control which sections are accessible — read the `permissions` array from the login response to decide what to render.
> **Try it now:** `php artisan migrate:fresh --seed` populates realistic demo data (staff, guests, bookings, tickets, everything), and `docs/postman/` has a ready-to-import Postman collection + environment pre-loaded with working tokens. See `docs/postman/README.md`.
> **Seeded logins — development only, never referenced from application code.** All seeded staff share the password `password`. `super@carlton.demo` is the super admin (bypasses every permission check, so testing only as this account proves nothing about gating); `content@carlton.demo` holds `content_editor` and is the account to develop CMS screens against. Also seeded: `reception@`, `kitchen@`, `housekeeping@`, `concierge@`, `events@`, plus `newstaff@` and `trainee@` with no role at all — useful for verifying that your permission gating actually denies. None of these accounts exist in a database that was not seeded.

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
| `Accept-Language` | Always | Any configured CMS locale — currently `en`, `ar`, `fr`, `tr`, `es`. Controls only `message`/error/validation strings. Full browser headers (`fr-FR,fr;q=0.9,en;q=0.8`) are parsed and negotiated. |
| `Content-Type` | Requests with a body | `application/json` |
| `Authorization` | All authenticated requests | `Bearer <staff-token>` |

**`Accept-Language` does NOT localize content fields.** Translatable CMS content (room names, menu items, page bodies, etc.) is always returned as a locale-keyed object — `{ "en": "...", "ar": "...", "fr": "..." }` — and the header only picks the language of the envelope's `message` and validation error strings. The dashboard is responsible for showing/editing every locale itself.

**The locale set is `config/cms.php`, not a literal.** Currently `locales` is `en, ar, fr, tr, es` and `required_locales` is `en, ar`; both are overridable per environment via `CMS_LOCALES` / `CMS_REQUIRED_LOCALES`. Consequences for the dashboard:

- On **create**, `en` and `ar` are required for every mandatory translatable field; `fr`, `tr` and `es` are nullable and may be back-filled later.
- On **update**, required locales are `sometimes|required` — omit the field to leave the stored translation alone, but you cannot blank it with `""`.
- A **read** map contains only the locales that actually hold non-empty content. `name.tr` being absent is normal, not corrupt — fall back (usually to `en`) and treat a missing key in an editor as "needs translating".
- A **write** merges per locale: `{"name":{"fr":"…"}}` sets `fr` and leaves `en`/`ar` untouched. Sending `{"name":{}}` wipes the whole field.
- No endpoint publishes the locale list, so the dashboard has to carry it. Re-check `config/cms.php` when the CMS gains a language.

Two exceptions accept **`en` and `ar` only**, regardless of config: the menu module (`/cms/menu-categories`, `/cms/menu-items`) and the P7 service catalog. Extra locale keys are dropped by validation without a warning. See *Module: CMS Content*.

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

### Index query parameters

Every index endpoint (CMS admin and public alike) accepts:

| Param | Meaning |
|---|---|
| `page` | 1-based page number. |
| `per_page` | Page size. Default `15`, hard cap `100`. Values above the cap are clamped; `0`, negatives and non-numeric values fall back to the default. Never an error. |

Because `per_page` is clamped rather than echoed, always read the effective page size from `data.meta.per_page`. Two endpoints ignore the parameter entirely and are hard-wired to 15: `GET /cms/reviews` and `GET /api/public/dining-venues/{uuid}/menu`.

CMS admin index endpoints (`/api/cms/*`) additionally accept:

| Param | Meaning |
|---|---|
| `is_active` | `true` or `false` (also accepted: `1`/`0`, `yes`/`no`, `on`/`off`, any case). Omit **or send empty** (`?is_active=`) to see both published and draft rows. Any other value is a `422`. |
| `search` | Case-insensitive substring match across the entity's name/title in **every** configured locale (`config/cms.php` → `locales`), plus plain columns like `slug`, `code`, `number`. `%` and `_` are matched literally, not as wildcards. |
| `sort` / `sort_dir` | `sort_dir` is `asc` (default) or `desc`. Allowed `sort` columns are per-entity — commonly `sort_order`, `created_at`, `updated_at`; entities without a `sort_order` column do not accept it. An unknown column is ignored and the endpoint's natural ordering is kept. |

Filters also accept an explicit-operator form — `?is_active[eq]=false`,
`?capacity[gte]=50`, `?status[in]=clean,dirty`, `?status[]=clean&status[]=dirty`
(repeated params are the same as `in`). `?field=value` is shorthand for
`?field[eq]=value`. Operators are `eq`, `like`, `gte`, `lte`, `in`, and each
entity whitelists which ones each column allows.

**How unusable input is treated** — three different rules, on purpose:

| Input | Result |
|---|---|
| Unknown column or operator (`?icon=safe`, `?is_active[gte]=1`) | Silently dropped, `200`. An older dashboard build never breaks a list screen. |
| Empty value (`?is_active=`, `?capacity[gte]=`, `?status[in]=`) | **No filter applied.** This is the "Status: All" option of a `<select>`; it does not mean `is_active = false`. |
| Value the column cannot interpret (`?is_active=trve`, `?capacity[gte]=abc`, `?name[like][]=x`) | **`422`** with `error_code: "validation_failed"` and an `errors` entry keyed by the param (`is_active`, or `capacity.gte` for the operator form). A typo is never silently answered as if it were a valid query. |



**Public endpoints (`/api/public/*`) take `page` and `per_page` only** — they
always return `is_active = true` rows in their fixed order. The one exception is
`GET /api/public/dining-venues/{uuid}/menu`, which also reads
`?type=<menu-category-slug>`.

Two CMS reads sit outside this filter layer and follow their own rules:
`GET /cms/reviews` (an `is_published` parameter where an **empty** value means
*unpublished*, not "no filter") and `GET /cms/settings` (not paginated at all).
Both are documented under *Module: CMS Content*.

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
  "message": "The given data was invalid.",
  "error_code": "validation_failed",
  "errors": { "field_name": ["message"], "name.ar": ["The name.ar field is required."] },
  "request_id": "uuid"
}
```

`errors` is keyed by **field path**, not field name. Translatable fields validate per locale, so the key is `"{field}.{locale}"` — `name.en`, `name.ar`, `name.fr` — and there is **never a bare `name` key**, because no request declares a top-level rule for a translatable field. A form with one input per locale must map `errors["name.ar"]` onto the Arabic input; keying the lookup on the field name alone shows nothing. The same convention covers array rows (`amenities.0.uuid`, `settings.3.key`) and rejected filter values (`is_active`, or `capacity.gte` for the operator form). Message *text* is localized by `Accept-Language`; the *keys* are always the English field path.

Branch on `error_code`, never on `message` — `message` is localized and free to be reworded. Treat an unrecognised `error_code` as a generic failure rather than crashing.

Log `request_id` on every response for support tracing. It is also returned as the `X-Request-Id` response header on every request.

## Permission model

After login, the `permissions` array in the user object is the source of truth for what the logged-in staff member can do. Use it to show/hide sections. The server enforces all permission gates server-side — hiding UI is convenience only.

A `super_admin` account bypasses all permission checks on the server.

**Full permission catalog** (seeded since P0, 8 modules): `reservations.view|create|cancel`, `folios.view|settle`, `cms.view|edit`, `service_requests.view|assign|update`, `tickets.view|assign|respond`, `pricing.edit`, `reports.view`, `staff.manage`.

### `cms.view` is enforced — gate read-only navigation on it

CMS routes are split by verb. Every **read** (`GET` on a collection and on `/{uuid}`) is gated on `cms.view|cms.edit`; every **write** (`POST`, `PUT`, `PATCH`, `DELETE`) is gated on `cms.edit`. Spatie resolves a pipe-separated list through `canAny()` — ANY, not ALL — so `cms.edit` implies read access:

| Account holds | CMS reads | CMS writes |
|---|---|---|
| `cms.edit` only | ✅ | ✅ |
| `cms.view` only | ✅ | ❌ `403` |
| neither | ❌ `403` | ❌ `403` |

So a `cms.view`-only account is a genuine read-only reviewer, and an editor never needs both rows. **Show a read-only CMS section when the account holds `cms.view` *or* `cms.edit`; show the create/edit/delete controls only for `cms.edit`.**

The split is structural, so it also holds for content types added after this revision — gate on the rule above rather than on a route list. At the time of writing that was 48 routes gated `cms.view|cms.edit` and 95 gated `cms.edit`, but each new content type adds several more; run `php artisan route:list --path=api/cms -v` for the live figure rather than trusting a number in a document.

> Earlier revisions of this guide said `cms.view` was reserved and ungated. That was true up to commit `2282314` and is wrong now.

### Where each permission is enforced

Most are route middleware, which is the norm. Two families are not, and are enforced just as strictly:

- **`staff.manage`** — checked by `StaffPolicy` (registered on the `User` model), not by middleware. It gates all six `/staff` routes plus `GET /api/permissions` and `GET /api/roles`. A `403` from those endpoints means `staff.manage` is missing, even though the route carries no `permission:` middleware.
- **`service_requests.assign` / `service_requests.update` / `tickets.assign` / `tickets.respond`** — checked inside `OperationsQueueService`, which derives the required permission from the `{type}` segment of the URL because it differs per type. See *Module: Operations Queue & Dashboard*.

### Genuinely inert — do not build UI against these

`pricing.edit` and `reports.view` are seeded, appear in `GET /api/permissions`, and are enforced **nowhere** — no route middleware, no policy, no service check. Granting either currently permits nothing and withholding either currently blocks nothing. `reports.view` becomes real when P12 ships its report endpoints; `pricing.edit` has no endpoint planned yet. Every other permission in the catalog is enforced somewhere.

**Role presets** (6): `reception`, `kitchen`, `housekeeping`, `concierge`, `events`, `content_editor` — see Module: Reference Data below for exactly which permissions each preset grants. `content_editor` is the only preset that grants `cms.*`; without it no seeded account except the super admin can reach `/api/cms/*`.

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

`email` must be a valid email address; `password` is any non-empty string.

**Response `data`:**
```json
{
  "user": {
    "uuid": "...",
    "name": "John Smith",
    "email": "john@carlton.com",
    "type": "staff",
    "is_active": true,
    "is_super_admin": false,
    "roles": ["reception"],
    "permissions": ["reservations.view", "reservations.create", "folios.view", "folios.settle"]
  },
  "token": "1|abcdef...",
  "permissions": ["reservations.view", "reservations.create", "folios.view", "folios.settle"]
}
```

`type` is either `staff` or `super_admin`. `data.permissions` and `data.user.permissions` are the same list — either is fine to store. The array lists every permission the account *effectively* holds (role preset + direct grants − direct revokes), and for a `super_admin` it is filled with the **complete** permission catalog even though that account holds zero permission rows, so a plain name check already succeeds for them. Branch on `is_super_admin` only when you want to label the account or unlock a danger zone; never compute permissions from `roles`, since a super admin has no role.

**Failure `error_code`s:**

| Code | HTTP | Message key behind it | UI action |
|---|---|---|---|
| `unauthorized` | 401 | `credentials_invalid` — "The provided credentials are incorrect." | "Invalid credentials." The API cannot distinguish wrong email from wrong password, so do not imply it can. |
| `forbidden` | 403 | `account_inactive` — "This account has been deactivated." | "Account disabled. Contact your administrator." |
| `validation_failed` | 422 | | Missing or malformed fields. |

> Earlier revisions of this table listed `credentials_invalid` and `account_inactive` as the `error_code`s. Those are the **translation keys behind `message`**, not codes: `AuthStaffService` throws `UnauthorizedException` / `ForbiddenException`, whose `errorCode()` values are `unauthorized` and `forbidden`. A client branching on `credentials_invalid` will never match. Distinguish the two cases by HTTP status (401 vs 403).

**Not rate-limited.** Unlike the guest OTP route, `POST /api/auth/login` carries no `throttle` middleware. Do not auto-retry on `401`.

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
  { "name": "events", "permissions": ["service_requests.view", "tickets.view", "tickets.assign", "tickets.respond"] },
  { "name": "content_editor", "permissions": ["cms.view", "cms.edit"] }
]
```

6 presets. `content_editor` is the CMS persona — the seeded account
`content@carlton.demo` holds it, and it is the only non-super-admin login that can
reach `/api/cms/*`.

---

## Module: CMS Content (reads `cms.view|cms.edit` · writes `cms.edit`)

Admin CRUD for the **19 CMS modules**, plus the 6 P7 service-catalog resources that sit behind the same gates. Most modules follow one shape: `GET`/`POST` on the collection, `GET`/`PUT`/`DELETE` on `/{uuid}`. All under `auth:users`; the `GET`s are gated on `permission:cms.view|cms.edit` and `POST`/`PUT`/`PATCH`/`DELETE` on `permission:cms.edit` — see [Permission model](#permission-model).

Route binding is by **`uuid`** everywhere under `/cms/*`, never by slug or numeric id — including Pages and Journal posts, whose *public* routes use `slug`.

| Module | Base path | Media | Notes |
|---|---|---|---|
| Room types | `/cms/room-types` | ✅ | Amenity pivot; delete cascades to rooms |
| Rooms | `/cms/rooms` | ✅ | Not translatable; no `sort_order` |
| Facilities | `/cms/facilities` | ✅ | |
| Dining venues | `/cms/dining-venues` | ✅ | Delete cascades to menu categories → items |
| Menu categories | `/cms/menu-categories` | — | `en`/`ar` only; `PUT`/`PATCH` needs the full payload |
| Menu items | `/cms/menu-items` | ✅ | `en`/`ar` only; full payload on update; no `sort_order` |
| Event spaces | `/cms/event-spaces` | ✅ | `amenities` is translatable free text here |
| Amenities | `/cms/amenities` | — | The vocabulary room types attach to |
| Home sliders | `/cms/home-sliders` | ✅ | Exposes `photo`, no `images` array |
| Promotions | `/cms/promotions` | ✅ | |
| Pages | `/cms/pages` | — | No `images` key at all |
| Reviews | `/cms/reviews` | — | **Read + publish only** — see below |
| Testimonials | `/cms/testimonials` | ✅ | |
| FAQs | `/cms/faqs` | — | |
| Experiences | `/cms/experiences` | ✅ | |
| Gallery categories | `/cms/gallery-categories` | — | Delete cascades to its photographs |
| Gallery items | `/cms/gallery-items` | ✅ | The photograph arrives via the media route |
| Journal posts | `/cms/journal-posts` | ✅ | `published_on` is a display date, not a schedule |
| Site settings | `/cms/settings` | — | **`GET` + bulk `PUT` only** — see below |

P7 service catalog, identical gates, full `apiResource` each (`PUT` **or** `PATCH`, full payload required on update, `en`/`ar` only): `/cms/spa-services`, `/cms/pool-cabanas`, `/cms/transfers`, `/cms/restaurant-tables`, `/cms/service-categories`, `/cms/service-items`. Only `/cms/menu-items` in that group takes media.

Response shapes are identical to the public read shapes — the same Resource class serves both admin and public routes, so only the row *selection* differs. `store` returns HTTP 201, `destroy` returns HTTP 204 with `data: null`.

> **There are no soft deletes anywhere in the CMS.** `DELETE` is permanent, and several relations cascade (room type → rooms, dining venue → menu categories → menu items, gallery category → items, menu category → items). Deleting a parent also leaves its media rows and files behind — nothing cleans them up. Confirm destructively and name what else disappears.

### Publishing

`is_active` is the **only** publishing mechanism. There is no draft state, no preview token, no revision history, no `published_at`, and no scheduler.

Its database default is **`true`** on every content table, and the create requests do not force the field — so a `POST` that omits `is_active` publishes immediately. A "save as draft" control must send `is_active: false` explicitly.

`journal_posts.published_on` and `promotions.valid_from`/`valid_until` are **display metadata only** — they order and label content, they never gate visibility. A future-dated active journal post and an expired active promotion are both live right now.

On the CMS content modules `is_active` and `sort_order` are validated as bare `['boolean']` / `['integer','min:0']`: **omit the key to leave the value alone; sending `null` is a `422`.** (The menu module and the P7 catalog use `['nullable', …]` and do accept `null` — an inconsistency, so code for the strict case.)

### Create/update field reference

`{loc}` = translatable, submitted and returned as a locale-keyed object. **req** = required in `en`+`ar` on create; **opt** = nullable in every locale.

**Room type** — `name` `{loc}` req (max 255), `description` `{loc}` req, `amenities` (optional array of `{ uuid (must exist), is_highlight (bool), sort_order (int ≥0) }`), `view_type` (nullable: `city|garden|pool|courtyard|mountain|interior`), `bed_types` (nullable array of `king|queen|double|twin|single|extra`), `base_occupancy` + `max_occupancy` (required on create, int 1–20, `max_occupancy >= base_occupancy`), `size_sqm` (nullable numeric ≥1), `base_price_usd` (required numeric ≥0), `cancellation_hours` (int 0–8760), `is_active`, `sort_order`.
`amenities` is a pivot **sync**: send the array to replace the whole set, omit the key to leave it untouched, send `[]`/`null` to detach all; a `uuid` that does not resolve is skipped silently, and a row's `sort_order` defaults to its array index. Read side returns `amenities` as amenity objects plus `highlights` (the `is_highlight` subset) — **not** an array of strings.
*Known gap:* `UpdateRoomTypeRequest` drops `gte:base_occupancy`, so a `PUT` will accept `max_occupancy` below `base_occupancy`. Validate client-side.

**Room** — `room_type_uuid` (**not** `room_type_id` — required on create, must exist), `number` (required on create, max 10, unique), `floor` (nullable int 0–200), `status` (`available|occupied|maintenance`), `is_active`. No `sort_order`. The nested `room_type` in the response omits `images`/`banner`/`amenities`/`highlights` — those relations are not eager-loaded through the nesting.

**Facility** — `name` `{loc}` req, `description` `{loc}` req, `location` `{loc}` opt, `hours` `{loc}` opt, `is_active`, `sort_order`.

**Dining venue** — `name` `{loc}` req, `description` `{loc}` req, `cuisine_type` `{loc}` opt, `location` `{loc}` opt, `hours` `{loc}` opt, `is_active`, `sort_order`. Read side adds read-only `rating`/`rating_count` review aggregates.

**Menu category** — `dining_venue_uuid` (required, must exist), `slug` (required, max 64 — auto-derived from `name.en` when omitted; **no character or uniqueness constraint**), `name.en` + `name.ar` (both required, max 255), `sort_order` (nullable), `is_active` (nullable). `fr`/`tr`/`es` are **not accepted**. Update reuses the create request — resend the full payload.

**Menu item** — `menu_category_uuid` (required, must exist), `name.en` + `name.ar` (required), `description.en`/`description.ar` (nullable), `price_usd` (required numeric ≥0), `is_vegan` (nullable), `is_active` (nullable). No `sort_order`. `fr`/`tr`/`es` not accepted; full payload on update. Read side exposes `type` (the parent category's slug) and `photo` (first image).

**Event space** — `name` `{loc}` req, `description` `{loc}` req, `capacity` (nullable int ≥1), `location` `{loc}` opt, `amenities` `{loc}` opt — a **translatable free-text string here**, not the array it is on room types. Plus `is_active`, `sort_order`.

**Amenity** — `slug` (required on create, max 255, unique, auto-derived from `name.en` when omitted; **no `^[a-z0-9-]+$` constraint**, unlike every other slug field), `name` `{loc}` req (max 255), `icon` (nullable string max 64 — free-form, no server vocabulary), `is_active`, `sort_order`.

**Home slider** — `header_text` `{loc}` req (max 255), `location` `{loc}` req (max 255), `description_text` `{loc}` req (max 1000), `is_active`, `sort_order`. All three text fields are required in `en`+`ar`. The response exposes `photo` (first image) and **no `images` array**, even though the plural media routes exist — upload exactly one image.

**Promotion** — `title` `{loc}` req, `description` `{loc}` req, `secondary_description` `{loc}` opt, `terms` `{loc}` opt, `valid_from` (nullable date), `valid_until` (nullable date, `>= valid_from`), `is_active`, `sort_order`. Read side adds `banner` (first image).

**Page** — `slug` (required on create, unique, `^[a-z0-9-]+$`), `title` `{loc}` req, `content` `{loc}` req, `is_active`, `sort_order`. No image gallery and no `images` key on this type.

**Review** — no create/update/delete; guests are the only authors. `GET /cms/reviews` is list-only (**there is no `/{uuid}` show route**), newest first, `per_page` ignored (fixed 15), no `search`/`sort`. It takes one optional parameter, `is_published`, read via `$request->boolean()`: omit for all, `true` for published, `false` for unpublished — and **`?is_published=` (empty) means *unpublished*, not "all"**, because this module bypasses the standard filter layer. Moderation is `PATCH /cms/reviews/{uuid}/publish` with `{ "is_published": true|false }` (required boolean). `is_verified_stay` is derived server-side and not editable.

**Testimonial** — `author_name` (required on create, max 255, **plain string, not translatable**), `author_title` `{loc}` opt (max 255), `quote` `{loc}` req, `rating` (nullable int 1–5), `is_active`, `sort_order`. Read side adds `avatar` (first image). Unrelated to guest reviews.

**FAQ** — `category` (nullable plain string max 255, free-form — no server vocabulary), `question` `{loc}` req (max 500), `answer` `{loc}` req, `is_active`, `sort_order`.

**Experience** — `slug` (required on create, unique, `^[a-z0-9-]+$`), `title` `{loc}` req (max 255), `description` `{loc}` req, `category` (required on create, plain string max 255, free-form), `duration_minutes` (nullable int 1–1440), `price_usd` (nullable numeric ≥0), `is_active`, `sort_order`. Read side adds `image` (first) plus `images`.

**Gallery category** — `slug` (required on create, unique, `^[a-z0-9-]+$`), `name` `{loc}` req (max 255), `is_active`, `sort_order`. No media — the photographs live on gallery items. **Delete cascades to every photograph in the category.**

**Gallery item** — `gallery_category_uuid` (required on create, must exist), `caption` `{loc}` req (max 500), `is_active`, `sort_order`. Create the row, then `POST /cms/gallery-items/{uuid}/images` — the row is meaningless without a photograph. Read side adds `category_slug`, the nested `category`, `image` (first) and `images`. Filter the list by chip with **`?category=<category-slug>`** (a bespoke parameter outside the DSL); the whitelisted `gallery_category_id` is the internal integer key the API never exposes and is unusable from a client.

**Journal post** — `slug` (required on create, unique, `^[a-z0-9-]+$`), `title` `{loc}` req (max 255), `excerpt` `{loc}` opt (max 1000), `body` `{loc}` req, `category` `{loc}` opt (max 100 — **translatable here**, unlike FAQ/experience `category`), `published_on` (required date on create, `sometimes|date` on update), `is_active`, `sort_order`. Read side adds `cover_image` (first image) plus `images`. Default order is `published_on` DESC then `sort_order`.

**Site settings** — see the dedicated subsection below.

On `update`, plain fields become `sometimes` and translatable required fields become `sometimes|required` — you cannot submit an incomplete or blanked translation, but you can omit the field entirely to leave it unchanged. **Exceptions:** the menu module and the P7 catalog reuse their create request on update, so those `PUT`/`PATCH` calls require the full payload.

### Site settings — two routes, neither conventional

`GET /cms/settings` is **not paginated**. `data` is an object keyed by group, each holding an array of full rows ordered by `group` then `key`, and it includes `is_active: false` rows:

```json
{
  "booking": [
    { "uuid": "…", "group": "booking", "key": "cta_label",
      "value": { "en": "Book Now", "ar": "…", "fr": "…" }, "type": "text", "is_active": true }
  ],
  "contact": [ … ], "footer": [ … ], "hero": [ … ], "seo": [ … ], "social": [ … ]
}
```

`PUT /cms/settings` is a **bulk atomic upsert**:

```json
{ "settings": [
  { "group": "hero", "key": "heading", "value": { "en": "…", "ar": "…" }, "type": "text", "is_active": true }
] }
```

- `settings` required array (min 1). Per row: `group` required (max 50, `^[a-z][a-z0-9_]*$`), `key` required (max 100, same pattern), `value` `present` (any JSON, including `null`), `type` required — one of `text`, `richtext`, `image`, `url`, `json`, `bool` — and `is_active` optional boolean.
- Identity is the `(group, key)` pair. A duplicate pair inside one payload is a `422` keyed `settings.{index}.key`.
- Every row is `updateOrCreate`d in **one transaction** — all of it lands or none does. A `422` on row 7 means rows 1–6 were not written.
- The response is the **full grouped set**, not an echo of what you sent. Re-render the form from it.
- `value` is **unvalidated free-form JSON**; `type` is only a hint about which editor widget to render. Locale maps in settings are a convention, not something `TranslatableRules` enforces. Validate client-side.
- No per-row read, no `POST`, no `DELETE`. A setting can be deactivated but not removed through the API.

Seeded groups/keys (20 rows, values in `en`/`ar`/`fr`): `contact` (`phone`, `email`, `address`, `address_lines:json`, `hours_note`), `social` (`instagram`, `facebook`, `x`, `youtube` — seeded `null` and inactive), `footer` (`tagline`, `copyright`, `newsletter_heading`), `booking` (`cta_label`, `availability_note`), `seo` (`site_title`, `meta_description`), `hero` (`eyebrow`, `heading`, `subheading`, `cta_label`).

The public read of the same data (`GET /api/public/settings`) is a **flat `{group:{key:value}}` map of active rows only** — a different shape. Do not reuse one parser for both.

### Images

> ⚠️ **In flight:** a media library (parentless upload, a filterable `index`, an
> `update`, a delete by media uuid alone, `attach`-existing routes per parent, and
> new `alt_text` (translatable) + `title` fields on every media object) is being
> added in `MediaController` / `MediaService` / `Media` / `MediaResource` but is
> **not yet wired into `routes/api.php`**. Everything below is what the API serves
> today; re-check before building an asset picker.

12 modules accept media: `room-types`, `rooms`, `facilities`, `dining-venues`, `event-spaces`, `home-sliders`, `promotions`, `testimonials`, `experiences`, `gallery-items`, `journal-posts`, `menu-items`. Not `amenities`, `faqs`, `pages`, `gallery-categories`, `settings`, `reviews`, or the P7 catalog other than menu items.

- `POST /cms/{module}/{uuid}/images` — multipart, `image` (**required**, a single file, `jpg`/`jpeg`/`png`/`webp`, max 5 MB / `max:5120` KB), `sort_order` (optional int ≥0, default 0). Returns HTTP 201 and the created media object: `{ "uuid", "url", "file_name", "mime_type", "size", "sort_order" }`. One file per request — upload a five-image gallery with five calls.
- `DELETE /cms/{module}/{uuid}/images/{media}` — removes the file and the record. HTTP 204. No bulk delete.

**The `{uuid}` parent segment scopes the delete.** `MediaService` verifies that the media's owner matches the parent in the URL; deleting a valid media uuid through the wrong parent returns **`404 not_found`** (with `context: { media, parent }`) and deletes nothing. A flat client-side map of media uuids is not enough — you must call with the parent the image actually belongs to.

URLs are **absolute**, built as `APP_URL + /storage/ + path`, so media is served by the API origin and **`php artisan storage:link` must have been run** or every URL is a well-formed 404. Storage layout is `cms/{ModelClassBasename}/{parent-uuid}/{hash}.{ext}`.

The single-image convenience fields — `banner` (room types, promotions), `photo` (home sliders, menu items), `cover_image` (journal posts), `avatar` (testimonials), `image` (experiences, gallery items) — are all `images->first()?->url`, i.e. load order. There is **no designated primary image** and no way to set one; `sort_order` is advisory.

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
  "rooms": [ { "room_type": { "...room type..." }, "room_uuid": "...", "room_number": "801", "price_usd": "270.00" } ],
  "guest": { "uuid": "...", "name": "...", "phone": "...", "email": "..." },
  "promo_code": null
}
```

### POST /cms/reservations — `reservations.create`

**Purpose:** Front-desk booking — reception creating a reservation for a guest at the desk or on the phone. There is no OTP step: the public two-step flow verifies a guest who is not present, whereas here staff vouch for them by holding the permission.

**Request body — identify the guest one of two ways.**

Existing guest already on file:
```json
{
  "guest_uuid": "...",
  "room_type_uuid": "...", "check_in": "2026-09-05", "check_out": "2026-09-08",
  "payment_method": "on_arrival"
}
```

New arrival — name plus **at least one** of `phone` / `email`:
```json
{
  "first_name": "Nour", "last_name": "Haddad",
  "phone": "+963955123456", "email": "nour@example.com",
  "room_type_uuid": "...", "check_in": "2026-09-05", "check_out": "2026-09-08",
  "payment_method": "on_arrival", "promo_code": "CARLTON10",
  "status": "confirmed", "source": "walk_in"
}
```

| Field | Required | Notes |
|---|---|---|
| `guest_uuid` | either/or | Existing guest. When sent, name and contact fields are ignored. |
| `first_name`, `last_name` | required without `guest_uuid` | |
| `phone`, `email` | at least one without `guest_uuid` | Phone is normalised to E.164. An existing guest matching the phone (then email) is reused rather than duplicated. |
| `room_type_uuid`, `check_in`, `check_out`, `payment_method` | yes | `check_in` cannot be in the past; `check_out` must be after it. |
| `promo_code` | no | Applied and its usage counter incremented, same as the guest flow. |
| `status` | no | `confirmed` (default) or `pending` — use `pending` for a phone booking still awaiting a deposit. |
| `source` | no | `walk_in` (default) or `direct`. |

**Behavior:** identical inventory and pricing path to the guest-facing booking — the room type row is locked, a specific room is reserved immediately (so `rooms[].room_number` is populated on the response), the stay is priced, and any promo is applied inside the same transaction. No `hold_expires_at` is set; the booking is live at once.

A guest created through this endpoint has **no verified contact** — they never proved they own the number. They claim the booking in the app through `POST /auth/guest/link-booking-code`, which is what performs the verification.

**Response:** HTTP 201, the reservation in the shape shown under `GET /cms/reservations/{uuid}`.

**Failure `error_code`s:** `no_availability` (409, no free room of that type for the dates), `validation_failed` (422 — including `identity` when neither `guest_uuid` nor a phone/email was sent), `not_found` (404, unknown `guest_uuid`).

### POST /cms/reservations/{uuid}/confirm — `reservations.create`

**Purpose:** Confirm a `pending`/`pending_verification` reservation.

**Request:** No body. **Response:** updated reservation, `status: "confirmed"`.

**Failure:** `reservation_state` (422) if already confirmed or otherwise not confirmable.

### POST /cms/reservations/{uuid}/assign-room — `reservations.create`

**Purpose:** Check the guest in, optionally moving them to a different room. **This is the check-in action — there is no separate "check in" endpoint.**

**Request body:** `{ "room_uuid": "..." }` — **optional.**

A specific room is now reserved when the booking is created, so `rooms[].room_number` is already populated before check-in. Omit `room_uuid` to check the guest into the room they were given; send it only to move them to a different room of the same type.

**Behavior:** requires the reservation to be `confirmed`, the target room's type to match the booked type, and no date-overlapping hold on that room by another booking. A booking holds its room from creation — including while merely `pending` — so a room reserved by an unconfirmed booking cannot be handed to someone else. On success, sets `room_id`, flips `status` to `checked_in`, and stamps `checked_in_at` (a later room move does not overwrite the original arrival time).

**Response `data`:** updated reservation with the room under `rooms[].room_uuid`/`room_number`.

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

## Module: In-Stay Service Catalog (reads `cms.view|cms.edit` · writes `cms.edit`)

Standard `apiResource` CRUD (index/store/show/update/destroy) for the **8** bookable/orderable catalog types staff maintain. Gated exactly like Module: CMS Content — `index`/`show` on `permission:cms.view|cms.edit`, the mutating verbs on `permission:cms.edit`. The update route accepts `PUT` **or** `PATCH`.

| Type | Base path | Fillable fields |
|---|---|---|
| Spa services | `/cms/spa-services` | `name.en/ar` (required), `duration_minutes` (required int ≥1), `price_usd` (required ≥0), `is_active` |
| Restaurant tables | `/cms/restaurant-tables` | `dining_venue_uuid` (optional, must exist), `table_number` (required, max 50), `capacity` (required int ≥1), `is_active` — **not translatable, no `name`** |
| Pool cabanas | `/cms/pool-cabanas` | `name.en/ar` (required), `capacity` (required int ≥1), `price_usd` (required ≥0), `is_active` |
| Transfers | `/cms/transfers` | `name.en/ar` (required), `price_usd` (required ≥0), `is_active` — no capacity/duration |
| Service categories | `/cms/service-categories` | `code` (required, max 50, unique), `name.en/ar` (required), `description.en/ar` (optional), `kind` (required — `ServiceCategoryKind`), `department` (required when `kind` is `catalog` or `direct` — `Department`), `link_target` (required when `kind` is `link`, max 30), `icon` (optional, max 50), `is_active`, `sort_order` |
| Service items | `/cms/service-items` | `service_category_uuid` (required, must exist), `name.en/ar` (required), `description.en/ar` (optional), `expected_minutes` (optional int 1–10080), `price_usd` (optional ≥0), `is_default`, `is_active`, `sort_order` |
| Menu categories | `/cms/menu-categories` | `dining_venue_uuid` (**required**, must exist), `slug` (**required**, max 64 — auto-derived from `name.en` when omitted), `name.en/ar` (required), `sort_order` (optional int ≥0), `is_active` |
| Menu items | `/cms/menu-items` | `menu_category_uuid` (required, must exist), `name.en/ar` (required), `description.en/ar` (optional), `price_usd` (required ≥0), `is_vegan` (optional bool), `is_active` |

Three properties this whole group shares, and which differ from Module: CMS Content:

- **`en` and `ar` only.** These requests hardcode `name.en`/`name.ar` instead of generating rules from `config/cms.php`, so `fr`/`tr`/`es` keys are dropped by validation without any error.
- **Update requires the full payload.** Each update route reuses its *create* FormRequest, so every `required` field is still required on `PUT`/`PATCH`. A partial update returns `422`.
- **`is_active` is `['nullable','boolean']` here**, so `null` is accepted — unlike the CMS content modules, where `null` is a `422`.

Response shapes mirror the fillable fields (translatable fields as locale maps, foreign keys exposed as `_uuid`, never the internal integer id). Menu categories nest their items under `items: []` when the relation is loaded; menu items expose `type` (the parent category's slug) and `photo` (first image). **`/cms/menu-items` is the only member of this group with media routes** (`POST`/`DELETE .../{uuid}/images` — same contract as Module: CMS Content).

For the menu module specifically, see also Module: CMS Content — it is documented there as part of the dining content the website reads.

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
