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

**Full permission catalog** (13 modules, 30 permissions): `reservations.view|create|cancel`, `folios.view|settle|post|dispute`, `cms.view|edit|restore|purge`, `rooms.status`, `service_requests.view|assign|update`, `tickets.view|assign|respond`, `events.view|manage|deposit`, `pricing.edit`, `reports.view`, `night_audit.manage`, `staff.manage`, `guests.view|edit`, `housekeeping.view|assign|update`.

### `cms.view` is enforced — gate read-only navigation on it

CMS routes are split by verb. Every **read** (`GET` on a collection and on `/{uuid}`) is gated on `cms.view|cms.edit`; every **write** (`POST`, `PUT`, `PATCH`, `DELETE`) is gated on `cms.edit`. Spatie resolves a pipe-separated list through `canAny()` — ANY, not ALL — so `cms.edit` implies read access:

| Account holds | CMS reads | CMS writes |
|---|---|---|
| `cms.edit` only | ✅ | ✅ |
| `cms.view` only | ✅ | ❌ `403` |
| neither | ❌ `403` | ❌ `403` |

So a `cms.view`-only account is a genuine read-only reviewer, and an editor never needs both rows. **Show a read-only CMS section when the account holds `cms.view` *or* `cms.edit`; show the create/edit/delete controls only for `cms.edit`.**

### `cms.restore` and `cms.purge` — the recycle bin is not part of `cms.edit`

`DELETE /cms/{module}/{uuid}` is a **soft delete**. The three verbs that address the bin sit outside the read/write split above, on their own permissions, because undoing a delete and destroying a record permanently are not edits:

| Route | Gate |
|---|---|
| `GET /cms/{module}/trashed` | `cms.restore\|cms.purge` |
| `POST /cms/{module}/{uuid}/restore` | `cms.restore` |
| `DELETE /cms/{module}/{uuid}/force` | `cms.purge` |

`cms.edit` alone gets a `403` on all three — an account that can delete cannot necessarily undo it, and certainly cannot empty the bin. The bin **listing** admits either bin permission, because it is only useful to someone who can act on a row in it; a `cms.view`-only reviewer gets a `403` there and should not be shown a "Trash" nav item at all.

**Gate the UI as:** show "Trash" when the account holds `cms.restore` *or* `cms.purge`; show "Restore" only for `cms.restore`; show "Delete permanently" only for `cms.purge`.

The split is structural, so it also holds for content types added after this revision — gate on the rule above rather than on a route list. At the time of writing that was 48 routes gated `cms.view|cms.edit` and 95 gated `cms.edit`, but each new content type adds several more; run `php artisan route:list --path=api/cms -v` for the live figure rather than trusting a number in a document.

> Earlier revisions of this guide said `cms.view` was reserved and ungated. That was true up to commit `2282314` and is wrong now.

### Where each permission is enforced

Most are route middleware, which is the norm. Two families are not, and are enforced just as strictly:

- **`staff.manage`** — checked by `StaffPolicy` (registered on the `User` model), not by middleware. It gates all six `/staff` routes plus `GET /api/permissions` and `GET /api/roles`. A `403` from those endpoints means `staff.manage` is missing, even though the route carries no `permission:` middleware.
- **`service_requests.assign` / `service_requests.update` / `tickets.assign` / `tickets.respond` / `housekeeping.assign` / `housekeeping.update`** — checked inside `OperationsQueueService`, which derives the required permission from the `{type}` segment of the URL (`service-requests`, `tickets`, `housekeeping-tasks`) because it differs per type. See *Module: Operations Queue & Dashboard*. The same service also checks the type's **work** permission on `PATCH /operations/queue/{type}/{uuid}/claim` (`service_requests.update` / `tickets.respond` / `housekeeping.update`; the assign permission alone is a `403`). The dedicated `/housekeeping/tasks` routes, by contrast, carry ordinary `permission:` route middleware (`housekeeping.view|assign|update`) — see *Module: Housekeeping*.
- **`tickets.view` / `tickets.respond` / `tickets.assign`** on the `/support-tickets` routes are ordinary route middleware: `tickets.view` reads (list, show), `tickets.respond` writes (create, status, reply, recovery-actions, escalate), `tickets.assign` assigns. See *Module: Support Tickets*. The same three permissions also gate the chat inbox (see *Module: Chat*). Event inquiries are gated by their own `events.*` group since Phase 8 (see *Module: Event Inquiries*).

### Genuinely inert — do not build UI against these

None. Every permission in the catalog is now enforced somewhere. The last inert one, pricing.edit, became real in Phase 9.1: the exchange-rate routes require it (see *Module: Exchange rates*). It is still held by no role preset, so grant it per account. (The reports permission became real in Phase 9 — see Night audit and Reports.)

### Role presets

Seven presets: `reception`, `kitchen`, `housekeeping`, `concierge`, `events`, `content_editor`, `content_manager` — see Module: Reference Data below for exactly which permissions each preset grants. The last two are the only presets that grant `cms.*`; without one of them no seeded account except the super admin can reach `/api/cms/*`. `content_manager` is `content_editor` plus `cms.purge`, and is the only preset that may empty the recycle bin. `housekeeping` and `reception` also hold `rooms.status`. `reception` also holds `folios.post` and `folios.dispute`. Since Phase 6, `housekeeping` holds all three `housekeeping.*` permissions (it runs the task board); `reception` holds `housekeeping.view` and `housekeeping.assign` (it can see and hand off tasks, but not move them through their statuses).

Since Phase 7 `reception` also holds `tickets.view` and `tickets.respond`, and `concierge` also holds `tickets.view`, `tickets.assign` and `tickets.respond`; `events` already held all three. `kitchen` and `housekeeping` hold no `tickets.*`. **These permissions are shared with other surfaces, so the grant has a wider blast radius than the support-ticket routes:** `tickets.view` opens `GET /cms/conversations` and its messages (the guest chat inbox), and `tickets.respond` allows `POST /cms/conversations/{uuid}/messages` (replying to a guest in chat).

Since Phase 8 the catalogue is 12 modules / 29 permissions: the new `events` group adds `events.view`, `events.manage` and `events.deposit`. **Contract tightening versus Phase 7:** event inquiries are gated by `events.*` only — `tickets.*` no longer opens any event-inquiry route. Only the `events` preset holds `events.*`, so `reception` and `concierge` lost all event-inquiry access (index, show, status and assign now answer `403`); `tickets.*` still opens `/cms/conversations` for them. The `event_inquiries` key of `GET /dashboard/summary` now follows `events.view` (it used to follow `tickets.view`), so reception and concierge no longer receive it. Gate the Events navigation item on `events.view`.

Since Phase 9 the catalogue is 13 modules / 30 permissions: the new `night_audit` group adds `night_audit.manage`, and `reports.view` is now enforced (see *Module: Night audit* and *Module: Reports*). **No role preset holds `reports.view` or `night_audit.manage`** — assign them per account through the existing permission-assignment endpoint (`POST /staff/{uuid}/permissions`). Suggested accounts: a night manager gets `reports.view` + `night_audit.manage`; a night auditor without revenue access gets `night_audit.manage` only. The super admin passes everything.

Since Phase 9.1 `pricing.edit` is enforced by the exchange-rate routes (see *Module: Exchange rates*). It is still in **no** role preset — grant it per account through `POST /staff/{uuid}/permissions`; the catalogue stays 13 modules / 30 permissions.

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

**Rate-limited.** `POST /api/auth/login` is throttled at 10 requests per minute per client IP; beyond that it answers `429` `too_many_requests`. Do not auto-retry on `401`.

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

### GET /api/auth/profile

**Purpose:** Read the signed-in staff member's own profile for the settings screen.

**Who can call:** Any authenticated staff member (no permission needed).

**Request:** `Authorization: Bearer <token>`. No body.

**Response `data`:** Same shape as `user` in `/auth/login`, and identical to `GET /api/auth/me`.

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `unauthorized` | 401 | Token expired or invalid — redirect to login. |

---

### PUT /api/auth/profile

**Purpose:** The staff member edits their own name and email.

**Who can call:** Any authenticated staff member (no permission needed).

**Request body:**

| Field | Type | Required |
|---|---|---|
| `name` | string | Required when `email` is absent; max 255. |
| `email` | string | Required when `name` is absent; valid email, max 255, unique across staff. |
| `current_password` | string | Required when `email` changes to a different value; if sent, it is always checked. |

Send only what changes; an empty body is rejected. Type, activation, roles and permissions are not editable here and stay on `PUT /api/staff/{uuid}`; the language switch stays client-side.

**Response `data`:** The updated user object, same shape as `/auth/me`, with message "Profile updated.".

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `unauthorized` | 401 | Token expired or invalid — redirect to login. |
| `validation_failed` | 422 | Show each `errors.<field>` under its field. `errors.current_password` reads "The current password is incorrect." |

**State notes:** No rate limit applies to this route. Refresh the stored user from `data` on success.

---

### PUT /api/auth/password

**Purpose:** The staff member changes their own password.

**Who can call:** Any authenticated staff member (no permission needed).

**Request body:**

| Field | Type | Required |
|---|---|---|
| `current_password` | string | ✅ |
| `password` | string | ✅ — min 8, must differ from `current_password`. |
| `password_confirmation` | string | ✅ — must equal `password`. |

**Response `data`:** `null`, message "Password changed.".

**State notes:** The token that made the call stays valid, and every other session of this account is signed out (their next call returns `401` `unauthorized`).

**Failure `error_code`s:**

| Code | HTTP | UI action |
|---|---|---|
| `unauthorized` | 401 | Token expired or invalid — redirect to login. |
| `validation_failed` | 422 | `errors.current_password` / `errors.password`. |
| `too_many_requests` | 429 | 5 requests per minute per account — tell the user to wait a minute and do not auto-retry. |

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
  },
  {
    "module": "housekeeping",
    "permissions": ["housekeeping.view", "housekeeping.assign", "housekeeping.update"]
  }
]
```

13 modules: `reservations`, `folios`, `cms`, `rooms`, `service_requests`, `tickets`, `events`, `pricing`, `reports`, `night_audit`, `guests`, `staff`, `housekeeping`.

---

### GET /api/roles

**Purpose:** List all role presets and their included permissions. Use to populate the role dropdown when creating a staff account.

**Who can call:** Authenticated staff with `staff.manage`.

**Response `data`:** Array of presets:
```json
[
  { "name": "reception", "permissions": ["reservations.view", "reservations.create", "reservations.cancel", "folios.view", "folios.settle", "folios.post", "folios.dispute", "service_requests.view", "service_requests.update", "rooms.status", "guests.view", "guests.edit", "housekeeping.view", "housekeeping.assign", "tickets.view", "tickets.respond"] },
  { "name": "kitchen", "permissions": ["service_requests.view", "service_requests.update"] },
  { "name": "housekeeping", "permissions": ["service_requests.view", "service_requests.update", "rooms.status", "housekeeping.view", "housekeeping.assign", "housekeeping.update"] },
  { "name": "concierge", "permissions": ["service_requests.view", "service_requests.assign", "service_requests.update", "guests.view", "guests.edit", "tickets.view", "tickets.assign", "tickets.respond"] },
  { "name": "events", "permissions": ["service_requests.view", "tickets.view", "tickets.assign", "tickets.respond", "events.view", "events.manage", "events.deposit"] },
  { "name": "content_editor", "permissions": ["cms.view", "cms.edit", "cms.restore"] },
  { "name": "content_manager", "permissions": ["cms.view", "cms.edit", "cms.restore", "cms.purge"] }
]
```

7 presets. `content_editor` is the everyday CMS persona — the seeded account
`content@carlton.demo` holds it — and `content_manager` is the same persona plus
`cms.purge`, the authority to empty the recycle bin. Those two are the only
non-super-admin logins that can reach `/api/cms/*`.

---

## Module: CMS Content (reads `cms.view|cms.edit` · writes `cms.edit`)

Admin CRUD for the **19 CMS modules**, plus the 6 P7 service-catalog resources that sit behind the same gates. Most modules follow one shape: `GET`/`POST` on the collection, `GET`/`PUT`/`DELETE` on `/{uuid}`, plus the three recycle-bin verbs (`GET /trashed`, `POST /{uuid}/restore`, `DELETE /{uuid}/force`) documented under **The recycle bin** below. All under `auth:users`; the `GET`s are gated on `permission:cms.view|cms.edit` and `POST`/`PUT`/`PATCH`/`DELETE` on `permission:cms.edit`, with the bin verbs on `cms.restore` / `cms.purge` — see [Permission model](#permission-model).

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

`/cms/media` sits under the same gates but is **not** a content type, so it is absent from the table above: it is the media library (`GET` / `POST` / `PATCH` / `DELETE`, no `show`, nothing translatable but `alt_text`). See **The media library** below.

P7 service catalog, identical gates, full `apiResource` each (`PUT` **or** `PATCH`, full payload required on update, `en`/`ar` only): `/cms/spa-services`, `/cms/pool-cabanas`, `/cms/transfers`, `/cms/restaurant-tables`, `/cms/service-categories`, `/cms/service-items`. Only `/cms/menu-items` in that group takes media.

Response shapes are identical to the public read shapes — the same Resource class serves both admin and public routes, so only the row *selection* differs. `store` returns HTTP 201, `destroy` returns HTTP 204 with `data: null`.

> **`DELETE` is a soft delete and is recoverable.** Earlier revisions of this guide said "there are no soft deletes anywhere in the CMS" and that `DELETE` was permanent. That is no longer true and has not been since the recycle bin landed — 17 of the 18 soft-deletable CMS models now answer `DELETE` by stamping `deleted_at` rather than removing the row. **The wire contract of `DELETE` itself has not moved:** still HTTP 204, still gone from every index, every `/{uuid}` show and every public route, and its slug/room number/`(group,key)` freed for reuse. What changed is that the record can now be listed, restored, or destroyed for good — see **The recycle bin** below. Confirmation copy should say "moved to trash", not "permanently deleted".
>
> Several relations cascade, on the way down and on the way back: room type → rooms, dining venue → menu categories → menu items, gallery category → items, menu category → items. A restore brings back exactly the children that went down *with* the parent — a dish retired last week, before its venue was deleted, stays retired.
>
> **Media survives a `DELETE` and is destroyed by a force delete.** `PurgesMedia` fires on `forceDeleted`, not on the recoverable delete, so a soft-deleted record keeps its `media` rows and its files and comes back whole. `DELETE /cms/{module}/{uuid}/force` is what finally drops those rows — the record's own, and its cascade descendants' — and the files nothing else references. File unlinks are queued (`PurgeMediaFile`, dispatched after commit, re-checked before deleting), so storage is reclaimed shortly after the request rather than during it — and not at all on a host with no queue worker.

### The recycle bin

Every module in the table above (all except **Site settings**, which has no `DELETE` at all) carries three extra verbs. Reviews are read-and-publish only and have no `DELETE` either, so they have no bin.

| Verb | Path | Gate | Returns |
|---|---|---|---|
| `GET` | `/cms/{module}/trashed` | `cms.restore\|cms.purge` | Paginated `items` + `meta`, **most recently deleted first** |
| `POST` | `/cms/{module}/{uuid}/restore` | `cms.restore` | HTTP 200, the restored record in the module's normal shape — or **409 `ancestor_trashed`**, below |
| `DELETE` | `/cms/{module}/{uuid}/force` | `cms.purge` | HTTP 204, `data: null` |

Notes the dashboard has to get right:

- **`/trashed` is a literal segment, not a uuid.** It is declared ahead of `/{uuid}`, so `GET /cms/pages/trashed` is the bin and never a lookup for a page whose uuid is the word "trashed".
- **The bin ordering is `deleted_at DESC`, and it overrides the module's editorial `sort_order`.** A bin is read chronologically — "what did I just delete" — so `?sort=` is not honoured here.
- **`restore` and `force` take the uuid of a *deleted* record**, and only those two routes resolve one. Every other route in this guide, `GET /{uuid}` included, still `404`s on a deleted uuid. A uuid that names nothing at all is a `404` from these two as well.
- **Restore is idempotent-ish, not a toggle.** Restoring a record that is already live answers 200 and changes nothing; there is no "un-restore" — use `DELETE` again.
- **Force delete needs the record to be in the bin first.** There is no one-step permanent delete: `DELETE` then `DELETE …/force`.
- A restored parent brings back its cascade children **and its images**; a force-deleted parent takes both with it, permanently.
- **A child cannot be restored while an ancestor is still in the bin — HTTP 409, `error_code: "ancestor_trashed"`.** This is the only domain error code a CMS endpoint raises. Restoring a room whose room type is still binned used to answer 200 and produce a live, bookable room under a type visible on no screen and no public page; the same held for menu categories and dishes under a binned dining venue and photographs under a binned gallery category. The check walks the **whole chain to the root**, so a dish under a live category under a binned venue is refused too.

  ```json
  {
    "success": false,
    "message": "This item cannot be restored while the record it belongs to is still in the recycle bin. Restore that one first.",
    "error_code": "ancestor_trashed",
    "context": {
      "ancestor": { "type": "dining_venue", "uuid": "…" },
      "trashed_ancestors": [
        { "type": "dining_venue",  "uuid": "…" },
        { "type": "menu_category", "uuid": "…" }
      ]
    },
    "request_id": "…"
  }
  ```

  `context.ancestor` is the one to restore **first** — the outermost binned ancestor, whose own restore cascades back down and in the ordinary case returns the record you asked for with it. `context.trashed_ancestors` is the full chain outermost-first, always non-empty, longer than one entry only where an ancestor was deleted separately from its parent and so fell outside that parent's cascade. `type` uses the same token vocabulary as `mediable_type` on the media library (`room_type`, `dining_venue`, `menu_category`, `gallery_category`), so `type` + `uuid` is enough to build that ancestor's own restore call.

  **There is deliberately no `?with_ancestors=true`.** Restoring an ancestor brings back *every* child that went down with it, so a flag on one dish would silently resurrect the whole venue, its categories and every other dish on the menu — under a permission check made against the dish. Show the ancestor from `context` and let the user restore it as a second, explicit call; the audit trail then records it against the record actually restored.

- **The bin empties itself after 90 days.** A nightly scheduled job (`cms:purge-bin`) force-deletes everything binned longer than `config('cms.recycle_bin.retention_days')` — rows, cascade descendants, `media` rows and the stored files — with exactly the effect of `DELETE …/force`. Before this existed a soft-deleted record and its photography sat on disk indefinitely and "recoverable" quietly meant "permanent". There is **no API surface** for it: no endpoint, no expiry field on the trashed object, and nothing in the `/trashed` response shape changed. The consequence for the dashboard is that the bin is not an archive — a uuid that was in `/trashed` last quarter may now `404`, which is a normal outcome and not an error state. If you want to show "expires in N days", derive it from `deleted_at` plus the window; do not hard-code "90" into copy without checking the deployment's config. Operators can run it by hand, and `--dry-run` reports what would go without destroying anything:

  ```
  php artisan cms:purge-bin --dry-run
  php artisan cms:purge-bin --days=180
  ```

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

**Room** — `room_type_uuid` (**not** `room_type_id` — required on create, must exist), `number` (required on create, max 10, unique), `floor` (nullable int 0–200), `status` (`available|dirty|maintenance`, accepted on create only), `is_active`. No `sort_order`. `PUT /cms/rooms/{uuid}` ignores a `status` key — no error, no change; change status with `PATCH /cms/rooms/{uuid}/status` (see below). The nested `room_type` in the response omits `images`/`banner`/`amenities`/`highlights` — those relations are not eager-loaded through the nesting.

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

12 modules accept media: `room-types`, `rooms`, `facilities`, `dining-venues`, `event-spaces`, `home-sliders`, `promotions`, `testimonials`, `experiences`, `gallery-items`, `journal-posts`, `menu-items`. Not `amenities`, `faqs`, `pages`, `gallery-categories`, `settings`, `reviews`, or the P7 catalog other than menu items.

Every media object, everywhere in the API, is `MediaResource`:

```json
{ "uuid": "9f3c…", "url": "http://127.0.0.1:8000/storage/cms/library/9aKd….jpg",
  "file_name": "lobby.jpg", "alt_text": { "en": "Lobby at dusk", "ar": "…" },
  "title": "Lobby — chandelier", "mime_type": "image/jpeg", "size": 184320, "sort_order": 0 }
```

- **`alt_text` is a translatable locale map**, exactly like `name`/`description` on a content type: all five configured locales accepted, every one optional, and the response carries the whole map so a client can switch `alt` with the language off a single fetch.
- **When no alt text exists the value is `[]`, not `{}`** — PHP's empty array serializes as a JSON array. Guard for it.
- **`title` is a plain string, not translatable.** It is the editor-facing label in the picker; `null` when unset.
- `size` is bytes. `file_name` is the client's original filename; the stored path uses a generated hash name.
- **`mediable_type` / `mediable_id` are not exposed.** You can filter the library by parent type but cannot read back which parent a row belongs to.

#### Per-parent upload and delete

- `POST /cms/{module}/{uuid}/images` — multipart, `image` (**required**, a single file, `jpg`/`jpeg`/`png`/`webp`, max 5 MB / `max:5120` KB), `sort_order` (optional int ≥0, default 0). HTTP 201, `data` = the created media object. One file per request — upload a five-image gallery with five calls. **No `alt_text`/`title` on this route**; upload then `PATCH`, or use the library.
- `DELETE /cms/{module}/{uuid}/images/{media}` — HTTP 204. Removes the record, and the file **only when no other row references it** (see the copy semantics below). No bulk delete.

**The `{uuid}` parent segment scopes the delete.** `MediaService` verifies that the media's owner matches the parent in the URL; deleting a valid media uuid through the wrong parent returns **`404 not_found`** (with `context: { media, parent }`) and deletes nothing. A flat client-side map of media uuids is not enough — you must call with the parent the image actually belongs to.

URLs are **absolute**, built as `APP_URL + /storage/ + path`, so media is served by the API origin and **`php artisan storage:link` must have been run** or every URL is a well-formed 404. Storage layout is `cms/{ModelClassBasename}/{parent-uuid}/{hash}.{ext}` for a parent upload and `cms/library/{hash}.{ext}` for a library upload. An attached copy keeps the path it was uploaded to — do **not** infer the parent from the URL.

### Dining venue menu file (Phase 8)

A venue can carry one downloadable menu file (PDF or image), separate from its `images` gallery. Both routes are `auth:users` and need `cms.edit`.

| Verb | Path | Result |
|---|---|---|
| `POST` | `/cms/dining-venues/{uuid}/menu-file` | `201`, `data` = `MediaResource` (`uuid`, `url`, `file_name`, `alt_text`, `title`, `mime_type`, `size`, `sort_order`, `mediable_type`), message "Menu file uploaded." |
| `DELETE` | `/cms/dining-venues/{uuid}/menu-file` | `200`, `data: null`, message "Menu file removed." |

- `POST` is multipart: `file` (required; `pdf`, `jpg`, `jpeg`, `png`, `webp`; max 10 MB) and an optional `title`. A missing file, a wrong type or a file over 10 MB is a `422`.
- **Replace semantics:** uploading again replaces any existing menu file; the old file is purged.
- `DELETE` with no menu file on the venue answers `404 not_found`.
- **Menu files are hidden from the image surfaces.** They are stored as media rows with `collection = menu` and never appear in the venue's `images`, in the `GET /cms/media` library, and cannot be attached via `…/images/attach` or deleted via `…/images/{media}` (that answers `404`). The library's `PATCH` / `DELETE /cms/media/{uuid}` stay unscoped, so deleting a menu row there removes the menu file.
- Public download (no auth): `GET /public/dining-venues/{uuid}/menu/download` returns `200 { url, file_name, mime_type, size, updated_at }` in the envelope. It returns a URL, not a stream. It is the one **`204` with an empty body (no envelope)** exception: that is what a venue with no menu file answers. Unknown, inactive or deleted venues answer `404 not_found`. The app opens the URL externally.

### The media library

Upload once with no parent, then place the same asset on as many records as you like. `GET` needs `cms.view` (or `cms.edit`, which implies it); all four writes need `cms.edit`.

| Verb | Path | Purpose |
|---|---|---|
| `GET` | `/cms/media` | Browse every asset, attached or not |
| `POST` | `/cms/media` | Upload with no parent |
| `PATCH` | `/cms/media/{media}` | Edit `alt_text` / `title` / `sort_order` |
| `DELETE` | `/cms/media/{media}` | Delete a row, unscoped |
| `POST` | `/cms/{module}/{uuid}/images/attach` | Place library assets on a parent (all 12 modules) |

`{media}` binds by uuid, like every other CMS route.

#### `GET /cms/media`

Standard paginated envelope — `data.items[]` + `data.meta`. `per_page` defaults to **15**, **clamped to 100** rather than rejected.

**Placements whose parent is in the recycle bin are not listed.** A row attached to a soft-deleted record is filtered out of the library — including from `data.meta.total` — and no filter switches it back on. It is not deleted: restore the parent and the asset reappears in the library unchanged; `DELETE /cms/{module}/{uuid}/force` is what finally removes it. The alternative was to keep listing it with a "parent is trashed" flag for a picker to grey out, and that was rejected because nothing else in the API will admit such a row exists: its parent `404`s on every read route, the nested `DELETE /{module}/{uuid}/images/{media}` route `404`s on binding that parent, and `mediable_uuid` already comes back `null` for it. A greyed-out row would advertise an asset the rest of the API denies. **Parentless library uploads (`mediable_type: null`) are never affected** — they are the point of the library and are always listed.

**Newest-first by default:** `created_at DESC, id DESC`. The `id` tiebreak is load-bearing — `created_at` has one-second resolution, so a batch uploaded together would otherwise come back in an arbitrary, unstably-paginated order. A picker opens on what the editor just uploaded with no `sort` param.

| Param | Notes |
|---|---|
| `mime` | The CMS-facing spelling of the `mime_type` column, and the only one the dashboard sends. Aliased to `mime_type` **only when `mime_type` is absent** — an explicit `mime_type` wins. |
| `mime_type` | Operators `eq`, `like`, `in`: `?mime=image/webp`, `?mime_type[like]=webp`, `?mime_type[in]=image/jpeg,image/png`, `?mime_type[]=…&mime_type[]=…`. The alias carries the whole param, so `?mime[in]=…` works too. |
| `mediable_type` | Operators `eq`, `in`. Matches the **stored** morph value, which is the FQCN — `App\Models\RoomType`, not `room-types`. URL-encode the backslashes. |
| `unattached` | `true` → only rows with no parent (the "unused assets" view). `false` → only attached rows. Accepts `1/true/yes/on` and `0/false/no/off`. |
| `search` | Case-insensitive `LIKE` over `file_name`, `title`, and `alt_text` in **each of the five locales**. `%` and `_` in the term are escaped. |
| `sort` | `sort_order`, `created_at`, `updated_at`, `size`, `file_name`. Anything else ignored, default ordering stands. An accepted `sort` **replaces** the default rather than tie-breaking it. `sort_dir` = `asc` (default) / `desc`. |

Filter rules are the universal three: unknown param **ignored**, empty value means **no filter**, uninterpretable value is a **`422`** keyed by the param (`?unattached=mabye` → `errors: { unattached: [...] }`). **No `is_active` filter** — the `media` table has no such column.

#### `POST /cms/media`

Multipart. `image` (**required**, single file, `jpg`/`jpeg`/`png`/`webp`, max 5 MB / `max:5120` KB), `alt_text[{locale}]` (optional string max 255, per locale, optional in *every* locale including the required ones), `title` (optional string max 255), `sort_order` (optional int ≥0, default 0).

Byte-identical file contract to the per-parent upload — the two share one FormRequest so they cannot drift on the size cap or the mime whitelist.

HTTP **201**, `data` = the created media object. `mediable_type`/`mediable_id` are stored **null** — the row is a real asset the moment it exists; attaching is a separate decision. Stored under `cms/library/`.

> `sort_order` is `integer|min:0` with no `nullable`, so a form that submits `sort_order=""` for an untouched field gets a `422`. Strip empty optional parts before building the `FormData`.

#### `PATCH /cms/media/{media}`

`alt_text[{locale}]` (optional string max 255), `title` (optional string max 255), `sort_order` (optional int ≥0). HTTP 200 with the refreshed object. **No `image`, no `path`, no `mediable_*`** — replacing a file is a new upload, moving an asset between parents is attach + delete.

**`alt_text` merges per locale.** Sending only `alt_text[en]` updates `en` and leaves the other four as stored; it does not blank the locales you omitted. A single-language edit form is safe.

#### `DELETE /cms/media/{media}`

HTTP 204. **Unscoped on purpose** — this is the library's own route, addressed by media uuid alone, and `cms.edit` already gates it. It will delete an *attached* row too. The nested `{module}/{uuid}/images/{media}` route remains the only way to reach a row *through* an entity's URL, so its cross-parent guard is untouched.

#### `POST /cms/{module}/{uuid}/images/attach`

```json
{ "media_uuids": ["9f3c…", "1a2b…"] }
```

`media_uuids` **required** array, **1–50** entries; each entry **required** string, `distinct`, must `exist` in `media` by uuid.

HTTP **201**, `data` = an **array** of media objects — the parent's rows, **one per requested uuid, in request order** — so you can zip the response straight back onto the uuids you sent. An unknown uuid is a **`422`** naming the offending index (`errors: { "media_uuids.1": [...] }`), not a `404` that leaves you guessing which of five was wrong. `distinct` rejects the same uuid twice in one call, because the service is idempotent per parent and a duplicate would otherwise collapse silently. `max:50` bounds the batch since every uuid becomes a row.

New rows are appended: `sort_order` starts at `max(existing) + 1` (or `0` when the parent had none) and increments across the batch. `alt_text` and `title` are **copied from the source row**. The source may itself already be attached elsewhere — copying a room type's hero onto a promotion is the point.

#### Two behaviours that will bite an asset picker

**1. Attach copies the row.** A `media` row carries exactly one `mediable_type`/`mediable_id` pair, so a shared asset needs **one row per placement**. The same asset on three parents is **three rows with three uuids naming one file on disk**. Therefore:

- **A media uuid identifies a placement, not an asset.** The library row and each attached copy have different uuids. To mark "already used" in a picker, compare on `url` — `disk` + `path` is what the backend itself compares on, and `url` is derived from it.
- **Editing one copy does not touch the others.** `PATCH` changes that placement only. Deliberate — the same photograph legitimately needs different alt text in different contexts — but there is no "edit the asset once" operation.
- **Deleting a placement does not delete the file.** The file is unlinked only when no remaining row references the same `disk` + `path`. Removing a photograph from one promotion leaves the other parents and the library entry rendering. Conversely, deleting the **last** row does destroy the file — including when that last row is the library entry.

**2. Attach is idempotent per parent.** Attaching an asset a parent already has returns **the existing row** instead of adding a second copy, so a double-submitted form or a retried request cannot put the same photograph on a page twice. The match is on `disk` + `path`, not uuid, which is what makes it work when the row you attach *from* is itself a copy of the one already there.

Two gaps to handle client-side: one request naming two *different* uuids that point at the same file will create two rows (the check reads the parent's rows as they were before the batch) — deduplicate by `url` before sending. And idempotency is per parent, not global; the same asset on many different records is expected.

The single-image convenience fields — `banner` (room types, promotions), `photo` (home sliders, menu items), `cover_image` (journal posts), `avatar` (testimonials), `image` (experiences, gallery items) — are all `images->first()?->url`, i.e. load order. There is **no designated primary image** and no way to set one; `sort_order` is advisory.

---

### PATCH /cms/rooms/{uuid}/status — `rooms.status`

**Purpose:** Move a room through the housekeeping lifecycle. Who can call: `rooms.status` (presets `housekeeping` and `reception`) — `cms.edit` alone is not enough.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | yes | one of `available`, `dirty`, `maintenance` |
| `reason` | string | no | max 255, stored on the history row |

**Behavior:** Transition table — `available` to `dirty` or `maintenance`; `dirty` to `available` or `maintenance`; `maintenance` to `dirty` only (a room leaving maintenance must be cleaned before it is sold). Same-state is rejected. Each accepted change writes one `room_status_history` row (who, when, from, to, reason) and updates the room's `status_changed_at`/`status_changed_by` in one transaction; a rejected change writes nothing. Housekeeping status never affects availability, booking or room assignment. Occupancy is not a status: the board derives it from reservations.

**Response `data`:** the room object (`uuid`, `number`, `floor`, `status`, `is_active`). Message: `"Room status updated."`.

**Failure `error_code`s:** `unauthorized` (401); `forbidden` (403, no `rooms.status`); `not_found` (404, unknown or deleted room); `validation_failed` (422, `errors.status`, `errors.reason`); `room_status_transition_invalid` (422, `context: { from, to, allowed: [...] }` — the UI should offer only `allowed`).

---

## Module: Reservations (`reservations.*`, `folios.settle`)

### GET /cms/reservations — `reservations.view`

Paginated (15 per page), all reservations, newest first.

**Request query:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string or array | no | A reservation status. `?status=a,b`, `?status[in]=a,b` or repeated `?status[]=a&status[]=b` all filter to that set. Values are not validated — an unknown status simply matches nothing. |
| `folio_status` | string | no | `open` or `settled`. Only reservations that have a folio are matched. Any other value is `validation_failed` (422, `errors.folio_status`). |
| `has_open_disputes` | string | no | `1` lists reservations whose folio has an open line-item dispute; `0` lists the rest, including reservations without a folio. Any other value is `validation_failed` (422, `errors.has_open_disputes`). Combines with `folio_status`. |

Empty values mean no filter; unknown query parameters are ignored. Example: `?status=checked_out&folio_status=open` lists stays that left with an open folio — forced check-outs and guest express checkouts. `?status[in]=checked_in,checked_out&has_open_disputes=1` lists in-house and departed stays with a disputed charge still open.

### GET /cms/reservations/{uuid} — `reservations.view`

**Response `data`:**
```json
{
  "uuid": "...", "booking_code": "CARL-XXXXXXXX", "status": "confirmed",
  "check_in": "2026-07-20", "check_out": "2026-07-22", "nights": 2,
  "source": "direct", "payment_method": "cash", "total_usd": "270.00", "hold_expires_at": null,
  "checked_in_at": null, "checked_out_at": null, "check_out_mode": null,
  "rooms": [ { "room_type": { "...room type..." }, "room_uuid": "...", "room_number": "801", "price_usd": "270.00" } ],
  "guest": { "uuid": "...", "name": "...", "phone": "...", "email": "..." },
  "promo_code": null, "notes": "VIP, late arrival"
}
```
`notes` is returned to staff only; guest routes never include it. `check_out_mode` (Phase 6, D-20) is also staff-only: `none`, `staff_force` or `guest_express`, recording how the check-out folio gate was passed; `null` for a stay not yet checked out and for stays checked out before this column existed.

The nested `guest` object (here and on `GET /cms/reservations` rows) also carries the guest's `preferences` — `{ bed_type, pillow_type, floor_preference, other, updated_at }` (Phase 4, D-09), the same object `PATCH /guests/{uuid}/preferences` returns. The reservation payload never carries the digital key or any key column.

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

> ⚠️ **Behaviour change (Phase 3, breaking for the dashboard):** assign-room no longer checks the guest in. It never changes `status` or `checked_in_at`; check in with `POST /cms/reservations/{uuid}/check-in`. The response still carries `status`, so the unchanged state is visible.

**Purpose:** Assign a room before arrival (`confirmed`) or move a checked-in guest to a different room during their stay (`checked_in`).

**Request body:** `{ "room_uuid": "..." }` — **optional** (omit to keep the reserved room).

**Behavior:** requires the target room's type to match the booked type, and no date-overlapping hold on that room by another booking — a booking holds its room from creation, including while merely `pending`. The room must not be in maintenance. Re-assigning the same room is a no-op 200. A move during a stay (the room actually changed while `checked_in`) pushes the guest a "room ready" notification; a pre-arrival assignment (`confirmed`) pushes nothing.

**Response `data`:** updated reservation with the room under `rooms[].room_uuid`/`room_number` and `status`.

**Failure `error_code`s:** `reservation_state` (422, `context.allowed`: `confirmed`, `checked_in` — wrong status, room type mismatch, or no room given or reserved), `room_out_of_order` (422, `context: { room_uuid, housekeeping_status }`), `room_already_assigned` (409, overlapping dates), `validation_failed` (422, bad `room_uuid`).

### GET /cms/reservations/{uuid}/available-rooms — `reservations.view`

**Purpose:** List rooms the reservation's booked room type could be assigned or checked into for its stay dates.

**Request:** No parameters. Read only.

**Behavior:** returns the rooms of the reservation's (first) room line's type that are active, not in maintenance, and not held by another booking over these dates — a merely `pending` hold (including one with no room named) still counts. If other holds already use every active room of the type, the list is empty. The currently assigned room comes first with `assigned: true`, then the rest ordered by room number.

**Response `data`:**
```json
{
  "room_type": { "uuid": "...", "name": { "en": "...", "ar": "..." } },
  "check_in": "2026-10-01", "check_out": "2026-10-04",
  "items": [
    { "uuid": "...", "number": "801", "floor": 8, "housekeeping_status": "available", "assigned": true }
  ]
}
```
Unpaginated.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `not_found` (404), `reservation_state` (422, the reservation has no room line).

### POST /cms/reservations/{uuid}/check-in — `reservations.create`

**Purpose:** Check the guest in.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `room_uuid` | string | no | Must exist. Defaults to the reserved room, else auto-picked. |
| `early_check_in` | boolean | no | Default `false`. Ignored once the ordinary stay window already admits today. |
| `reason` | string | required when `early_check_in` is `true` | Max 255. |

**Behavior:** only `confirmed`. The room is the one given, else the reserved one, else the first free room auto-picked (available before dirty, then lowest number; never maintenance) — dirty rooms are allowed to be checked into. The hotel-local date (server setting `HOTEL_TIMEZONE`, default `Asia/Damascus`) must satisfy `check_in <= today < check_out`; `early_check_in` with a reason admits exactly the day before `check_in`, logged in the activity log as `reservation.early_check_in` (properties `reason`, `check_in`, `today`) — the flag is ignored inside the ordinary window. The room's housekeeping status is not changed; the room board shows it occupied. The guest gets a "room ready" push.

**Response `data`:** the reservation with `rooms` and `guest` loaded, message "Guest checked in.".

**Failure `error_code`s:** `reservation_state` (422, `context: { status, allowed: ["confirmed"] }` — also room of another type, or no room line), `reservation_outside_stay_window` (422, `context: { check_in, check_out, today }`, all `Y-m-d`), `room_out_of_order` (422, `context: { room_uuid, housekeeping_status }`), `room_already_assigned` (409), `no_availability` (409, nothing free to auto-pick), `validation_failed` (422), `unauthorized` (401), `forbidden` (403), `not_found` (404).

Example `validation_failed` when `early_check_in` is sent without a reason: `"errors": { "reason": ["The reason field is required."] }`.

### POST /cms/reservations/{uuid}/check-out — `reservations.create` (+ `folios.settle` to force)

**Purpose:** Check the guest out.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `force` | boolean | no | Overrides an open-folio refusal. Only honoured for callers holding `folios.settle`. |
| `reason` | string | required when `force` is `true` | Max 255. |

**Behavior:** only `checked_in`; no date guard. The folio is generated (if missing) or refreshed (if open). An open folio is refused with `folio_unsettled` unless `force: true` comes from a `folios.settle` holder: then check-out proceeds, the folio stays open (settle it later with `POST /cms/folios/{folio}/settle`), and the override is logged as `reservation.check_out_forced` (properties `folio_uuid`, `folio_status`, `total_usd`, `reason`). `force` on an already-settled folio is ignored. Every assigned room moves to `dirty` (a system change, reason `check-out`). An internal `ReservationCheckedOut` event fires after commit.

**Response `data`:** the reservation plus `folio: { uuid, status, total_usd, open_disputes_count }`, message "Guest checked out.". An open line-item dispute never blocks check-out: only the folio status (`folio_unsettled`) does; `open_disputes_count` is a flag for the desk.

The reservation carries a staff-only `check_out_mode` (`none`, `staff_force`, `guest_express`; historical rows read `null`) recording how the gate was passed — see `GET /cms/reservations/{uuid}` above. Phase 6: checking out also opens a turnover housekeeping task per assigned room (due at `now + HOTEL_TURNOVER_SLA_MINUTES`, high priority when a confirmed same-day arrival already holds the room) — see *Module: Housekeeping*.

**Failure `error_code`s:** `reservation_state` (422, `context: { status, allowed: ["checked_in"] }`), `folio_unsettled` (422, `context: { folio_uuid, total_usd, can_force }` — the folio named by `folio_uuid` exists with current charges and can be settled with `POST /cms/folios/{folio}/settle`; show a "Force check-out" action only when `can_force` is `true`), `forbidden` (403, `force` sent without `folios.settle`, whatever the folio state), `validation_failed` (422), `unauthorized` (401), `not_found` (404).

### PATCH /cms/reservations/{uuid}/notes — `reservations.create`

**Purpose:** Keep free-text front-desk notes on a reservation.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `notes` | string or null | ✅ (key must be present) | Max 2000 characters (code points). Empty or whitespace-only text is stored as `null`. |

**Behavior:** works on a reservation of any status. `notes` is returned to staff only — guest routes never return it. Each change is kept in the activity log.

**Response `data`:** the reservation (`rooms`, `guest` loaded), message "Reservation notes updated.".

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `not_found` (404), `validation_failed` (422).

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

**Behavior:** creates a `Payment` record with `recorded_by` = your user; if the reservation was `pending`, transitions it to `confirmed` (no-op on already-confirmed/other states). Money taken here counts toward the folio balance (`balance_due_usd` subtracts reservation-level payments), so pre-departure deposits belong on this route. Once the reservation's folio is settled this route answers `folio_settled` (422, `context: { folio_uuid, settled_at }`) and records nothing.

**Response `data`** (message: "Payment recorded successfully."):
```json
{ "uuid": "...", "method": "cash", "amount_usd": "270.00", "status": "completed", "note": "...", "recorded_by": "staff-uuid", "created_at": "..." }
```

**Failure `error_code`s:** `folio_settled` (422, the reservation's folio is already settled), `payment_failed` (422, gateway rejected — unreachable with the current cash-only driver), `validation_failed` (422).

### Reservation status reference

| Status | Set by |
|---|---|
| `pending_verification` | Guest booking created via the public two-step flow, awaiting OTP |
| `pending` | Booking active, no room assigned |
| `confirmed` | Admin `confirm`, or a settled payment while pending |
| `checked_in` | Admin `POST /cms/reservations/{uuid}/check-in` |
| `checked_out` | Admin `POST /cms/reservations/{uuid}/check-out`, or guest approves express checkout (P8) |
| `cancelled` | Terminal — guest or admin cancel, or an expired soft-hold auto-releasing |

---

## Module: Event Inquiries (RFP triage)

All routes are `auth:users`. Since Phase 8 they are gated by the `events.*` group only; the seeded `events` preset is the only role that holds it (plus `super_admin`). `reception` and `concierge` no longer reach any of these routes. There are no `/events/...` alias routes.

| Verb + path | Permission | Body | Response |
|---|---|---|---|
| `GET /cms/event-inquiries` | `events.view` | — | paginated list (20/page) |
| `GET /cms/event-inquiries/{uuid}` | `events.view` | — | detail |
| `PATCH /cms/event-inquiries/{uuid}/status` | `events.manage` | `{ status }` | detail |
| `PATCH /cms/event-inquiries/{uuid}/assign` | `events.manage` | `{ user_uuid }` | detail |
| `PATCH /cms/event-inquiries/{uuid}/checklist/{item}` | `events.manage` | `{ done: boolean }` (required) | detail, message "Checklist updated." |
| `PATCH /cms/event-inquiries/{uuid}/notes` | `events.manage` | `{ staff_notes: string\|null }` | detail, message "Notes updated." |
| `PATCH /cms/event-inquiries/{uuid}/deposit` | `events.deposit` | `{ amount_usd, method?, note? }` + `Idempotency-Key` header | detail, message "Deposit recorded." |

### GET /cms/event-inquiries — `events.view`

Paginated, 20 per page. Each row:
```json
{
  "uuid": "...", "name": "...", "email": "...", "phone": "...", "company": "...",
  "event_type": "corporate", "event_date": "2026-08-01", "expected_guests": 120, "budget_usd": "5000.00",
  "notes": "...", "status": "quoted", "department": "sales", "assigned_to": null,
  "requirements": [ { "uuid": "...", "type": "av_equipment", "notes": "..." } ],
  "created_at": "...",
  "staff_notes": null, "deposit_status": "unpaid", "deposit_paid_at": null,
  "checklist_done_count": 1, "checklist_total": 5
}
```

The Phase 8 keys are additive: `staff_notes`, `deposit_status` (`unpaid` | `paid`), `deposit_paid_at` (ISO or `null`), `checklist_done_count` (ticked items, plus 1 when the deposit is paid) and `checklist_total` (always `5`). Existing keys are unchanged.

### GET /cms/event-inquiries/{uuid} — `events.view`

The detail is the list row plus the keys below. **Every `PATCH` on this resource returns the same detail shape.**

```json
{
  "...": "all list-row keys",
  "checklist": [
    { "item": "contract", "label": "...", "owner_department": "sales", "derived": false, "done": true,
      "completed_at": "2026-07-01T10:00:00Z", "completed_by": { "uuid": "...", "name": "..." } },
    { "item": "deposit", "label": "...", "owner_department": "events", "derived": true, "done": false,
      "completed_at": null, "completed_by": null }
  ],
  "deposit": { "status": "paid", "amount_usd": "500.00", "method": "cash", "paid_at": "...",
               "received_by": { "uuid": "...", "name": "..." }, "payment_uuid": "..." },
  "assigned_user": { "uuid": "...", "name": "..." },
  "guest": { "uuid": "...", "name": "..." },
  "event_space": { "uuid": "...", "name": "..." }
}
```

- `checklist[]` always has five entries, in the order listed under *Checklist*.
- `deposit`: when unpaid, every key except `status` is `null`.
- `assigned_user`, `guest` and `event_space` are `null` when absent.

### Checklist — `PATCH /cms/event-inquiries/{uuid}/checklist/{item}` — `events.manage`

| Order | `item` | Owner department | Notes |
|---|---|---|---|
| 1 | `contract` | sales | |
| 2 | `deposit` | events | **Derived** from the deposit state — not toggleable |
| 3 | `guarantee` | sales | |
| 4 | `beo` | events | |
| 5 | `av` | maintenance | |

- Body `{ "done": true|false }` — `done` is **required** and explicit; there is no blind toggle.
- Re-sending the current state is a `200` no-op (nothing is written). `done: false` on an item never ticked is also a `200` no-op.
- Unknown item (e.g. `/checklist/foo`) → `404 not_found`.
- `PATCH …/checklist/deposit` → `422 event_checklist_item_derived`, `context: { item: "deposit" }`.
- A cancelled inquiry → `422 inquiry_state`, `context: { status: "cancelled", allowed: ["new","in_review","quoted","confirmed"] }`. The checklist is editable in `new`, `in_review`, `quoted` and `confirmed`.
- Rows are created lazily on first tick; history lives in the activity log.

### Notes — `PATCH /cms/event-inquiries/{uuid}/notes` — `events.manage`

Body `{ "staff_notes": string|null }` — the key is required, max 5000 characters, `null` clears. Editable in every status, including `cancelled`. Last write wins (no concurrency token).

**`notes` and `staff_notes` are different fields.** `notes` is the guest's own RFP brief and is read-only for staff; `staff_notes` is the internal note staff edit.

### Deposit — `PATCH /cms/event-inquiries/{uuid}/deposit` — `events.deposit`

Body `{ "amount_usd": 500.00, "method"?: "cash", "note"?: "..." }` plus the **required** `Idempotency-Key` header.

- A deposit is a real payment row (payable = the inquiry) recorded through the cash payment action, with `deposit_status` set to `paid` and `deposit_paid_at` stamped. It never touches a folio.
- One deposit per inquiry: no partial deposits, no refunds, and **no auto-confirm** — the inquiry status stays `quoted` / `confirmed`.
- `amount_usd`: required, decimal with at most 2 places, between `0.01` and `99999.99`. It is not checked against `budget_usd`. `method`: optional, only `cash`. `note`: optional, max 1000.
- Allowed only while the inquiry is `quoted` or `confirmed`; otherwise `422 inquiry_state`, `context: { status, allowed: ["quoted","confirmed"] }`.
- **Replay:** the same `Idempotency-Key` with the same payload (same amount, method, note and the same staff user) → `200` with the same result and no second payment.
- Same key with a different payload or a different user → `409 idempotency_conflict`, `context: { idempotency_key }`.
- Missing or blank key → `422 validation_failed` with `errors.idempotency_key`.
- A second deposit under a new key → `422 event_deposit_already_recorded`, `context: { payment_uuid, paid_at }`.

> Revenue reporting (Phase 9) must segment payments by `payable_type`: event deposits are payments that are not folio payments.

### Error codes (this module)

| Code | HTTP | Context |
|---|---|---|
| `event_checklist_item_derived` | 422 | `{ item }` |
| `event_deposit_already_recorded` | 422 | `{ payment_uuid, paid_at }` |
| `inquiry_state` | 422 | `{ status, allowed }` — additive context, also on the status route |
| `idempotency_conflict` | 409 | `{ idempotency_key }` |
| `validation_failed` | 422 | `errors` keyed by field |
| `not_found` | 404 | unknown inquiry or unknown checklist item |

`payment_failed` (422) is reused if the cash payment is rejected.

### Dashboard handoff (Phase 8)

- Move from `/events/{id}` to `/cms/event-inquiries/{uuid}`. There are no alias routes.
- Notes: send `{ staff_notes }`. `notes` is the client's brief and is read-only.
- Checklist: body `{ done }` is required. An unknown item answers `not_found`, not the mock's `checklist_item_not_found`. `deposit` is not toggleable.
- Deposit: body `{ amount_usd, method?: 'cash', note? }` plus the `Idempotency-Key` header.
- `deposit{}` replaces the mock's `deposit_paid`, `deposit_amount` and `deposit_received_by`. Unpaid deposits have no amount (the agreed/required deposit is deferred).
- Gate the Events navigation on `events.view` (it was `tickets.view`).
- The public `POST /event-inquiries` receipt is unchanged and carries no staff keys.

### PATCH /cms/event-inquiries/{uuid}/status — `events.manage`

**Request body:** `{ "status": "in_review" | "quoted" | "confirmed" | "cancelled" }` (note: you cannot transition back to `new`).

**Allowed transitions:** `new`→`in_review`/`cancelled`; `in_review`→`quoted`/`cancelled`; `quoted`→`confirmed`/`cancelled`; `confirmed`→`cancelled`. `cancelled` is terminal.

**Failure:** `inquiry_state` (422) on an invalid transition — a distinct code from `reservation_state`, don't conflate them. Since Phase 8 it carries additive context `{ status, allowed }`.

### PATCH /cms/event-inquiries/{uuid}/assign — `events.manage`

**Request body:** `{ "user_uuid": "..." }` (required, must exist).

**Behavior:** sets `assigned_to`; if the inquiry was `new`, also auto-advances it to `in_review`.

### Department routing (informational — set at submit time, not editable)

`corporate`, `conference`, `product_launch` → `sales`; everything else → `events`.

---

## Module: Dining → Table reservations (`service_requests.view`)

### GET /cms/table-reservations — `service_requests.view`

Read-only list of restaurant table bookings (service bookings whose bookable is a restaurant table). `auth:users` + `service_requests.view`; held by the `kitchen`, `reception`, `concierge`, `housekeeping` and `events` presets. There are no staff write verbs yet, so rows carry no `allowed_statuses`.

| Param | Notes |
|---|---|
| `venue` | Dining venue uuid. Unknown uuid → empty page |
| `table` | Restaurant table uuid. Unknown uuid → empty page |
| `status` | `eq`, or `status[in]=pending,confirmed`; values `pending`, `confirmed`, `cancelled`, `completed` |
| `date` | `Y-m-d`, a **hotel-local** day |
| `from` + `to` | `Y-m-d`, hotel-local, inclusive; `to` ≥ `from` and the span is at most **31 days** |
| `sort` / `sort_dir` | `scheduled_at` (default) or `guest_count`; `asc` (default) / `desc` |
| `per_page` | Default 50, max 100 |

- With neither `date` nor `from`/`to`, the list defaults to the hotel-local **today**.
- `422` cases: `date` together with `from`/`to` (keyed `date`); only one of `from`/`to` (keyed on the missing one); a malformed date; `to` before `from`; a span over 31 days.
- Default order is `scheduled_at` ascending, then id.

**Row:**
```json
{
  "uuid": "...", "status": "confirmed",
  "scheduled_at": "2026-08-01T16:00:00Z", "local_date": "2026-08-01", "local_time": "19:00",
  "guest_count": 4, "special_request": null,
  "venue": { "uuid": "...", "name": "..." },
  "table": { "uuid": "...", "table_number": "T4", "capacity": 4 },
  "guest": { "uuid": "...", "name": "..." },
  "reservation": { "uuid": "...", "booking_code": "..." },
  "created_at": "..."
}
```

`scheduled_at` is the ISO UTC instant; `local_date` / `local_time` are the hotel-timezone rendering to display. `venue.name` is in the request locale. `venue`, `table`, `guest` and `reservation` can each be `null`. A trashed venue still renders; a hard-deleted table gives `table: null, venue: null`.

> **Known gap (PR-9):** the generic guest `POST /service-bookings` can still create a `restaurant_table` booking with a client-supplied `scheduled_at` that bypasses the table picker. Such rows appear in this list.

> **Guest-app timezone fix:** `POST /dining-venues/{venue}/table-reservations` takes a hotel-local `date` + `time`; the stored and returned `scheduled_at` is now the true UTC instant of that slot (19:00 local is 16:00Z, previously stored as 19:00Z). "Today" is the hotel-local day. Existing rows are not backfilled.

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

Response shapes mirror the fillable fields (translatable fields as locale maps, foreign keys exposed as `_uuid`, never the internal integer id). Menu categories nest their items under `items: []` when the relation is loaded; menu items expose `type` (the parent category's slug) and `photo` (first image). **`/cms/menu-items` is the only member of this group with media routes** (`POST`/`DELETE .../{uuid}/images` and `POST .../{uuid}/images/attach` — same contract as Module: CMS Content).

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

Approving a `confirmed` or `checked_in` stay also issues the guest's digital key (Phase 4, D-11) — display-only, NOT lock-grade, and never returned to staff. Re-approving a stay that already holds an active key keeps that key rather than minting a new one. Rejecting revokes it. Either way the guest gets a push (`notifications.check_in_approved`) that never contains the code.

---

## Module: Folios & Express Checkout (`folios.view`, `folios.post`, `folios.settle`, `folios.dispute`)

The guest's approve now runs the same check-out as the desk (rooms turn dirty, `ReservationCheckedOut` fires, logged as `reservation.check_out_guest_express`), still without a balance check. `POST /cms/folios/{reservation}/generate` returns the folio unchanged once the reservation is checked out.

**Ledger rules** (Phase 5):

- The folio is an append-only ledger. Corrections are **credit rows, never edits**: there is no `PATCH`/`DELETE` for a line item or a payment, and there never will be.
- Totals (`subtotal_usd`, `total_usd`) are recomputed from the stored rows under a row lock after every write, so two desks posting at once never lose a line.
- Regeneration (the generate route, the guest's `GET /folio`, check-out) **reconciles instead of rebuilding**: item uuids are stable across refreshes, and desk charges, credits, credited rows and disputed rows survive it unchanged. A generated line a credit or a dispute points at is frozen (never deleted, never repriced).
- `balance_due_usd` = `total_usd` minus completed payments whose payable is the folio **or its reservation**. It is signed (negative means the hotel owes the guest) and never clamped. Refunds are not subtracted yet.
- A dispute never moves money. A refund-worthy dispute is settled by posting a credit with `reverses_item_uuid`.
- Pre-departure money goes through `POST /cms/reservations/{uuid}/settle`; the folio payments route is the departure desk. A payment that brings the balance to zero settles the folio automatically (logged `folio.auto_settled`); there is no reopen.

### Idempotency-Key

A request header, at most 64 characters. **Optional** on `POST /cms/folios/{folio}/line-items`, **required** on `POST /cms/folios/{folio}/payments` (missing: `validation_failed` with `errors.idempotency_key` = "An Idempotency-Key header is required for this request."). A body field named `idempotency_key` is ignored; only the header counts.

- The same key with the same payload returns **200** and the folio as it is **now** (not a byte-identical copy of the first response), without writing again. This holds even after the folio has settled.
- The same key with a different payload returns `idempotency_conflict` (409, `context: { idempotency_key }`). For payments a different recording staff user counts as a different payload.
- Keys never expire. The settle routes do not take the header yet.

Generate one UUID per user action (per click on "Post" / "Take payment") and reuse it on every retry of that action.

### POST /cms/folios/{reservation}/generate — `folios.view`

**Purpose:** Generate/refresh a reservation's folio (idempotent — one folio per reservation). Refreshing reconciles the generated lines in place; see the ledger rules above.

**Request:** No body. **Response `data`:** Folio object (see shape below).

### GET /cms/reservations/{reservation}/folio — `folios.view`

**Purpose:** Read a reservation's folio. A pure read for a reservation in any status: it never generates or refreshes anything.

**Response `data`:** the folio shape below, message "Success.".

**Failure `error_code`s:** `folio_missing` (404, `context: { reservation_uuid, reservation_status }` — the reservation has no folio yet; call `POST /cms/folios/{reservation}/generate` first), `not_found` (404, unknown reservation), `unauthorized` (401), `forbidden` (403).

**Folio shape** (every folio response uses it: generate, settle, line items, payments, this read, the guest's `GET /folio` and approve):
```json
{
  "uuid": "…", "reservation_uuid": "…", "status": "open",
  "subtotal_usd": "309.00", "total_usd": "309.00",
  "approved_by_guest_at": null, "settled_at": null,
  "items": [
    {
      "uuid": "…", "description": "Room charge", "amount_usd": "300.00", "source_type": "reservation",
      "quantity": 1, "unit_price_usd": null, "posted_by": null, "posted_at": null,
      "reason": null, "reverses_item_uuid": null, "dispute": null
    },
    {
      "uuid": "…", "description": "Minibar", "amount_usd": "9.00", "source_type": "manual",
      "quantity": 2, "unit_price_usd": "4.50",
      "posted_by": { "uuid": "…", "name": "Front Desk" }, "posted_at": "2026-09-26T10:05:00+00:00",
      "reason": null, "reverses_item_uuid": null,
      "dispute": {
        "uuid": "…", "status": "open", "reason": "I did not order this.", "raised_by": "guest",
        "raised_at": "2026-09-26T11:00:00+00:00", "resolved_at": null, "resolution_note": null
      }
    }
  ],
  "payments": [
    { "uuid": "…", "method": "cash", "amount_usd": "100.00", "status": "completed", "note": null, "created_at": "…" }
  ],
  "paid_usd": "100.00",
  "balance_due_usd": "209.00",
  "open_disputes_count": 1
}
```

- Money fields are 2-decimal strings. `paid_usd` counts completed payments on the folio or its reservation; `payments[]` lists every payment of both (a pending one is listed but not counted) and omits `recorded_by`.
- `source_type` is `reservation`, `service_booking`, `service_request` (generated), `manual` (a desk charge) or `credit` (a negative line). `quantity`/`unit_price_usd`/`posted_by`/`posted_at` describe desk lines; generated lines carry `unit_price_usd: null`, `posted_by: null`, `posted_at: null`.
- `reverses_item_uuid` is the item a credit reverses (null otherwise). `dispute` is the item's latest dispute or `null`; `raised_by` is `guest` or `staff`.
- Items are ordered by posting order; payments by time (then insertion order).
- `reservation_uuid` is present on this read; other routes may omit it.

### POST /cms/folios/{folio}/line-items — `folios.post`

**Purpose:** Post a charge (minibar, laundry, damage) or a credit (a correction or goodwill) to an open folio. Header `Idempotency-Key` optional (see above).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `kind` | string | no | `charge` (default) or `credit` |
| `description` | string | ✅ | Max 255 |
| `quantity` | integer | no | 1–999, default 1 |
| `unit_price_usd` | string | ✅ | Up to 2 decimals, 0.01–99999.99. Send it as a string (`"4.50"`). |
| `reason` | string | required for a credit | Max 255 |
| `reverses_item_uuid` | string (uuid) | no | Credits only (`validation_failed` on a charge); must be an item of this folio |

**Behavior:** the line total is `quantity × unit_price_usd`, stored negative for a credit. A credit that reverses an item may not exceed what remains of that item after earlier credits against it (`folio_credit_exceeds_item`, checked first); no credit may take `balance_due_usd` below zero (`folio_credit_exceeds_balance`; reservation deposits count). A credit without `reverses_item_uuid` is a goodwill credit. The reservation's status is not a guard (a forced check-out's open folio still accepts lines); a settled folio refuses.

**Response `data`:** the full folio shape. **201** with message "Folio line item posted.", or **200** on an `Idempotency-Key` replay.

**Failure `error_code`s:** `folio_settled` (422, `context: { folio_uuid, settled_at }`), `folio_credit_exceeds_item` (422, `context: { item_uuid, remaining_usd, amount_usd }`), `folio_credit_exceeds_balance` (422, `context: { balance_due_usd, amount_usd }`), `idempotency_conflict` (409), `validation_failed` (422), `unauthorized` (401), `forbidden` (403), `not_found` (404).

### POST /cms/folios/{folio}/payments — `folios.settle`

**Purpose:** Take a manual payment against a folio at the departure desk. Header `Idempotency-Key` **required** (see above).

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `method` | string | ✅ | `cash` or `on_arrival` |
| `amount_usd` | string | ✅ | Up to 2 decimals, 0.01–99999.99, and at most `balance_due_usd` |
| `note` | string | optional | Max 1000 |

**Behavior:** row-locks the folio; a settled folio refuses; an amount above `balance_due_usd` is refused (so a folio whose balance is already `0.00`, e.g. covered by a reservation deposit, refuses any payment — close it with the settle route instead). The payment that brings the balance to `0.00` settles the folio (`status: "settled"`, `settled_at` set, logged `folio.auto_settled`), after which it passes the check-out gate.

**Response `data`:** the full folio shape. **201** with message "Folio payment recorded.", or **200** on a replay (also after the payment auto-settled the folio).

**Failure `error_code`s:** `folio_overpayment` (422, `context: { balance_due_usd, amount_usd }`), `folio_settled` (422, `context: { folio_uuid, settled_at }`), `idempotency_conflict` (409), `validation_failed` (422, including a missing `Idempotency-Key`), `unauthorized` (401), `forbidden` (403), `not_found` (404).

### PATCH /cms/folios/{folio}/line-items/{item}/dispute — `folios.dispute`

**Purpose:** Raise a dispute on a line item on the guest's behalf, or close the open one as resolved or rejected.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `action` | string | ✅ | `raise`, `resolve` or `reject` |
| `reason` | string | required for `raise` | Max 500 |
| `note` | string | required for `resolve`/`reject` | Max 1000. The guest sees it as `resolution_note`. |

**Behavior:** the item must belong to the folio in the URL (404 otherwise). One open dispute per item; a new one may be raised after the previous one is resolved or rejected. Allowed on open and settled folios. A decision never moves money — to refund a disputed charge, post a credit with `reverses_item_uuid` through the line-items route. An open dispute never blocks check-out.

**Response `data`:** the item with its latest `dispute`, message "Dispute raised." / "Dispute resolved." / "Dispute rejected.".

**Failure `error_code`s:** `folio_item_dispute_open` (422, `context: { item_uuid, dispute_uuid }` — raise while one is open), `folio_dispute_state` (422, `context: { item_uuid, status }` — resolve/reject with no open dispute; `status` is the latest dispute's status or `null`), `validation_failed` (422), `unauthorized` (401), `forbidden` (403), `not_found` (404). The guest raises their own disputes through `PATCH /folio/items/{item}/dispute` (mobile guide).

### POST /cms/folios/{folio}/settle — `folios.settle`

> ⚠️ **Contract change (Phase 5):** settling an already-settled folio now answers `folio_settled` (422, context folio_uuid and settled_at) instead of `reservation_state`.

**Purpose:** Close a folio, recording a final cash/on-arrival payment when money is still due.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `method` | string | only with `amount_usd` | `cash` or `on_arrival` |
| `amount_usd` | number or string | optional | Up to 2 decimals, min 0.01 |
| `note` | string | optional | Max 1000 |

**Behavior:** row-locks the folio. When `balance_due_usd` is already `0.00` or below (e.g. a prepaid stay whose reservation deposit covers the total), the folio closes **without a payment** — even if an amount was sent — with message "Folio settled; nothing was due, so no payment was recorded." (logged `folio.settled_no_payment`). Otherwise an amount is required (`validation_failed`, `errors.amount_usd`) and is recorded as before, then the folio closes with message "Folio settled.". Either way `status: "settled"` and `settled_at` are set and the folio passes the check-out gate. Does not touch reservation status.

**Response `data`:** the full folio shape (not a Payment object).

**Failure `error_code`s:** `folio_settled` (422, `context: { folio_uuid, settled_at }`), `payment_failed` (422), `validation_failed` (422).

---

## Module: Chat (P9)

Guest↔staff messaging. `tickets.view` reads, `tickets.respond` replies (both seeded since P0). Since Phase 7 the `events`, `reception` and `concierge` presets all hold both, so reception and concierge staff can read and answer guest chat; kitchen and housekeeping cannot. A support ticket's `conversation_uuid` is the conversation to reply into.

- `GET /api/cms/conversations` — all conversations, most recent first (`tickets.view`).
- `GET /api/cms/conversations/{uuid}/messages` — paginated history, oldest first (`tickets.view`).
- `POST /api/cms/conversations/{uuid}/messages` — reply; body `{ "body"?: string, "attachment"?: file }` (`tickets.respond`). Claims the conversation (sets `assigned_user_id` to the replying staff member) on the first staff reply if unassigned.

Mirrors to Firestore the same way as the guest side (see `API_GUIDE_MOBILE.md`) — subscribe for live updates.

No push notification is sent to staff (the dashboard is web; it live-subscribes to Firestore instead of FCM).

---

## Module: Operations Queue & Dashboard (P10)

The unified read+assign layer over `service_requests`, `tickets` (staff-created since Phase 7 — see *Module: Support Tickets*; chatbot-sourced rows arrive with P11) and, since Phase 6, `housekeeping_tasks` — a single registry (`OperationsQueueType`) drives every type-dependent rule below. Every mutation mirrors live to the same Firestore `ops_queue` collection service-request creation already writes to (see `API_GUIDE_MOBILE.md`). The queue only ever shows **active** work — completed/cancelled service requests, resolved/closed tickets and closed housekeeping tasks (`done`/`cancelled`) are excluded, not just paginated away.

- `GET /api/operations/queue` — merged, newest-first, paginated. Requires `service_requests.view` **or** `tickets.view` **or** `housekeeping.view`; each of the three tables is included only if the caller holds its own `.view` permission (holding just one or two silently omits the rest, not a 403). Each item: `{ type: "service_request"|"ticket"|"housekeeping_task", queue_type: "service-requests"|"tickets"|"housekeeping-tasks", uuid, subject, department, status, priority, assigned_user_uuid, created_at, room_number, allowed_statuses }`. `subject` is the service request's `type`, the ticket's `subject`, or the housekeeping task's `type`; `department` is always `housekeeping` for a task row. `priority` is always a string (`low`/`normal`/`high`) — ticket priority is stored as a 1–3 int internally but normalized here so the field never changes type between rows. `room_number` (string or `null`) and `allowed_statuses` (the D-05-style transition targets from the row's current status) are on **every** row regardless of type — a service request's `room_number` comes from its reservation's first assigned room, and a ticket's is the room linked to the ticket (`null` when none; a soft-deleted room still shows its number). **Build every queue path from `queue_type`** — `/operations/queue/{queue_type}/{uuid}/assign|status|claim` — there are no `{id}` aliases. Ticket rows now include the `in_progress` and `waiting_guest` statuses (active statuses are `open`, `assigned`, `in_progress`, `waiting_guest`) and advertise the enforced `allowed_statuses` (never `assigned`). Each of the three types is fetched with its own 500-row cap before the merge (3 × 500 at most); a type with more than 500 open rows is silently truncated (a SQL `UNION` is deferred).
- `PATCH /api/operations/queue/{type}/{uuid}/assign` — `{ "user_uuid": "..." }`. `{type}` is `service-requests`, `tickets` or `housekeeping-tasks`. Permission differs by type: `service_requests.assign` / `tickets.assign` / `housekeeping.assign`. Assigning a `pending` housekeeping task moves it to `assigned` (same rule as the dedicated `/housekeeping/tasks/{task}/assign` verb); assigning an already-assigned or in-progress task only swaps the assignee; a `done`/`cancelled` task answers `422 housekeeping_task_closed`. Since Phase 7 every assign verb also checks **assignee eligibility** (see below) and a ticket assign runs the ticket assign writer (open → `assigned`, timeline row, `ticket_closed` on a resolved/closed ticket); a service request assign answers `422 service_request_closed` on a terminal request.
- `PATCH /api/operations/queue/{type}/{uuid}/status` — `{ "status": "...", "reason"?: "..." }`, validated against that item's own status enum. Permission: `service_requests.update` / `tickets.respond` / `housekeeping.update` (ticket status changes reuse the chat-reply permission — resolving a ticket is a form of responding to it). A housekeeping task enforces the D-05 transition table and answers `422 housekeeping_task_transition_invalid` (`context: { from, to, allowed }`) on an invalid move — service requests have no server-side transition table. **Tickets now do** (additive 422s on this existing route): the ticket arm runs the same lifecycle writer as `PATCH /support-tickets/{ticket}/status` — an invalid move or `status: "assigned"` is `422 ticket_transition_invalid` (`context: { from, to, allowed }`), closing a ticket that is not `resolved` or reopening a `resolved` ticket needs a non-blank `reason` (`422 validation_failed` on `reason`), the move writes the ticket timeline, and a move to `in_progress` on an unassigned ticket self-assigns the caller. On this route `reason` stays capped at 255 characters (1000 on `/support-tickets/{ticket}/status`).
- `GET /api/dashboard/summary` — `{ service_requests?: {status: count}, tickets?: {status: count}, event_inquiries?: {status: count}, housekeeping_tasks?: {status: count} }`. Each block appears only if you hold the matching `.view` permission (`tickets.view` unlocks both `tickets` and `event_inquiries` — event inquiries reuse the same permission P6 already gated their own admin routes with; `housekeeping.view` unlocks `housekeeping_tasks`). No permissions → `{}`, not a 403. The `tickets` block now counts `in_progress` and `waiting_guest` alongside the other active statuses.

**Staff create tickets since Phase 7** (`POST /support-tickets`); the chatbot will add `source: "chatbot"` rows in P11 without any change to the queue.

### Assignee eligibility (Phase 7)

Every assign verb — `PATCH /operations/queue/{queue_type}/{uuid}/assign` for all three types, `PATCH /support-tickets/{ticket}/assign`, the ticket escalation target and `PATCH /housekeeping/tasks/{task}/assign` — refuses an assignee who could not then work the item: the user must be active, of type `staff` or `super_admin`, and hold the queue type's **work** permission (`service_requests.update` for service requests, `tickets.respond` for tickets, `housekeeping.update` for housekeeping tasks; a super admin always qualifies). Otherwise `422 assignee_not_eligible` with `context: { user_uuid, required_permission }`. Claiming skips this check (you claim for yourself, and the work-permission gate on the claim route already applies).

Which seeded presets can be assigned each queue type:

| Queue type | Assignable | Not assignable |
|---|---|---|
| `service-requests` | `reception`, `kitchen`, `housekeeping`, `concierge` | `events`, `content_editor`, `content_manager` |
| `tickets` | `events`, `reception`, `concierge` | `kitchen`, `housekeeping`, `content_editor`, `content_manager` |
| `housekeeping-tasks` | `housekeeping` | every other preset, including `reception` |

Before Phase 7 any staff user could be assigned. Newly refused: `reception` for housekeeping tasks (it holds `housekeeping.assign` — it may hand tasks off — but not `housekeeping.update`, so it can be the assigner but no longer the assignee); `events` for service requests (no `service_requests.update`); and `kitchen`/`housekeeping` for tickets (no `tickets.*`). Reception stays assignable to service requests. Use `GET /operations/staff` below to populate the assignee picker instead of hard-coding presets.

### Claim — `PATCH /api/operations/queue/{queue_type}/{uuid}/claim`

No body; `auth:users`. "Take this item for myself." The caller must hold the type's **work** permission (`service_requests.update` / `tickets.respond` / `housekeeping.update`) — the assign permission alone is `403`. The permission is checked before the item is resolved, so a missing permission is `403` even for an unknown uuid; an unknown `{queue_type}` or uuid is `404 not_found`. Response `200`: the refreshed queue row (same shape as the list items).

Inside the item's row lock the order is: **closed check → who owns it → assign**.

| Item state | Result |
|---|---|
| Terminal (closed) | `422` with the type's own code, `context: { status }`, even when the caller already owns it — `ticket_closed` (tickets), `housekeeping_task_closed` (housekeeping tasks), `service_request_closed` (service requests). There is **no shared closed code**; branch per `queue_type`. |
| Unassigned | Assigned to the caller, message "Queue item claimed." A ticket `open` → `assigned` (any other active status keeps its status) with an `assignment` timeline row whose `meta` is `{ "claim": true }`; a housekeeping task `pending` → `assigned` with history reason `claimed`; a service request keeps its status. |
| Already yours | `200` no-op, message "This item is already assigned to you." Nothing is written and nothing is mirrored. |
| Someone else's | `409 queue_item_already_claimed`, `context: { assigned_user_uuid }`. A claim never overrides another assignee — supervisors re-assign with `PATCH …/assign`. |

Claiming no longer moves the item to `in_progress`. A deactivated user's tokens are revoked at deactivation, so an old token gets `401`, not a claim.

**Race caveat (MySQL only):** the claim runs under a `SELECT … FOR UPDATE` row lock. SQLite ignores `lockForUpdate`, so the test suite proves only that the `for update` clause is issued; two simultaneous claims are serialised on MySQL (production) only.

### GET /api/operations/staff — assignable-staff directory

Gate: `auth:users` + `permission:service_requests.view|tickets.view|housekeeping.view` (the same as the queue list). There is no `/operations/queue/staff` alias (`404`).

| Query | Notes |
|---|---|
| `type` | `service-requests`, `tickets` or `housekeeping-tasks` — holders of that type's work permission. |
| `permission` | **Only** `service_requests.update`, `tickets.respond` or `housekeeping.update`; any other value is `422 validation_failed`. When `type` and `permission` are both sent, both apply. |
| `department` | A department value: `kitchen`, `housekeeping`, `concierge`, `reception`, `events`, `sales`, `maintenance` — users holding the role of that name. `sales` and `maintenance` have no role, so they return an empty list. |
| `search` | Case-insensitive name substring (wildcards escaped), max 100 characters. |

Rows are active users of type `staff` or `super_admin`, ordered by name. Super admins match the `type`/`permission` filters but are excluded by `department` unless they hold that role. **Response `data`:** `{ "items": [ { "uuid", "name", "type", "departments": ["housekeeping"] } ], "meta": { "count", "truncated" } }` — unpaginated, capped at 200; `meta.count` is the number of rows returned and `meta.truncated` is `true` when more matched. A row carries exactly those four keys — no email, no roles, no permissions. `departments` is always an array and is derived from the user's **role names** that equal a department value, so renaming a role silently changes both the `department` filter and each row's `departments`.

**Firestore mirror (D-11b):** `ops_queue` now carries a third status vocabulary. A housekeeping-task change mirrors to document id `housekeeping_task_{uuid}` (versus `service_request_{uuid}` and `ticket_{uuid}`) with payload `{ uuid, department, status, priority, guest_uuid, assigned_user_uuid, created_at, task_type, room_uuid, room_number }` — no guest name or phone (task rows carry room and stay identifiers only). Subscribers must branch on the document id prefix to know which status vocabulary a row's `status` belongs to. Ticket changes write `ticket_{uuid}` (payload unchanged) from a **queued listener** (`MirrorTicketToFirestore`) after the transaction commits, so a queue worker must be running for ticket mirrors to appear.

---

## Module: Guests (`guests.view` · `guests.edit`)

Staff guest directory, profile, notes and preferences (Phase 4). Seeded on the `reception` and `concierge` presets only. **Staff cannot edit guest identity** (name, phone, email) — there is no `PATCH /guests/{guest}` and none is planned; only notes and preferences are staff-editable (D-13).

### GET /guests — `guests.view`

**Purpose:** The guest directory (search + stay filter), paginated (default 15, cap 100).

**Request query:**

| Param | Notes |
|---|---|
| `search` | Case-insensitive substring across `name`, `first_name`, `last_name`, `phone`, `email`. |
| `phone`, `email` | Operators `eq`, `like` (`?phone[like]=0912`, `?email=a@b.com`). |
| `preferred_locale` | Operators `eq`, `in`. |
| `sort` / `sort_dir` | `sort` one of `name`, `last_name`, `created_at`; `sort_dir` `asc` (default) / `desc`. Default order (no `sort`): `last_name asc, name asc, id asc`. |
| `stay_status` | One of `in_house`, `departing`, `arriving`, `upcoming`, `past`, `none`, evaluated against the hotel-local date. Non-exclusive predicates (an `in_house` filter also lists guests departing today) — the row's own `stay_status` below is the precedence-based one. Empty value = no filter. **Any other value is `422` `validation_failed` on `stay_status`.** |
| `account_status` | `active` or `deleted`; operators `eq`, `in` (`?account_status=deleted`, `?account_status[in]=active,deleted`). **Deleted (erased) accounts are excluded by default**; send `deleted` for only them or `in` for both. |
| `per_page` | Default 15, hard cap 100. |

Guests with no reservations are listed (`stay_status: "none"`). Since Phase 9.1 every row also carries `account_status` (`"active"` | `"deleted"`) and `account_deleted_at` (ISO-8601 or `null`), so each row has 15 keys.

**Row shape** (13 keys, plus `account_status` and `account_deleted_at`):
```json
{
  "uuid": "...", "name": "...", "first_name": "...", "last_name": "...",
  "phone": "...", "phone_country": "SY", "phone_verified": true,
  "email": "...", "email_verified": true, "preferred_locale": "en",
  "stay_status": "in_house",
  "current_reservation": { "uuid": "...", "booking_code": "CARL-...", "status": "checked_in", "check_in": "2026-09-25", "check_out": "2026-09-28", "room_number": "812" },
  "created_at": "..."
}
```
`current_reservation` is `null` when the row's `stay_status` is `none`. `stay_status` precedence when several would apply: `departing > in_house > arriving > upcoming > past > none`.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `validation_failed` (422, `errors.stay_status` on an unknown value).

### GET /guests/{uuid} — `guests.view`

**Purpose:** The "guest at the counter" profile — one bounded round trip: identity, stats, preferences, the target reservation in full, the derived pre-arrival checklist, recent stay history and recent notes. Staff-only — never reused on a guest route.

**Response `data`** (21 keys):
```json
{
  "uuid": "...", "name": "...", "first_name": "...", "last_name": "...",
  "phone": "...", "phone_country": "SY", "phone_verified": true,
  "email": "...", "email_verified": true, "preferred_locale": "en",
  "created_at": "...", "stay_status": "in_house",
  "stats": { "stays_count": 3, "cancelled_count": 0, "last_check_out": "2026-08-01" },
  "preferences": { "bed_type": "king", "pillow_type": "firm", "floor_preference": "high", "other": "...", "updated_at": "..." },
  "current_reservation": {
    "uuid": "...", "booking_code": "CARL-...", "status": "checked_in",
    "check_in": "2026-09-25", "check_out": "2026-09-28", "checked_in_at": "...",
    "arrival_time": "18:30", "online_check_in_submitted_at": "...",
    "room": { "uuid": "...", "number": "812", "floor": 8 },
    "room_type": { "uuid": "...", "name": { "en": "...", "ar": "..." } },
    "check_in_approval": { "uuid": "...", "status": "approved", "notes": null, "approved_by": { "uuid": "...", "name": "..." }, "updated_at": "..." },
    "documents": [ { "uuid": "...", "type": "passport", "created_at": "..." } ],
    "digital_key": { "issued_at": "...", "expires_at": "...", "revoked_at": null, "active": true }
  },
  "pre_arrival_checklist": { "reservation_uuid": "...", "complete": false, "items": [ "...six items, see below..." ] },
  "stay_history": [ { "uuid": "...", "booking_code": "...", "status": "checked_out", "check_in": "...", "check_out": "...", "nights": 2, "room_number": "801", "room_type": { "uuid": "...", "name": { "en": "...", "ar": "..." } }, "total_usd": "270.00", "checked_in_at": "...", "checked_out_at": "..." } ],
  "stays_total": 4, "has_more": false,
  "notes": [ { "uuid": "...", "body": "...", "author": { "uuid": "...", "name": "..." }, "created_at": "..." } ],
  "notes_count": 6
}
```

- Phase 9.1 adds `account_status` (`"active"` | `"deleted"`) and `account_deleted_at` (ISO-8601 or `null`). A deleted (erased) guest still answers `200` and keeps its reservations, but names, phone and email are `null`. Guests delete their own account via `DELETE /auth/guest/me` (guest app); the row is anonymized, never removed. Receipt PDFs of an erased guest name the payer from the reservation's last name.
- `current_reservation` is the in-house stay if any, else the next arrival; `null` when neither exists.
- `documents` is metadata only — no `file_path`, no URL. `digital_key` here is the **staff shape** (`{issued_at, expires_at, revoked_at, active}`) — the code itself never appears in a staff response.
- `pre_arrival_checklist` is derived, never stored, and is `null` without a target reservation. Six items, in order, each `{key, done, ...detail}`: `documents_uploaded` (`count`), `check_in_approved` (`status`), `preferences_set`, `arrival_time_set` (`arrival_time`), `room_assigned` (`room_number`), `digital_key_issued` (`expires_at`). `complete` is true only when all six are done; a guest declaring `floor_preference: "any"` still counts as `preferences_set`.
- `stay_history` is the 25 most recent reservations of any status by `check_in desc`; `stays_total` counts every non-cancelled reservation and `has_more` is true when history was truncated to the 25-row window.
- `notes` is the 10 newest; `notes_count` is the guest's total note count. The full list is `GET /guests/{uuid}/notes` below.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `not_found` (404).

### GET /guests/{uuid}/notes — `guests.view`

**Purpose:** The guest's full note history, paginated newest first (default 15, cap 100; same tiebreak as the profile's `notes`: `created_at desc, id desc`).

**Response `data`:** paginated `items` of `{ uuid, body, author: {uuid, name} | null, created_at }`.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `not_found` (404).

### POST /guests/{uuid}/notes — `guests.edit`

**Purpose:** Add a free-text front-desk note about a guest. Append-only — there is no edit or delete route.

**Request body:** `{ "body": "..." }` — required string, max 2000.

**Response:** HTTP 201, `{ uuid, body, author: {uuid, name}, created_at }`, message `"Guest note added."`.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `not_found` (404), `validation_failed` (422, `errors.body`), `guest_account_deleted` (422, the guest deleted their account — "This guest account has been deleted.").

### PATCH /guests/{uuid}/preferences — `guests.edit`

**Purpose:** Staff records a guest's room preferences on their behalf (e.g. taken over the phone).

**Request body:** any of the four keys, PATCH semantics — a present key is written, an explicit `null` clears it, an absent key is left untouched. A body with **none** of the four keys is `422` on `errors.preferences`.

| Field | Type | Notes |
|---|---|---|
| `bed_type` | string, nullable | `king`, `queen`, `double`, `twin`, `single` — **`extra` is refused** (inventory-only, not a guest preference). |
| `pillow_type` | string, nullable | `soft`, `medium`, `firm`, `feather`, `hypoallergenic`. |
| `floor_preference` | string, nullable | `low`, `high`, `any` (`any` is a positive choice, not "no preference"). |
| `other` | string, nullable | Max 500. |

**Response `data`:** `{ bed_type, pillow_type, floor_preference, other, updated_at }` — the same shape `PATCH /auth/guest/preferences` returns on the guest side. Message `"Preferences updated."`.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `not_found` (404), `validation_failed` (422 — including an unknown enum value, `extra` for `bed_type`, or `errors.preferences` on an empty body), `guest_account_deleted` (422, the guest deleted their account).

---

## Module: Front Desk (`rooms.status` · `reservations.view`)

### GET /front-desk/room-board — `rooms.status` or `reservations.view`

**Purpose:** Live housekeeping and occupancy state for every active room, for the front-desk board screen.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `date` | string (Y-m-d) | no | default today UTC |
| `status` | string | no | `available`, `dirty` or `maintenance` |
| `floor` | integer | no | 0–200 |
| `room_type` | string (uuid) | no | an unknown uuid returns an empty list |

**Behavior:** Unpaginated, active rooms only, ordered by floor ascending (rooms without a floor first), then number. Occupancy rules: `occupied` when a checked-in stay covers the night of `date`; `stayover` when that stay began before `date`; `departing_today` when a checked-in stay ends on `date` — that room reads `vacant` because occupancy is counted per night (the guest is still in house until the check-out verb of Phase 3); `arriving_today` when any booking that is neither cancelled nor checked out starts on `date`; `reservation` is the checked-in stay, else today's arrival, else null. `status_changed_by` is null for rooms never moved through the status endpoint.

**Response `data`:** `{ date, items: [...] }`. Each item has exactly these keys, in order:

```json
{
  "uuid": "...", "number": "101", "floor": 1,
  "room_type": { "uuid": "...", "name": {"en":"...","ar":"..."} },
  "housekeeping_status": "dirty",
  "status_changed_at": "2026-09-26T08:00:00+00:00",
  "status_changed_by": { "uuid": "...", "name": "..." },
  "occupancy": "occupied",
  "arriving_today": false, "departing_today": false, "stayover": true,
  "reservation": { "uuid": "...", "guest_name": "...", "check_in": "2026-09-24", "check_out": "2026-09-28", "status": "checked_in" }
}
```

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `validation_failed` (422).

---

### GET /front-desk/availability-grid — `reservations.view`

**Purpose:** Free/booked/out-of-order counts per room type over a date window, for the dashboard's availability grid.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `from` | string (Y-m-d) | no | default today, not earlier than today minus 365 days |
| `days` | integer | no | 1–31, default 14 |

**Behavior:** `free` equals what `GET /public/availability` returns for that room type and night; `out_of_order` counts rooms in maintenance today, is repeated on every cell and is never subtracted from `free`.

**Response `data`:** `{ from, days, room_types: [ { uuid, name, total, cells: [ { date, free, booked, out_of_order } ] } ] }`.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `validation_failed` (422).

---

### GET /front-desk/rates-grid — `reservations.view`

**Purpose:** Nightly rate per room type over a date window, for the dashboard's rates grid. Read-only: edit rates through the CMS pricing rules.

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `from` | string (Y-m-d) | no | default today, not earlier than today minus 365 days |
| `days` | integer | no | 1–31, default 14 |

**Behavior:** `rate_usd` is a two-decimal string equal to the one-night booking quote for that date: active pricing rules whose window includes the date (inclusive on both ends) applied in the quote's order; `rule_scope` is the last applied rule's scope or null, and a `weekend` rule applies on every date of its window.

**Response `data`:** `{ from, days, room_types: [ { uuid, name, base_price_usd, cells: [ { date, rate_usd, rule_scope } ] } ] }`.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403), `validation_failed` (422).

---

## Module: Housekeeping (`housekeeping.view` · `housekeeping.assign` · `housekeeping.update`)

The task board behind the room-status lifecycle (Phase 6, D-01..D-11). Every write goes through one of three single writers, so lock order, history rows and the Firestore mirror live in exactly one place each; there is no `PATCH`/`DELETE` outside the two verbs below and no bulk endpoint.

- `GET /housekeeping/tasks` — `housekeeping.view`.
- `GET /housekeeping/tasks/{task}` — `housekeeping.view`.
- `POST /housekeeping/tasks` — `housekeeping.assign`.
- `PATCH /housekeeping/tasks/{task}/assign` — `housekeeping.assign`.
- `PATCH /housekeeping/tasks/{task}/status` — `housekeeping.update`.

### GET /housekeeping/tasks

**Request query (filters, all optional, blank = no filter):**

| Param | Notes |
|---|---|
| `status` | eq/in — `pending`, `assigned`, `in_progress`, `done`, `cancelled` |
| `type` | eq/in — `turnover`, `stayover`, `inspection`, `request` |
| `priority` | eq/in — `low`, `normal`, `high` |
| `room` | a room number or a room uuid |
| `assignee` | a staff uuid, or `unassigned`; anything else is `422` |
| `due_at[gte\|lte]` | an instant, compared in UTC |
| `due_date` | `Y-m-d`, the hotel-local day of `due_at` (`HotelClock::dayWindow()`); anything else is `422` |

Sortable: `due_at`, `created_at`, `priority` (by rank high > normal > low, not alphabetically), always with `id` as the tiebreak. Default order (no `sort`): `due_at` ascending with undated tasks last, then `id` ascending.

**Task shape:**
```json
{
  "uuid": "...", "type": "turnover", "status": "pending", "priority": "high", "notes": null,
  "room": { "uuid": "...", "number": "101", "floor": 1, "status": "dirty" },
  "reservation": { "uuid": "...", "booking_code": "...", "check_out": "2026-09-28" },
  "assigned_user": null,
  "service_request_uuid": null,
  "due_at": "2026-09-27T14:00:00+00:00", "started_at": null, "completed_at": null,
  "created_at": "...", "updated_at": "...",
  "allowed_statuses": ["assigned", "in_progress", "cancelled"]
}
```
`show` also returns `history` — the last 10 status-history rows, newest first, each `{ from_status, to_status, reason, changed_by, created_at }`. `allowed_statuses` lists the D-05 transition targets from the current status, so the dashboard never re-implements the state machine — an empty array on `done`/`cancelled` means hide every status action.

### POST /housekeeping/tasks — `housekeeping.assign`

**Purpose:** Create a turnover, stayover or inspection task by hand — also the recovery path if a listener ever fails to open one.

**Request body:** `{ "room_uuid", "type": "turnover"|"stayover"|"inspection", "due_at"?, "priority"?, "notes"? }`. `type: request` is refused — request tasks are system-made from a service request.

**Response:** `201` with message `custom.messages.housekeeping_task_created` when a new task opens; `200` with `custom.messages.housekeeping_task_exists` and the existing open task of that room+type when one already exists (D-02 dedupe — one open task per room and type). No `DELETE`.

### PATCH /housekeeping/tasks/{task}/assign — `housekeeping.assign`

**Request body:** `{ "user_uuid" }`. `pending → assigned` (writes a history row, reason `assigned`); `assigned`/`in_progress` swaps the assignee (activity log only, no history row); `done`/`cancelled` → `422 housekeeping_task_closed`.

### PATCH /housekeeping/tasks/{task}/status — `housekeeping.update`

**Request body:** `{ "status", "reason"? }`.

**Transition table (D-05):**

| From | Allowed to |
|---|---|
| `pending` | `assigned`, `in_progress`, `cancelled` |
| `assigned` | `in_progress`, `cancelled` |
| `in_progress` | `done`, `cancelled` |
| `done`, `cancelled` | none (terminal) |

An invalid move is `422 housekeeping_task_transition_invalid` with `context: { from, to, allowed }` and writes nothing. Moving to `in_progress` stamps `started_at` (once) and self-assigns the task to the calling staff member if it is still unassigned; moving to `done` stamps `completed_at`/`completed_by`.

**Room coupling (D-07), turnover tasks only:** `done` on a `dirty` room turns it `available` through the same `UpdateRoomStatusAction` the room board uses (reason `turnover`); an `available` room is left alone; a `maintenance` room stays in maintenance (activity property `room_left_in_maintenance: true`). `cancelled` never touches the room, and no other task type ever writes `rooms.status`. Conversely, marking a room `dirty → available` on the front-desk room board (any reason other than `turnover`) closes that room's open turnover task straight to `done` (history reason `room_board`) — the one place a task skips the transition table above, because the board is reporting a physical fact, not asking permission.

**Automatic tasks:** a `turnover` task opens per assigned room when a stay checks out (`due_at = now + HOTEL_TURNOVER_SLA_MINUTES`, default 120; `priority: high` when a confirmed same-day arrival already holds the room, else `normal`). A `request` task opens when a service request routes to the `housekeeping` department, linked 1:1 to it via `service_request_uuid`; the task reaching `done` completes the request (if it is still active), and the request reaching `completed`/`cancelled` cancels the task — either direction is loop-safe. Both listeners are synchronous and never throw on business failure; a failure is logged, and `POST /housekeeping/tasks` (or `php artisan housekeeping:reconcile`, run by hand) recovers a missed one.

**Failure `error_code`s:** `housekeeping_task_transition_invalid` (422), `housekeeping_task_closed` (422), `validation_failed` (422), `unauthorized` (401), `forbidden` (403), `not_found` (404).

---

## Module: Service Request Board (`service_requests.view`)

Read-only staff view of the guest service-request table (Phase 6, D-15..D-17) — a richer companion to the operations queue's service-request rows, for a screen dedicated to requests. **Writes are not here**: progress a row through `PATCH /operations/queue/service-requests/{uuid}/assign|status` (no write aliases under this path, D-17).

- `GET /cms/service-requests` — `service_requests.view`.
- `GET /cms/service-requests/{serviceRequest}` — `service_requests.view`.

**Request query (filters, all optional):** `status`, `department`, `priority`, `type` (eq/in); `created_at[gte|lte]`; `assignee` (uuid or `unassigned`); `room` (room number, via the reservation's assigned rooms); `date` (`Y-m-d`, hotel-local day of `created_at`); `guest` (a guest uuid exact, else a case-insensitive fragment of name/first/last name). Sortable: `created_at`, `priority`, `status`, with `id` descending as the tiebreak. Default order: `created_at` descending.

**Row shape:**
```json
{
  "uuid": "...", "type": "late_checkout", "category_code": "late_checkout",
  "department": "reception", "status": "new", "priority": "normal", "notes": null,
  "created_at": "...", "updated_at": "...",
  "guest": { "uuid": "...", "name": "..." },
  "reservation": { "uuid": "...", "booking_code": "...", "check_out": "2026-09-28", "room_number": "812" },
  "service_item": { "uuid": "...", "name": {"en": "...", "ar": "..."}, "expected_minutes": 15, "price_usd": null },
  "assigned_user": null,
  "housekeeping_task": null
}
```
`category_code` is the catalogue category snapshotted into `type` when the request came from `GET /public/service-catalog`, else `null` for a legacy free-string request. `housekeeping_task` is `{ uuid, status }` when a housekeeping-department request opened a linked task (`null` otherwise), so the board can show turnover/room-clean progress without a second call.

**Failure `error_code`s:** `validation_failed` (422), `unauthorized` (401), `forbidden` (403), `not_found` (404).

---

## Module: Departure Services (`service_requests.view` · `service_requests.update`)

A same-screen view of everything a departing stay still needs today — transfer pickups, late-checkout and luggage requests, and guest express checkouts — projected live over existing tables (Phase 6, D-18..D-22). Nothing is persisted for this module; a status change writes straight to the row's own source.

### GET /departure-services — `service_requests.view`

**Departing set:** reservations `checked_in` or `checked_out` whose `check_out` is the requested date, or whose `checked_out_at` falls in that date's hotel-local day.

**Kinds:**

| `kind` | Source | Notes |
|---|---|---|
| `transfer` | `ServiceBooking` (`bookable_type: transfer`) | Excludes arrival pickups — bookings scheduled before the date's hotel-local midnight |
| `late_checkout` | `ServiceRequest` (`type: late_checkout`) | |
| `luggage` | `ServiceRequest` (`type: luggage`) | |
| `express_checkout` | `Reservation` (`check_out_mode: guest_express`) | Read-only, always `stage: resolved` |

**Request query:** `date` (`Y-m-d` hotel-local, default today, bounded to today ± 30 days — outside the window is `422`), `kind[in]`, `status[in]` (validated against the union of both status vocabularies below).

**Response `data`:** `{ items: [...], meta: { count, truncated } }` — **unpaginated**, capped at 500 rows, ordered `scheduled_at` ascending (nulls last), then `created_at`, then `uuid`. `meta.truncated` is `true` when there was more than the cap; `meta.count` is the number of rows actually returned. At most ~7 queries regardless of how many rows come back.

**Row shape:**
```json
{
  "uuid": "...", "kind": "transfer", "source_type": "service_booking",
  "status": "pending", "stage": "open", "allowed_statuses": ["confirmed", "cancelled"],
  "scheduled_at": "2026-09-28T09:00:00+00:00", "notes": null,
  "reservation": { "uuid": "...", "booking_code": "...", "check_out": "2026-09-28", "checked_out_at": null, "status": "checked_in" },
  "guest": { "uuid": "...", "name": "...", "phone": "..." },
  "room_number": "812", "assigned_user_uuid": null, "created_at": "..."
}
```
`uuid` is the bare source row's own uuid (a booking, a request, or a reservation for `express_checkout`) — there is no departure-service table or id of its own. `source_type` is `service_booking`, `service_request` or `reservation`, and is the hint to send back on the PATCH below.

**`stage` vocabulary** (D-21) — the same three values across every kind, so the dashboard builds one status pill instead of two state machines:

| `stage` | Transfer booking (`status`) | Request (`status`) | `express_checkout` |
|---|---|---|---|
| `open` | `pending` | `new` | — |
| `in_progress` | `confirmed` | `in_progress` | — |
| `resolved` | `completed`, `cancelled` | `completed`, `cancelled` | always |

`allowed_statuses` is the enforced transition table for a booking or the D-05-style targets for a request; empty for `express_checkout` (read-only).

### PATCH /departure-services/{uuid}/status — `service_requests.update`

Of the seeded presets, `reception`, `concierge`, `housekeeping` and `kitchen` hold `service_requests.update` (reception so the front desk can progress `late_checkout` rows, which route to its department). `reception` still does not hold `service_requests.assign`. An account with `service_requests.view` only gets `403` here.

**Request body:** `{ "status", "reason"?, "source_type"? }`.

**Resolution order:** when `source_type` is sent, resolve directly against that source; otherwise probe in order **transfer booking → late_checkout/luggage request → guest-express reservation**. A `source_type` hint that does not match the uuid's real source is `404`, same as an unknown uuid.

**Behavior:** `status` is first checked against the union of the booking and request vocabularies (`422 validation_failed` on `status` if it's in neither); once the source resolves, `status` is re-checked against **that source's own family** (a request-only value against a resolved booking is also `422 validation_failed` on `status`). A booking delegates to the D-22 transition table below; a request delegates to the same writer the operations queue uses (`PATCH /operations/queue/service-requests/{uuid}/status`) — any request status is accepted, exactly as on the queue. An `express_checkout` row is always `422 departure_service_readonly` — it is derived from the stay, not written here. Returns the refreshed row in the shape above.

**Booking transition table (D-22), enforced by `UpdateServiceBookingStatusAction`:**

| From | Allowed to |
|---|---|
| `pending` | `confirmed`, `cancelled` |
| `confirmed` | `completed`, `cancelled` |
| `cancelled`, `completed` | none (terminal) |

An invalid move is `422 service_booking_transition_invalid` with `context: { from, to, allowed }`. **Confirming a transfer is what makes the next folio generation bill it** — `GenerateFolioAction` only bills bookings in `confirmed` or `completed`.

**Failure `error_code`s:** `validation_failed` (422 — bad `status` value, or a value outside the resolved source's family), `service_booking_transition_invalid` (422), `departure_service_readonly` (422), `not_found` (404 — unknown uuid, a spa/table booking, a request of some other type, a non-express reservation, or a `source_type` hint that doesn't match), `unauthorized` (401), `forbidden` (403).

**Known gaps:** no `POST /departure-services` (staff cannot create a departure service by hand) and no `GET /departure-services/{uuid}` — workaround for both: `POST /service-requests` as the guest (or ask them to raise it through the app).

### Dashboard handoff (Phase 6)

- Rename any `luggage_storage` filter chip to **`luggage`** — that is the seeded catalogue code and the request `type`, there is no `luggage_storage` value anywhere in this API.
- Hide every status action on a row whose `allowed_statuses` is empty (this is how an `express_checkout` row signals read-only, and how a terminal task/booking/request signals it is done).
- `GET /departure-services` returns unpaginated `data.items` — page it client-side if needed, and show a "more available" indicator from `meta.truncated` rather than assuming `meta.count` is the true total.
- The `stage` vocabulary is always `open` | `in_progress` | `resolved`, regardless of kind — build one status pill, not one per kind.

---

## Module: Support Tickets (tickets.view · tickets.assign · tickets.respond)

Staff-run support tickets with a timeline, service-recovery records and escalation (Phase 7). All routes are `auth:users` and live under `/api/support-tickets`; `{ticket}` is the ticket's uuid. Tickets also appear on the operations queue (`queue_type: "tickets"`) — both surfaces share the same writers, so the lifecycle rules below apply to both. There is **no DELETE route** (a `DELETE` is `405 method_not_allowed`): tickets and recoveries are permanent audit.

| Route | Gate | Success |
|---|---|---|
| `GET /support-tickets` | `tickets.view` | 200, paginated |
| `GET /support-tickets/{ticket}` | `tickets.view` | 200 |
| `POST /support-tickets` | `tickets.respond` | 201 |
| `PATCH /support-tickets/{ticket}/status` | `tickets.respond` | 200 |
| `POST /support-tickets/{ticket}/reply` | `tickets.respond` | 201 |
| `POST /support-tickets/{ticket}/recovery-actions` | `tickets.respond` | 201 |
| `POST /support-tickets/{ticket}/escalate` | `tickets.respond` | 200 |
| `PATCH /support-tickets/{ticket}/assign` | `tickets.assign` | 200 |

Every write returns the full ticket detail shape below. Missing token `401`, missing permission `403`, unknown uuid `404`, bad input `422 validation_failed`.

### Lifecycle

Statuses: `open`, `assigned`, `in_progress`, `waiting_guest`, `resolved`, `closed`. The enforced transition table (the ticket's `allowed_statuses` lists the targets you may PATCH to):

| From | Allowed to |
|---|---|
| `open` | `in_progress`, `resolved`, `closed` |
| `assigned` | `in_progress`, `waiting_guest`, `resolved`, `closed` |
| `in_progress` | `waiting_guest`, `resolved`, `closed` |
| `waiting_guest` | `in_progress`, `resolved`, `closed` |
| `resolved` | `closed`, `in_progress` (reopen) |
| `closed` | none (terminal) |

`assigned` is system-managed: it is reached only by **assign**, **claim** or **escalate**, never by a status PATCH — a PATCH to `assigned` is `422 ticket_transition_invalid`, as is any other move outside the table (`context: { from, to, allowed }`, `allowed` never contains `assigned`).

**`PATCH /support-tickets/{ticket}/status`** — body `{ "status", "reason"? }` (`reason` max 1000). A non-blank `reason` is **required** when closing a ticket that is not `resolved`, and when reopening (`resolved` → `in_progress`); otherwise `422 validation_failed` on `reason`. Side effects: `resolved_at` is stamped on `resolved` and cleared on reopen; `closed_at` is stamped on `closed`; moving an **unassigned** ticket to `in_progress` assigns it to the caller (self-assign, recorded as the `target_user` of the one `status_change` row). The reason is stored as the timeline row's `body`.

### POST /support-tickets

Body: `subject` (required, 3–150), `category` (required: `inquiry`, `complaint`, `booking_help`, `maintenance`, `other`), `description?` (max 5000), `priority?` (`low` | `normal` | `high`), `department?` (`kitchen`, `housekeeping`, `concierge`, `reception`, `events`, `sales`, `maintenance`), `guest_uuid?`, `reservation_uuid?`, `room_uuid?` (a soft-deleted room is rejected).

- The server forces `source: "staff"`, `status: "open"` and `created_by` (the caller); those fields are ignored if sent. A `created` row is written to the timeline.
- `department` falls back by category: `complaint` → `concierge`, `maintenance` → `housekeeping`, `booking_help` → `reception`, everything else → `concierge`. An explicit `department` wins.
- When `reservation_uuid` is sent, the guest is derived from the reservation; sending a `guest_uuid` that is not that reservation's guest is `422 validation_failed` on `guest_uuid`. With no reservation, `guest_uuid` alone is accepted. `room_uuid` is independent — it is **not** derived from the reservation.
- Returns `201` with the detail shape (no `conversation_uuid` unless a chat conversation is linked to the guest).

### GET /support-tickets — list

Paginated (`per_page`), rows use the ticket shape below **without** `actions`, `actions_truncated` or `latest_escalation`.

| Query | Notes |
|---|---|
| `status`, `department`, `source`, `category`, `priority` | `eq` or `in` (`?status[in]=open,assigned`). Unknown values match nothing. `priority` takes the labels `low`/`normal`/`high`. |
| `created_at[gte]`, `created_at[lte]` | Parsed as instants (UTC); an unparseable value is `422`. |
| `assignee` | A staff uuid, `unassigned`, or `me` (the caller). Any other value is `422 validation_failed`. |
| `guest`, `reservation` | A uuid; a malformed uuid is `422`. |
| `escalated` | Boolean — `true` = `escalation_level` > 0, `false` = 0. A non-boolean is `422`. |
| `sort` / `sort_dir` | `sort` one of `created_at`, `updated_at`, `priority`, `status` (`sort_dir` `asc` default / `desc`); `id` descending is the tie-break. Default order: `created_at` descending. |

### Ticket shape

```json
{
  "uuid": "...", "subject": "...", "description": null,
  "category": "complaint", "status": "in_progress", "priority": "high",
  "department": "concierge", "source": "staff", "escalation_level": 0,
  "allowed_statuses": ["waiting_guest", "resolved", "closed"],
  "guest": { "uuid": "...", "name": "..." },
  "reservation": { "uuid": "...", "booking_code": "CARL-..." },
  "room": { "uuid": "...", "number": "812" },
  "conversation_uuid": null,
  "assigned_user": { "uuid": "...", "name": "..." },
  "created_by": { "uuid": "...", "name": "..." },
  "folio_credit_total_usd": "25.00", "recorded_value_usd": "65.00",
  "resolved_at": null, "closed_at": null, "created_at": "...", "updated_at": "..."
}
```

`guest`, `reservation`, `room`, `assigned_user` and `created_by` are `null` when absent. `priority` is the label (`low`/`normal`/`high`). `conversation_uuid` is the guest's chat conversation, when there is one. **Two totals, USD strings:** `folio_credit_total_usd` sums only **ledger-backed folio credits** (recoveries of type `folio_credit`, absolute values); `recorded_value_usd` sums **every** recovery's recorded value, any type — so it is at least the first, and non-credit amounts are informational, not money moved.

The detail (`GET /support-tickets/{ticket}` and every write) adds:

- `actions` — the timeline, **newest 200 rows in ascending order**; `actions_truncated` is `true` when older rows were left out.
- `latest_escalation` — `{ level, target_user: {uuid, name}, reason, created_at }` for the newest escalation **within the loaded actions**, else `null` (so `null` can also mean "the escalation is older than the newest 200 rows"; read `escalation_level` for the count).

**Action shape:**

```json
{ "uuid": "...", "type": "status_change", "body": "reason or text", "from_status": "open", "to_status": "in_progress",
  "actor": { "uuid": "...", "name": "..." }, "target_user": null, "recovery": null, "meta": null, "created_at": "..." }
```

`type` is `created`, `status_change`, `assignment`, `escalation`, `reply` or `recovery`. `recovery` is set only on `recovery` rows: `{ uuid, type, amount_usd, description, folio_item_uuid }`. `meta` keys per type: `assignment` → `{ "claim": true }` when the assignment came from a queue claim; `escalation` → `{ "level", "previous_assignee_uuid" }`; no other type carries meta.

### PATCH /support-tickets/{ticket}/assign

Body `{ "user_uuid" }` (required, must exist) — `tickets.assign`. The assignee must be eligible (active staff holding `tickets.respond`, or a super admin) else `422 assignee_not_eligible`; this check runs before the no-op. Only active tickets (`open`, `assigned`, `in_progress`, `waiting_guest`) can be assigned: a `resolved` or `closed` ticket is `422 ticket_closed` (`context: { status }`). Assigning the current assignee is a `200` no-op (nothing written). Otherwise an `open` ticket becomes `assigned`; in any other active status the status is kept and only the assignee swaps; one `assignment` timeline row is written.

### POST /support-tickets/{ticket}/reply

Body `{ "body" }` (1–5000). **Replies are internal notes only**: this writes one `reply` timeline row, does not change the status, does not message the guest and does not touch the chat. To answer the guest, post to `POST /cms/conversations/{conversation}/messages` using the ticket's `conversation_uuid` (`tickets.respond`). A `closed` ticket is `422 ticket_closed`.

### POST /support-tickets/{ticket}/escalate

Body `{ "user_uuid", "reason" }` (`reason` 3–1000). The level is server-derived — a `level` in the body is ignored. Guards run in this order, each a `422`:

1. `ticket_closed` — the ticket is `resolved` or `closed`;
2. `ticket_escalation_invalid` `{ reason: "self" }` — the target is the caller;
3. `ticket_escalation_invalid` `{ reason: "same_assignee" }` — the target already holds the ticket;
4. `ticket_escalation_limit` `{ level, max }` — `escalation_level` has reached the cap, `HOTEL_TICKET_MAX_ESCALATION_LEVEL` (default `3`);
5. `assignee_not_eligible` — the target lacks `tickets.respond`.

Effects: the target becomes the assignee, `escalation_level` goes up by one, an `open` ticket becomes `assigned`, and one `escalation` row is written (`body` = reason, `meta` = `{ level, previous_assignee_uuid }`). The cap counts escalations, not people: A → B → A is allowed within it. **No notification is sent** to anyone (no push, no email, nothing scheduled) — the dashboard learns of an escalation by watching the queue row's `assigned_user_uuid` and by filtering `GET /support-tickets?assignee=me`.

### POST /support-tickets/{ticket}/recovery-actions

Records a service-recovery gesture on the ticket. It is **record-only**: it never posts to or changes a folio. Body: `type` (required: `folio_credit`, `rate_discount`, `courtesy_amenity`, `room_upgrade`, `late_checkout`, `apology`, `other`), `description` (required, 3–1000), `amount_usd?` (0–99999.99, at most 2 decimals; informational for every type except `folio_credit`), `folio_item_uuid` (**required** for `folio_credit`, **prohibited** for every other type). A `closed` ticket is `422 ticket_closed`. Writes one `recovery` timeline row (with its `recovery` object) and returns `201`.

**Two-step folio credit.** First post the credit on the guest's folio yourself: `POST /cms/folios/{folio}/line-items` (see *Module: Folios & Express Checkout* for the credit body) with an `Idempotency-Key` header and `folios.post`. Then link that line here with `type: "folio_credit"` and its `folio_item_uuid`. The recorded `amount_usd` is the absolute value of the credit line; sending a different `amount_usd` is `422 validation_failed` on `amount_usd`. Amounts are USD. Each credit line can be linked once. Link failures are `422 ticket_recovery_folio_invalid` with `context: { folio_item_uuid, reason }`, `reason` one of, checked in this order: `no_stay` (the ticket has neither a reservation nor a guest, so there is no stay to match), `not_credit` (the line is not a credit), `other_stay` (the line belongs to a different reservation than the ticket's — or, for a guest-only ticket, a different guest's), `already_linked`. An unknown `folio_item_uuid` is `422 validation_failed`.

### Failure `error_code`s (this module)

`ticket_transition_invalid`, `ticket_closed`, `ticket_escalation_invalid`, `ticket_escalation_limit`, `ticket_recovery_folio_invalid`, `assignee_not_eligible` (all 422), plus `validation_failed` (422), `unauthorized` (401), `forbidden` (403), `not_found` (404). See the quick reference below.

### Dashboard handoff (Phase 7)

- Renames: `owner` → **`user_uuid`** (assign body); escalate `{ target_owner, level }` → **`{ user_uuid, reason }`** (`level` is server-derived); recovery `action_type` / `detail` / `amount` / `currency` → **`type` / `description` / `amount_usd`** (USD only; the old `transport_hold` type is now `other`); priority `critical` → **`high`** (labels are `low`/`normal`/`high`); staff list `/operations/queue/staff` → **`/operations/staff`**.
- Claim path is `/operations/queue/{queue_type}/{uuid}/claim`, and claim **no longer moves the item to `in_progress`** (a ticket `open` becomes `assigned`; other statuses are kept). Build every queue path from the row's `queue_type`.
- The assignee picker should call `GET /operations/staff?type=…` rather than listing every staff user — assigning someone without the type's work permission is `422 assignee_not_eligible`.
- Show "Reply" as an internal note; to answer the guest, post to the conversation named by `conversation_uuid`.
- `status` PATCH targets come from the ticket's `allowed_statuses`; never offer `assigned`.
- **Deploy note [BLOCKING]:** `HOTEL_TICKET_MAX_ESCALATION_LEVEL` (default `3`) must be set in the production environment if the default is not wanted, and ticket Firestore mirrors need a running queue worker.

---

## Module: Night audit (`reports.view` or `night_audit.manage`)

All four routes sit under `/api/operations/night-audit` (staff bearer token, `auth:users`, no `/v1`) and return the **same** `data` payload: `{ state, audit }`.

| Route | Gate | Purpose |
|---|---|---|
| `GET /operations/night-audit?date=Y-m-d` | `reports.view` OR `night_audit.manage` | Read (and lazily open) the audit for a business date |
| `PATCH /operations/night-audit/checks/{check}` | `night_audit.manage` | Resolve or override one check |
| `PATCH /operations/night-audit/blockers/{blocker}` | `night_audit.manage` | Attest a blocker as resolved |
| `POST /operations/night-audit/{audit}/close` | `night_audit.manage` | Close the business date |

Timestamps are UTC ISO-8601 with `Z`. Unknown uuids answer `404 not_found`. Errors use the standard envelope `{ success: false, message, error_code, context, request_id }`.

### GET /operations/night-audit

`date` is optional and strict `Y-m-d`. `data` = `{ state: { current_business_date, last_closed_date }, audit: null | { uuid, business_date, status (open|closed), snapshot_basis ("current_state_at_open"), evaluated_at, opened_by {uuid,name}|null, closed_at, closed_by, readiness { checks_pending, blockers_open, can_close }, checks[5], blockers[] } }`.

```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "state": { "current_business_date": "2026-10-10", "last_closed_date": null },
    "audit": {
      "uuid": "05634aa0-1471-4ac7-86e9-f8ab8e1c41a5",
      "business_date": "2026-10-10",
      "status": "open",
      "snapshot_basis": "current_state_at_open",
      "evaluated_at": "2026-10-10T19:00:00Z",
      "opened_by": { "uuid": "1be23a59-7d1d-4315-9305-1e35cdc5854e", "name": "Night Manager" },
      "closed_at": null,
      "closed_by": null,
      "readiness": { "checks_pending": 2, "blockers_open": 1, "can_close": false },
      "checks": [
        {
          "uuid": "3350f3c4-de8f-4cc8-bfd6-35acadc91df5",
          "type": "unsettled_departures",
          "label": "Unsettled departures",
          "blocking": true,
          "status": "pending",
          "issue_count": 1,
          "evidence": [{ "reservation_uuid": "6e869037-cf5f-4727-9aa3-c0fb27783298", "booking_code": "CARL-VKYVIMBP" }],
          "evidence_truncated": false,
          "note": null,
          "acted_by": null,
          "acted_at": null,
          "blocker_uuid": "9c599adb-66e4-44d6-a26f-a52865c9245b"
        },
        {
          "uuid": "a4598939-74ca-4834-99e9-ee9549b2c86e",
          "type": "dirty_rooms",
          "label": "Dirty rooms",
          "blocking": false,
          "status": "pending",
          "issue_count": 1,
          "evidence": [{ "room_uuid": "add051e9-2939-42d4-8877-8f2d587ac57e", "number": "204" }],
          "evidence_truncated": false,
          "note": null,
          "acted_by": null,
          "acted_at": null,
          "blocker_uuid": null
        }
      ],
      "blockers": [
        {
          "uuid": "9c599adb-66e4-44d6-a26f-a52865c9245b",
          "check_uuid": "3350f3c4-de8f-4cc8-bfd6-35acadc91df5",
          "type": "unsettled_departures",
          "status": "open",
          "note": null,
          "acted_by": null,
          "acted_at": null
        }
      ]
    }
  },
  "request_id": "2559b287-9681-4a02-ad5b-5d2cda3da377"
}
```

(`checks` trimmed here; the real array always has 5 entries in the fixed order below, and `unassigned_arrivals`, `open_high_priority_tickets`, `open_folio_disputes` appear with `status: "passed"` and empty `evidence` when clean.)

**Check shape:** `{ uuid, type, label (localized), blocking, status (passed|pending|resolved|overridden), issue_count, evidence[≤20], evidence_truncated, note, acted_by, acted_at, blocker_uuid|null }`. **Blocker shape:** `{ uuid, check_uuid, type, status (open|resolved), note, acted_by, acted_at }`. Evidence holds public ids only.

#### The five checks (fixed order)

| `type` | Blocking | Pending when (business date D) | Evidence item |
|---|---|---|---|
| `unsettled_departures` | yes | departures on D (confirmed / checked_in / checked_out) with no folio or an open folio | `{ reservation_uuid, booking_code }` |
| `unassigned_arrivals` | yes | arrivals on D with no room line, or a line without a room | `{ reservation_uuid, booking_code }` |
| `dirty_rooms` | no | live active rooms with status `dirty` | `{ room_uuid, number }` |
| `open_high_priority_tickets` | no | active tickets with priority >= 3 | `{ ticket_uuid }` |
| `open_folio_disputes` | no | open folio disputes (never block closing) | `{ dispute_uuid, folio_uuid }` |

An empty check is `passed`; a non-empty one is `pending`. A **blocker exists only for a non-empty blocking check**, so an audit has 0 to 2 blockers. Dirty rooms, tickets and disputes are the **current state at the moment the audit was opened**, even when the audit is for an old date.

#### Snapshot semantics

The audit is created lazily on the first `GET` of the current business date, is evaluated **once** and is never re-evaluated (`snapshot_basis: "current_state_at_open"`). Resolving the real-world problem does not change `status` or `issue_count`; staff record the outcome through the PATCH routes. Past audits are returned as history.

#### First-initialization runbook

No business-date state exists until the first night auditor (a `night_audit.manage` holder) calls `GET ?date=<the night being closed>`. Outcomes:

| Situation | Response |
|---|---|
| Before init, no `date` | `422 night_audit_not_initialized`, `context: { requires: "date" }` |
| Before init, `date` sent by a `reports.view`-only account | `403 forbidden`, `context: { reason: "night_audit_not_initialized" }` |
| Before init, `date` in the future | `422 night_audit_date_in_future`, `context: { requested_date, hotel_today }` |
| After init, no `date` | current business date |
| After init, a past `date` that has an audit | `200`, that audit as history |
| After init, a `date` that is not current and has no audit | `422 night_audit_date_mismatch`, `context: { requested_date, current_business_date }` |
| Current business date is after the hotel's today (e.g. D was closed at 23:30 and the page reloads) | `200` with `audit: null` |

The client must therefore send `date` on first use.

### PATCH /operations/night-audit/checks/{check} — `night_audit.manage`

Body: `{ "status": "resolved" | "overridden", "note": "<required, trimmed 1..1000>" }`. Success message `Night audit check updated.` and the full `{ state, audit }` payload. It **never touches the blocker** of a blocking check; resolve that separately.

### PATCH /operations/night-audit/blockers/{blocker} — `night_audit.manage`

Body: `{ "note": "<required>", "status": "resolved" }` (`status` optional and, if present, must be `resolved`). There is no override for blockers. This is an **attestation**, not a live re-check: the server does not verify that the departure was actually settled.

### POST /operations/night-audit/{audit}/close — `night_audit.manage`

No body (anything sent is ignored). Requires every check terminal (`passed`, `resolved` or `overridden`) **and** every blocker `resolved`. Repeating the call on a closed audit answers `200` unchanged. On success the business date advances one calendar day. There is no reopen.

```json
{
  "success": true,
  "message": "Business date closed.",
  "data": {
    "state": { "current_business_date": "2026-10-11", "last_closed_date": "2026-10-10" },
    "audit": { "uuid": "05634aa0-1471-4ac7-86e9-f8ab8e1c41a5", "business_date": "2026-10-10", "status": "closed", "closed_at": "2026-10-10T19:00:00Z", "closed_by": { "uuid": "1be23a59-7d1d-4315-9305-1e35cdc5854e", "name": "Night Manager" }, "readiness": { "checks_pending": 0, "blockers_open": 0, "can_close": false }, "checks": ["..."], "blockers": ["..."] }
  },
  "request_id": "..."
}
```

(`audit` trimmed; same shape as `GET`. `can_close` is `false` once closed.) Not ready:

```json
{
  "success": false,
  "message": "The night audit cannot be closed until every check and blocker is resolved.",
  "error_code": "night_audit_not_ready",
  "context": { "checks_pending": 1, "blockers_open": 1 },
  "request_id": "52f74d2b-7415-47c9-8dc9-b7c97135d27d"
}
```

### Check vs blocker

| | Check | Blocker |
|---|---|---|
| Exists for | all 5 types | non-empty blocking checks only (0–2) |
| Terminal states | `passed`, `resolved`, `overridden` | `resolved` |
| Route | `PATCH /checks/{check}` | `PATCH /blockers/{blocker}` |
| Body | `status` + `note` | `note` (+ optional `status: "resolved"`) |
| Override allowed | yes | no |

### Error codes (this module)

| `error_code` | HTTP | When | `context` |
|---|---|---|---|
| `night_audit_not_initialized` | 422 | No state yet and no `date` | `{ requires: "date" }` |
| `night_audit_date_in_future` | 422 | First-init `date` is after the hotel's today | `{ requested_date, hotel_today }` |
| `night_audit_date_mismatch` | 422 | `date` not current and no audit; or close on a non-current audit | `{ requested_date, current_business_date }` |
| `night_audit_closed` | 422 | Any PATCH on a closed audit (checked first) | `{ business_date, closed_at }` |
| `night_audit_item_resolved` | 422 | PATCH on an already terminal check or blocker | `{ item: "check" \| "blocker", status }` |
| `night_audit_not_ready` | 422 | Close with pending checks or open blockers | `{ checks_pending, blockers_open }` |
| `forbidden` | 403 | Missing permission; or `reports.view`-only account before init (`reason: "night_audit_not_initialized"`) | |
| `not_found` | 404 | Unknown check, blocker or audit uuid | |
| `validation_failed` | 422 | Bad `date`, `status` or missing `note` | |

### Dashboard handoff (Phase 9)

Mock to API mapping for the React team: `property_day` → `business_date`; `done` → `status`; mock `in_progress` → `open`. The gate is `reports.view` | `night_audit.manage`, **not** `FOLIOS_VIEW`. Handoff notes, an audit history list and severity levels are not provided.

---

## Module: Reports (`reports.view` only)

### GET /api/reports/dashboard?date_from=&date_to=

**Who can call:** `reports.view` **only** — an account with `night_audit.manage` alone gets `403`.

**Query:** `date_from` and `date_to` are sent together or not at all; strict `Y-m-d`; `date_to >= date_from`; at most 31 days inclusive (`custom.validation.report_period_too_long`). Default for both is the hotel's today; future dates are allowed. Validation errors answer `422 validation_failed`.

```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "period": { "date_from": "2026-10-01", "date_to": "2026-10-10", "days": 10, "timezone": "Asia/Damascus" },
    "generated_at": "2026-10-10T19:00:00Z",
    "occupancy": { "occupied_room_nights": 0, "available_room_nights": 10, "occupancy_rate": "0.0000" },
    "arrivals": 1,
    "departures": 1,
    "revenue": {
      "basis": "posted_folio_lines",
      "currency": "USD",
      "charges_usd": "120.00",
      "credits_usd": "0.00",
      "net_usd": "120.00",
      "by_source": { "reservation": "120.00", "service_booking": "0.00", "service_request": "0.00", "manual": "0.00", "credit": "0.00" }
    },
    "collections": {
      "basis": "completed_payments",
      "refunds_included": false,
      "stays_usd": "50.00",
      "event_deposits_usd": "0.00",
      "other_usd": "0.00",
      "total_usd": "50.00"
    },
    "open_work": {
      "basis": "current_state",
      "as_of": "2026-10-10T19:00:00Z",
      "service_requests": { "new": 0, "in_progress": 0, "total": 0 },
      "tickets": { "open": 0, "assigned": 0, "in_progress": 0, "waiting_guest": 0, "total": 0 }
    }
  },
  "request_id": "8bba903e-3361-49d7-ac1c-ba7edffb0d87"
}
```

### Metric definitions and limits

- **`occupied_room_nights`** — booked room-nights: every room line of `confirmed` / `checked_in` / `checked_out` stays, assigned to a room or not, counting nights in `[check_in, check_out)` that fall inside the period. There is no no-show status, so a past confirmed stay that never checked in still counts.
- **`available_room_nights`** — active live rooms **today** × `days` (rooms in maintenance included).
- **`occupancy_rate`** — 4-decimal string, **not clamped** (it can exceed `1`), `"0.0000"` when there are no rooms.
- **`arrivals` / `departures`** — reservations in the same statuses, each counted once.
- **`revenue`** — folio lines posted inside the hotel-local period (`basis: "posted_folio_lines"`), USD. Reservation lines are lump sums and may be re-priced while a folio is still open.
- **`collections`** — completed payments only (`basis: "completed_payments"`); refunds are **not** included, so this is not "net cash". Event deposits are reported here and never in revenue.
- **`open_work`** — current state at `as_of`, independent of the period.
- Money is a 2-decimal string. This is an operational posting/cash view, **not audited accounting**.

### Not provided

ADR, RevPAR, MTD/YTD, per-room-type revenue, a daily breakdown, booking-source revenue, the mock `revenue_today` / `kpis`, handoff notes, an audit-history list, severity, and exports. Do not build UI that expects them.

---

## Module: Exchange rates (`pricing.edit`)

Display-only conversion rates (Phase 9.1): units of the currency per 1 USD. Money stays USD everywhere else. All three routes are `auth:users` + `permission:pricing.edit` (the super admin passes). No role preset holds `pricing.edit` — grant it per account. There are no update/delete routes: every save appends a row, and the history is the table.

### GET /api/cms/exchange-rates

**Who can call:** `pricing.edit`. Not paginated — one entry per configured currency (`SYP`, `TRY`) in config order.

```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "base": "USD",
    "stale_after_hours": 168,
    "rates": [
      { "currency": "SYP", "rate": "13000.000000", "display_decimals": 0, "updated_at": "2026-10-04T08:00:00Z", "is_stale": false, "note": "CBS bulletin", "set_by": { "uuid": "...", "name": "Rana Finance" } },
      { "currency": "TRY", "rate": null, "display_decimals": 2, "updated_at": null, "is_stale": true, "note": null, "set_by": null }
    ]
  }
}
```

A currency with no rate yet has `rate`, `updated_at`, `note` and `set_by` all `null` and `is_stale: true`.

### GET /api/cms/exchange-rates/history

**Who can call:** `pricing.edit`. Paginated `{ items, meta }`, newest first. Query: `currency` (`?currency=SYP` or `?currency[in]=SYP,TRY`), `per_page` (max 100).

**Item:** `{ "uuid", "currency", "rate": "13000.000000", "note", "set_by": { "uuid", "name" }, "created_at": "2026-10-04T08:00:00Z" }`.

### POST /api/cms/exchange-rates

**Who can call:** `pricing.edit`.

**Request body:** `{ "currency": "SYP", "rate": "13000", "note": "CBS bulletin", "confirm_large_change": false }`

| Field | Rules |
|---|---|
| `currency` | Required; a configured code (`SYP`, `TRY`), case-insensitive; `USD` is rejected. |
| `rate` | Required; string or number matching `^\d{1,14}(\.\d{1,6})?$`, greater than 0. |
| `note` | Nullable, max 255. |
| `confirm_large_change` | Optional boolean. |

**Response:** HTTP 201, message `"Exchange rate saved."`, `data` is the history item above.

**Large-change guard:** when a current rate exists and the new one differs by more than 50% either way, the save is refused with `422` `exchange_rate_large_change` unless `confirm_large_change` is `true`. `context`: `{ "currency": "SYP", "current_rate": "13000.000000", "proposed_rate": "130.000000", "change_percent": "-99.000000" }`. Show the numbers to the user and resend with `confirm_large_change: true` to save.

**Failure `error_code`s:** `unauthorized` (401), `forbidden` (403, no `pricing.edit`), `validation_failed` (422), `exchange_rate_large_change` (422).

### Public board

`GET /api/public/exchange-rates` (no auth) returns the same board without `note` / `set_by`; `Cache-Control: public, max-age=300`, throttled 60/min. Used by the guest app, not the dashboard.

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
| `method_not_allowed` | 405 | Known path called with a verb it does not serve (e.g. `POST` on a read-only list); the `Allow` header lists the accepted verbs. Previously answered `500 server_error` |
| `too_many_requests` | 429 | Rate limited |
| `server_error` | 500 | Unexpected error — show generic message, log `request_id` |
| `no_availability` | 409 | Last room raced away during booking, or check-in auto-pick found no free room of the type for the stay dates |
| `room_already_assigned` | 409 | Room already assigned to another reservation for overlapping dates |
| `invalid_promo` | 422 | Promo code invalid/expired |
| `reservation_state` | 422 | Action not valid for the reservation's current state |
| `hold_expired` | 422 | Soft-hold window passed before OTP verification |
| `payment_failed` | 422 | Payment gateway rejected the charge |
| `inquiry_state` | 422 | Invalid event-inquiry status transition, or a checklist/deposit write in a status that does not allow it; `context: { status, allowed }` |
| `event_checklist_item_derived` | 422 | Checklist write on the derived `deposit` item; `context: { item }` |
| `event_deposit_already_recorded` | 422 | The inquiry already has a deposit; `context: { payment_uuid, paid_at }` |
| `no_active_reservation` | 403 | Guest-side entitlement gate — not relevant to dashboard requests, but appears in any guest-facing payload you might inspect while debugging |
| `room_status_transition_invalid` | 422 | Room status change not allowed from the current state; `context.allowed` lists the valid targets |
| `reservation_outside_stay_window` | 422 | Check-in outside the hotel-local stay window |
| `room_out_of_order` | 422 | The room is in maintenance |
| `folio_unsettled` | 422 | Check-out with an open folio; `context.can_force` says whether the caller may force it |
| `folio_missing` | 404 | The reservation has no folio yet; call `POST /cms/folios/{reservation}/generate` first |
| `folio_settled` | 422 | The folio is already settled and can no longer change (line items, payments, settle, reservation-level settle) |
| `folio_credit_exceeds_item` | 422 | A credit is larger than what remains of the item it reverses; `context.remaining_usd` |
| `folio_credit_exceeds_balance` | 422 | A credit would take the folio balance below zero; `context.balance_due_usd` |
| `folio_overpayment` | 422 | A folio payment is larger than the balance due; `context.balance_due_usd` |
| `folio_item_dispute_open` | 422 | The line item already has an open dispute; `context.dispute_uuid` |
| `folio_dispute_state` | 422 | Resolve/reject on a line item with no open dispute; `context.status` |
| `idempotency_conflict` | 409 | The `Idempotency-Key` was already used for a different request |
| `housekeeping_task_transition_invalid` | 422 | Housekeeping task status change not allowed from the current state; `context: { from, to, allowed }` |
| `housekeeping_task_closed` | 422 | Assign attempted on a `done`/`cancelled` housekeeping task |
| `service_booking_transition_invalid` | 422 | Service-booking (transfer) status change not allowed from the current state; `context: { from, to, allowed }` |
| `departure_service_readonly` | 422 | Status change attempted on an `express_checkout` departure row, which is derived from the stay |
| `ticket_transition_invalid` | 422 | Ticket status change not allowed from the current state (including any PATCH to `assigned`); `context: { from, to, allowed }` |
| `ticket_closed` | 422 | Assign, escalate or claim on a `resolved`/`closed` ticket, or reply/recovery on a `closed` ticket; `context: { status }` |
| `ticket_escalation_invalid` | 422 | Escalation target is the caller (`context.reason: self`) or already the assignee (`same_assignee`) |
| `ticket_escalation_limit` | 422 | The ticket reached the escalation cap; `context: { level, max }` |
| `ticket_recovery_folio_invalid` | 422 | A `folio_credit` recovery's line cannot be linked; `context: { folio_item_uuid, reason }` with `reason` one of `no_stay`, `not_credit`, `other_stay`, `already_linked` |
| `assignee_not_eligible` | 422 | The would-be assignee is inactive, not staff, or lacks the queue type's work permission; `context: { user_uuid, required_permission }` |
| `service_request_closed` | 422 | Assign or claim on a terminal service request; `context: { status }` |
| `queue_item_already_claimed` | 409 | Claim on an item someone else holds; `context: { assigned_user_uuid }` |
| `night_audit_not_initialized` | 422 | Night audit read before the first business date was set, with no `date`; `context: { requires: "date" }` |
| `night_audit_date_in_future` | 422 | First-initialization `date` is after the hotel's today; `context: { requested_date, hotel_today }` |
| `night_audit_date_mismatch` | 422 | `date` is not the current business date and has no audit, or close on a non-current audit; `context: { requested_date, current_business_date }` |
| `night_audit_closed` | 422 | Check/blocker PATCH on a closed audit; `context: { business_date, closed_at }` |
| `night_audit_item_resolved` | 422 | Check/blocker already terminal; `context: { item, status }` |
| `night_audit_not_ready` | 422 | Close with pending checks or open blockers; `context: { checks_pending, blockers_open }` |
| `exchange_rate_large_change` | 422 | New exchange rate differs from the current one by more than 50% either way and `confirm_large_change` is not `true`; `context: { currency, current_rate, proposed_rate, change_percent }` |
| `guest_account_deleted` | 422 | Note or preferences write, or a front-desk booking (`POST /cms/reservations` with `guest_uuid`), on a guest whose account was deleted |
| `guest_account_deletion_blocked` | 422 | Guest-app only (`DELETE /auth/guest/me` blocked); not returned by dashboard routes |

---

## Coming in later phases

- **P11** — AI chatbot will add `source: "chatbot"` tickets; staff-created tickets already exist since Phase 7, and nothing new is needed on the dashboard beyond what Phases 7 and 10 built
- **TICKET-08 (deferred)** — guest-visible ticket replies: today a ticket reply is an internal note and the guest is answered through the chat conversation; there is no route that shows a ticket reply to the guest
- **P12** — Reports (occupancy, revenue, reservations-by-source, request volume, ticket resolution — `reports.view`), hardening pass
