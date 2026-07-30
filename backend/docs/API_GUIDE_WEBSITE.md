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
| `Accept-Language` | Always | Any configured CMS locale — currently `en`, `ar`, `fr`, `tr`, `es`. Controls only the human-readable `message`/error/validation strings. |
| `Content-Type` | Requests with a body | `application/json` |

No `Authorization` header is needed or used by the website.

**`Accept-Language` does NOT localize content fields.** Every translatable content field (room type names, page bodies, promotion terms, etc.) is always returned as a locale-keyed object, regardless of `Accept-Language` — the header only picks which language the envelope's `message` string and validation error text are written in. The frontend picks the locale itself.

A real browser header is parsed and negotiated, so `fr-FR,fr;q=0.9,en;q=0.8` resolves to `fr`; anything unmatched falls back to `config('app.locale')` when that is one of the configured locales, otherwise to the first one.

### The locale set

`config/cms.php` is the single source of truth:

| | Locales |
|---|---|
| `locales` — accepted and stored | `en`, `ar`, `fr`, `tr`, `es` |
| `required_locales` — must be filled for content to save | `en`, `ar` |

Both are overridable per environment via `CMS_LOCALES` / `CMS_REQUIRED_LOCALES`, and **no endpoint publishes the list** — a client that needs it has to carry it and re-check when the CMS gains a language.

Two consequences for reading content:

1. **A locale map contains only the locales that actually hold content.** The API filters out `null` and `""`, so `name` may come back as `{ "en": …, "ar": … }` with no `fr` even though `fr` is configured and stored elsewhere. `field[locale]` being `undefined` is normal — always fall back (usually to `en`).
2. **`en` and `ar` are the only locales guaranteed to be present** on a required field, because they are the only required ones at write time. Seeded content currently carries `en`, `ar` and `fr`; `tr` and `es` are accepted but largely unfilled.

The dining **menu** module and the in-stay service catalog accept `en` and `ar` only at write time, so their content will never carry `fr`/`tr`/`es`.

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

The page key is **`current_page`**, not `page`, and `meta` has exactly those four keys — no `from`, `to`, `links` or `path`.

**`data` is not uniformly a paginated list.** Three shapes exist across the public surface, and the difference is deliberate:

| `data` shape | Endpoints |
|---|---|
| paginated `{ items, meta }` | every `index` route except the two below |
| **bare array** | `GET /public/dining-venues/{uuid}/menu-categories`, `GET /public/service-catalog` |
| **flat object** | `GET /public/settings` (a `{group:{key:value}}` map — see Module: Site Settings), plus every `show` route and `/health` |

Never assume `data.items` exists.

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

Always log or surface `request_id` in error states — it identifies the exact request in backend logs. It is also returned as the `X-Request-Id` response header on every request.

**Branch on `error_code`, never on `message`.** `message` is localized by `Accept-Language` and free to be reworded; `error_code` is a stable snake_case identifier. Codes reachable from the public surface:

| `error_code` | HTTP | Cause on a public route |
|---|---|---|
| `not_found` | 404 | Unknown uuid/slug, or the record is `is_active: false` |
| `validation_failed` | 422 | Bad request body or query params; `errors` is present |
| `too_many_requests` | 429 | OTP rate limit on the booking flow |
| `server_error` | 500 | Unhandled exception; `message` is the raw text only in local env |
| `no_availability` · `invalid_promo` · `hold_expired` · `otp_invalid` · `otp_expired` · `otp_locked` | 409/422/429 | Booking flow only — see Module: Availability, Quote & Booking |

Treat an unrecognised `error_code` as a generic failure rather than crashing; new codes can be added.

`errors` is keyed by **field path**. Where a field is translatable the key is `"{field}.{locale}"` (e.g. `name.ar`) — relevant only if the site ever submits content, but worth knowing when reading a 422 from the booking or inquiry endpoints, where nested keys like `requirements.0.type` appear.

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

All public, no token. Every list/show endpoint filters `is_active = true` server-side (inactive records 404 on `show`, never leak into a list). Route key is `uuid` except for **Pages** and **Journal posts**, which are addressed by `slug`.

| Type | List | Show |
|---|---|---|
| Home sliders | `GET /public/home-sliders` | — |
| Room types | `GET /public/room-types` | `GET /public/room-types/{uuid}` |
| Rooms | `GET /public/rooms` | `GET /public/rooms/{uuid}` |
| Facilities | `GET /public/facilities` | `GET /public/facilities/{uuid}` |
| Dining venues | `GET /public/dining-venues` | `GET /public/dining-venues/{uuid}` |
| Venue menu chips | `GET /public/dining-venues/{uuid}/menu-categories` | — (**bare array**) |
| Venue menu items | `GET /public/dining-venues/{uuid}/menu` | — (accepts `?type=<chip-slug>`) |
| Event spaces | `GET /public/event-spaces` | `GET /public/event-spaces/{uuid}` |
| Amenities | `GET /public/amenities` | — |
| Promotions | `GET /public/promotions` | `GET /public/promotions/{uuid}` |
| Testimonials | `GET /public/testimonials` | — (one section, no detail page) |
| FAQs | `GET /public/faqs` | — (one accordion, no detail page) |
| Experiences | `GET /public/experiences` | `GET /public/experiences/{uuid}` |
| Gallery chips | `GET /public/gallery-categories` | — |
| Gallery photographs | `GET /public/gallery` | — (nothing deep-links to one photo) |
| Journal posts | `GET /public/journal` | `GET /public/journal/{slug}` |
| Pages | — | `GET /public/pages/{slug}` |
| Reviews | `GET /public/reviews/{type}/{uuid}` | — |
| Site settings | `GET /public/settings` | — (**flat map, not paginated**) |

There are 34 routes under `/api/public` in total; the rest are the bookables and availability/quote endpoints covered further down.

List endpoints are paginated (`data.items` + `data.meta`); `show` returns a single object in `data`.

Page size is controlled by `?per_page=` — default `15`, hard cap `100`. Values
above the cap are clamped to 100 rather than rejected, and `0`/negative/
non-numeric values fall back to 15; **`per_page` never produces an error**, so
read the size that was actually applied from `data.meta.per_page`. `?per_page=100`
is the "give me the whole collection" call the site makes; if `data.meta.total`
ever exceeds `per_page`, page through with `?page=`.

**Two endpoints ignore `per_page`** and are hard-wired to 15 per page:
`GET /public/dining-venues/{uuid}/menu` and `GET /public/reviews/{type}/{uuid}`.
A venue with more than 15 dishes therefore cannot be rendered as a single menu
without paging.

Apart from `page`, `per_page`, and `?type=` on the venue menu, **no query
parameter is read on the public routes** — no `search`, no `sort`, no `is_active`.
They always return `is_active = true` rows in their own fixed order (`sort_order`
ascending for most types; `published_on` descending for the journal; room type
then number for rooms; chip order then item order for the gallery).

### Publishing model — there is only `is_active`

The CMS has no draft state, no preview token and no scheduler. A record is on the
site when `is_active` is `true` and gone when it is `false`. Two fields look like
they gate visibility and do not:

- **`journal_posts.published_on`** is a *display date* used for ordering and
  bylines. A future-dated active post is returned by `GET /public/journal` today.
- **`promotions.valid_from` / `valid_until`** are display metadata. An expired
  active promotion is still returned.

So do not filter or hide content client-side based on those dates unless the
design intends it — the API considers all of it published.

### Media on content objects

Image-galleried types expose an `images` array of media objects:

```json
{ "uuid": "...", "url": "http://127.0.0.1:8000/storage/cms/RoomType/8c1f…/9aKd….jpg",
  "file_name": "deluxe-king.jpg", "mime_type": "image/jpeg", "size": 184320, "sort_order": 0 }
```

Several types also expose a **first-image convenience field** — `banner` (room
types, promotions), `photo` (home sliders, menu items), `cover_image` (journal
posts), `avatar` (testimonials), `image` (experiences, gallery items). Each is
`images->first()->url` in load order, not a separately designated primary image.

URLs are **absolute and served by the API host** (`APP_URL + /storage/…`), so the
API origin implicitly selects the image host too. `/storage` is *not* proxied by
the Vite dev server, so images are cross-origin in dev — harmless for `<img>`.
If every image 404s, `php artisan storage:link` has not been run on the API host.

Types with **no** images at all: pages, FAQs, amenities, gallery categories, site
settings. Home sliders expose `photo` but no `images` array.

### Room type
```json
{
  "uuid": "...", "name": {"en":"Deluxe King","ar":"...","fr":"..."}, "description": {"en":"...","ar":"..."},
  "view_type": "city", "bed_types": ["king"],
  "base_occupancy": 2, "max_occupancy": 4,
  "size_sqm": "35.00", "base_price_usd": "150.00", "cancellation_hours": 48,
  "rating": "4.5", "rating_count": 12,
  "is_active": true, "sort_order": 0,
  "banner": "http://.../storage/cms/RoomType/.../a.jpg",
  "images": [ { "uuid":"...", "url":"...", "file_name":"...", "mime_type":"image/jpeg", "size":81234, "sort_order":0 } ],
  "amenities": [ { "uuid":"...", "slug":"wifi", "name":{"en":"Wi-Fi","ar":"..."}, "icon":"wifi", "is_active":true, "sort_order":0 } ],
  "highlights": [ "…the subset of amenities flagged as highlights, same object shape…" ]
}
```

**`amenities` is a collection of amenity objects, not an array of strings.** Each
carries its own `slug`, translatable `name` and free-form `icon` key. `highlights`
is the subset an editor marked as a highlight, in the same shape — render it as
the short feature list and `amenities` as the full one.

`view_type` is one of `city`, `garden`, `pool`, `courtyard`, `mountain`,
`interior`, or `null`. `bed_types` is an array drawn from `king`, `queen`,
`double`, `twin`, `single`, `extra` (empty array when unset). `rating` /
`rating_count` are aggregates over published guest reviews — `rating` is `null`
until the first review lands.

### Room
```json
{
  "uuid": "...", "number": "101", "floor": 1, "status": "available", "is_active": true,
  "room_type": { "...room type shape above, minus banner/images/amenities/highlights..." },
  "images": [ ... ]
}
```
`status` is `available`, `occupied` or `maintenance`. The nested `room_type` omits
`banner`, `images`, `amenities` and `highlights` — those relations are not loaded
through the nesting. Fetch `GET /public/room-types/{uuid}` when you need them.

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
  "rating": "4.2", "rating_count": 8,
  "is_active": true, "sort_order": 0, "images": [ ... ]
}
```
`rating` / `rating_count` are aggregates over published guest reviews; `rating` is
`null` until the first one lands.

### Venue menu — two calls for one screen

`GET /public/dining-venues/{uuid}/menu-categories` returns the filter chips as a
**bare array** in `data` — not `{items, meta}`, not paginated:

```json
[
  { "uuid": "...", "slug": "starters", "dining_venue_uuid": "...",
    "name": {"en":"Starters","ar":"..."}, "sort_order": 0, "is_active": true }
]
```

`GET /public/dining-venues/{uuid}/menu` returns the dishes, **paginated at a fixed
15 per page** (`per_page` is ignored here), optionally narrowed to one chip with
`?type=<chip-slug>`:

```json
{
  "uuid": "...", "menu_category_uuid": "...", "type": "starters",
  "name": {"en":"Fattoush","ar":"..."}, "description": {"en":"...","ar":"..."},
  "price_usd": "12.00", "is_vegan": true,
  "photo": "http://.../storage/cms/MenuItem/.../x.jpg", "is_active": true
}
```

`type` is the parent chip's slug — use it to group items client-side without a
second lookup. Both endpoints `404` if the venue itself is inactive, and both
return only items whose chip is active as well as the item.

Menu content is written in **`en` and `ar` only** (this module does not use the
five-locale rule set), so do not expect `fr`/`tr`/`es` keys on menu names or
descriptions.

### Event space
```json
{
  "uuid": "...", "name": {"en":"...","ar":"..."}, "description": {"en":"...","ar":"..."},
  "capacity": 100, "location": {"en":"...","ar":"..."}, "amenities": {"en":"...","ar":"..."},
  "is_active": true, "sort_order": 0, "images": [ ... ]
}
```
Note: `amenities` here is a **translatable free-text string**, not the array it is on room types — don't share a parsing path between the two. It is optional in every locale, as are `location` and `capacity`, so expect `{}` / `null`.

### Page
```json
{ "uuid": "...", "slug": "about-us", "title": {"en":"...","ar":"..."}, "content": {"en":"...","ar":"..."}, "is_active": true, "sort_order": 0 }
```
No `images` field. `GET /public/pages/{slug}` 404s (`not_found`) if the slug doesn't exist or the page is inactive — there is no `GET /public/pages` list endpoint.

### Promotion
```json
{
  "uuid": "...", "title": {"en":"...","ar":"..."}, "description": {"en":"...","ar":"..."},
  "secondary_description": {"en":"...","ar":"..."}, "terms": {"en":"...","ar":"..."},
  "valid_from": "2026-07-01", "valid_until": "2026-08-31", "is_active": true, "sort_order": 0,
  "banner": "http://.../storage/cms/Promotion/.../p.jpg", "images": [ ... ]
}
```
`valid_from`/`valid_until` are `YYYY-MM-DD` or `null`, and are **display metadata
only** — an expired promotion with `is_active: true` is still returned. Both
`secondary_description` and `terms` are optional in every locale, so either may
come back as `{}`.

### Home slider
```json
{
  "uuid": "...", "header_text": {"en":"...","ar":"..."}, "location": {"en":"...","ar":"..."},
  "description_text": {"en":"...","ar":"..."},
  "photo": "http://.../storage/cms/HomeSlider/.../hero.jpg",
  "is_active": true, "sort_order": 0
}
```
Ordered by `sort_order`. Exposes `photo` (the first upload) and **no `images`
array**, so a slide is effectively one image. `photo` can be `null` if the editor
never uploaded one — guard the hero render.

### Amenity
```json
{ "uuid": "...", "slug": "wifi", "name": {"en":"Wi-Fi","ar":"..."}, "icon": "wifi",
  "is_active": true, "sort_order": 0 }
```
The shared amenity vocabulary. `icon` is a free-form key with no server-side
vocabulary — map it to your icon set and fall back gracefully on an unknown value.
No images on this type. The same objects appear nested inside a room type's
`amenities`/`highlights`, so this endpoint is only needed for a standalone
"all amenities" section.

### Testimonial
```json
{
  "uuid": "...", "author_name": "Layla K.", "author_title": {"en":"...","ar":"..."},
  "quote": {"en":"...","ar":"..."}, "rating": 5, "is_active": true, "sort_order": 0,
  "avatar": "http://.../storage/cms/Testimonial/.../a.jpg", "images": [ ... ]
}
```
Curated marketing quotes — **unrelated to guest reviews**. `author_name` is a
plain string, not translatable; `author_title` is translatable and optional.
`rating` is `1`–`5` or `null`. No `show` route: it renders as one section.

### FAQ
```json
{ "uuid": "...", "category": "booking", "question": {"en":"...","ar":"..."},
  "answer": {"en":"...","ar":"..."}, "is_active": true, "sort_order": 0 }
```
`category` is a plain, free-form string (or `null`) with no server-side
vocabulary — group by it if you like, but normalise case yourself. `answer` may
contain HTML. No `show` route: one accordion.

### Experience
```json
{
  "uuid": "...", "slug": "old-city-walk", "title": {"en":"...","ar":"..."},
  "description": {"en":"...","ar":"..."}, "category": "cultural",
  "duration_minutes": 120, "price_usd": "45.00", "is_active": true, "sort_order": 0,
  "image": "http://.../storage/cms/Experience/.../e.jpg", "images": [ ... ]
}
```
Concierge experiences, with a detail page — and `show` **404s on an inactive
experience rather than previewing it**. Note the detail route binds by **`uuid`**
(`/public/experiences/{uuid}`), even though the object carries a `slug`; the slug
is available for pretty URLs you resolve client-side. `category` is plain and
free-form. `duration_minutes` and `price_usd` may be `null`.

### Gallery — two calls for one screen

`GET /public/gallery-categories` returns the chip row (paginated):

```json
{ "uuid": "...", "slug": "rooms", "name": {"en":"Rooms","ar":"..."},
  "is_active": true, "sort_order": 0 }
```

`GET /public/gallery` returns the photographs (paginated), ordered by chip
`sort_order` then item `sort_order`. It returns **only items whose chip is
published as well as the item itself**, so a photo can vanish because its category
was deactivated:

```json
{
  "uuid": "...", "caption": {"en":"...","ar":"..."}, "is_active": true, "sort_order": 0,
  "category_slug": "rooms",
  "category": { "uuid": "...", "slug": "rooms", "name": {"en":"...","ar":"..."}, "is_active": true, "sort_order": 0 },
  "image": "http://.../storage/cms/GalleryItem/.../g.jpg", "images": [ ... ]
}
```

`category_slug` is there so the chip filter can be applied client-side without
reading the nested object. There is no `show` route and no server-side category
filter on the public route — fetch the wall once with `?per_page=100` and filter
in the browser. A gallery item with no upload yet has `image: null`.

### Journal post
```json
{
  "uuid": "...", "slug": "spring-in-damascus", "title": {"en":"...","ar":"..."},
  "excerpt": {"en":"...","ar":"..."}, "body": {"en":"...","ar":"..."},
  "category": {"en":"Culture","ar":"..."},
  "published_on": "2026-07-01", "is_active": true, "sort_order": 0,
  "cover_image": "http://.../storage/cms/JournalPost/.../c.jpg", "images": [ ... ]
}
```

Three things to get right:

- **`GET /public/journal/{slug}` binds by slug**, not uuid — the article URL is the
  slug an editor chose. (The CMS addresses the same post by uuid, so a re-slug
  changes the public URL only.) An inactive post `404`s.
- **`published_on` is a display date, not a schedule.** The list is ordered by it
  descending, but a future-dated active post is returned today. Nothing hides it.
- `category` is **translatable here** (unlike the plain `category` on FAQs and
  experiences) and optional, so it may be `{}`. `body` contains HTML.

### Review (read-only)

`GET /public/reviews/{type}/{uuid}` where `{type}` is `room_type` or
`dining_venue` and `{uuid}` is that record's uuid. Returns **published** reviews
only, newest first, paginated at a fixed 15 per page (`per_page` ignored).

```json
{
  "uuid": "...", "rating": 5, "comment": "...", "is_verified_stay": true,
  "is_published": true, "created_at": "2026-07-20T10:00:00+00:00",
  "author": { "first_name": "Nour", "last_name": "H." }
}
```

An unknown `{type}` or `{uuid}` is `404 not_found`. The aggregate `rating` /
`rating_count` on room types and dining venues comes from the same data, so a
card can show the summary without calling this endpoint. Submitting a review
requires a guest token and is app-only.

---

## Module: Site Settings

### GET /public/settings

**Purpose:** Global site copy an editor controls — hero text, footer, contact
details, social links, SEO strings.

**Who can call:** Public — no headers required.

**This endpoint is a deliberate exception to every convention above: `data` is a
flat `{ group: { key: value } }` map. It is not paginated, has no `items`/`meta`,
and exposes no `uuid`, `type` or `is_active`.**

```json
{
  "success": true,
  "message": "Success.",
  "data": {
    "booking": {
      "availability_note": { "en": "Reservations open 24 hours", "ar": "...", "fr": "..." },
      "cta_label": { "en": "Book Now", "ar": "...", "fr": "..." }
    },
    "contact": {
      "phone": { "en": "..." }, "email": { "en": "..." },
      "address": { "en": "...", "ar": "..." }, "address_lines": ["...", "..."],
      "hours_note": { "en": "..." }
    },
    "footer":  { "tagline": {...}, "copyright": {...}, "newsletter_heading": {...} },
    "hero":    { "eyebrow": {...}, "heading": {...}, "subheading": {...}, "cta_label": {...} },
    "seo":     { "site_title": {...}, "meta_description": {...} }
  },
  "request_id": "..."
}
```

Only rows with `is_active = true` appear, ordered by group then key.

**`value` is free-form JSON and is not validated by the API.** Usually it is a
locale map, but it can be a plain string, a number, an array, an object or `null`
— the CMS's `type` field (`text`, `richtext`, `image`, `url`, `json`, `bool`) is
only a hint about which editor widget to render and is not exposed here. Read
defensively: check for an object before indexing a locale, and treat a missing
group or key as "not configured" rather than an error.

Groups and keys currently seeded (20 rows, values in `en`/`ar`/`fr`):

| Group | Keys |
|---|---|
| `contact` | `phone`, `email`, `address`, `address_lines`, `hours_note` |
| `social` | `instagram`, `facebook`, `x`, `youtube` — **seeded `null` and inactive, so the `social` group is absent from this response until an editor fills them in** |
| `footer` | `tagline`, `copyright`, `newsletter_heading` |
| `booking` | `cta_label`, `availability_note` |
| `seo` | `site_title`, `meta_description` |
| `hero` | `eyebrow`, `heading`, `subheading`, `cta_label` |

Treat that as the current content, not a schema — an editor can add groups and
keys through the CMS at any time, so the site should key off what it needs and
tolerate extras.

**Failure:** none specific. An empty settings table returns `data: {}`.

---

## Module: Other public endpoints (exist, but app-oriented)

These are also under `/api/public` with no auth. They exist for the guest mobile
app's booking flows; the website generally has no use for them, but they are part
of the public surface and will show up in `route:list`:

| Endpoint | `data` | Purpose |
|---|---|---|
| `GET /public/service-catalog` | **bare array** | The guest app's in-stay service menu — 8 categories with their microservices; each category's `kind` tells the client how to render it |
| `GET /public/spa-services` | paginated | Bookable spa treatments (uuids for the app's booking call) |
| `GET /public/pool-cabanas` | paginated | Bookable cabanas |
| `GET /public/transfers` | paginated | Bookable airport/city transfers |
| `GET /public/dining-venues/{uuid}/tables` | paginated | Bookable restaurant tables for a venue |

All five are written in `en`/`ar` only. If the website ever surfaces spa
treatments as marketing content, `GET /public/spa-services` is the honest source
— but it is a bookables list, not editorial content, and carries no images.

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

**Dev/testing note:** no real SMS/WhatsApp/email provider is wired yet. In local/testing environments the code is always `000000` — real random codes are generated everywhere else once deployed.

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
- `POST /reviews/{type}/{uuid}` — submitting a review needs a guest token; the website may only *read* reviews
- All staff/dashboard endpoints (`/auth/login`, `/staff/*`, `/permissions`, `/roles`, `/cms/*` admin routes, `/operations/*`, `/dashboard/*`)

---

## Content that has no endpoint

Everything the website shows now has a server-side home. Earlier notes in this
repo's sibling docs claiming that experiences, gallery, journal/news,
testimonials and FAQs "have no endpoint" predate the modules above and are
**stale** — see `Carlton-hotel-s/docs/CMS-API-CONTRACT.md` for the full CMS-side
contract and its drift table.

The one remaining gap is **page slugs**. `GET /public/pages/{slug}` serves
whatever an editor created; there is no list endpoint, so the site cannot discover
which slugs exist. A request for a slug nobody created is a plain
`404 not_found` — decide per route whether that falls back to bundled copy or
renders a 404 page.
