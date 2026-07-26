# Mobile API Design — Service Catalog & Stays

> **Status:** Design only — no code in this document is implemented yet.
> **Scope:** (A) two-level guest service catalog, (B) Active / Upcoming / Past stay views with checkout, cancel, book-again, and receipts (JSON + PDF).
> **Grounded in:** `routes/api.php`, `app/Base/*`, existing migrations, `app/Enums/*`, `app/Actions/Service/*`, `app/Support/GuestEntitlement.php`, `docs/API_GUIDE_MOBILE.md`, and the `tupcode-laravel-backend` skill (additive migrations, DECIMAL money, uuid-public, AR/EN JSON columns, envelope, domain exceptions).

---

## 0. Decisions at a glance

| # | Question | Decision |
|---|---|---|
| 1 | Catalog tables | New `service_categories` (8 rows, stable `code`) + `service_items` (microservices). `service_requests` gains nullable `service_item_id`. Fully additive. |
| 2 | Microservice-less categories | Data-driven `kind` per category: `catalog`, `direct` (hidden default item), `link` (deep-link to existing module), `toggle` (DND state on the reservation). One uniform `POST /service-requests` for everything that creates a request. |
| 3 | Expected time | Single `expected_minutes` unsigned int, nullable. Client formats/localizes. |
| 4 | Stays endpoints | Three new read endpoints under `auth:guests` (`/stays/active`, `/stays/upcoming`, `/stays/past`) + two receipt endpoints. Checkout **reuses** `POST /folio/approve`; cancel **reuses** `DELETE /reservations/{uuid}`. |
| 5 | Book again | No new endpoint. Past-stay items carry `room_type_uuid`; app deep-links into the existing quote + `POST /reservations` flow. |
| 6 | Receipt | The reservation's folio + items + payments, served by a new past-stay-capable endpoint. PDF via `mpdf/mpdf` Blade→HTML render, generated on demand. |
| 7 | Check-in time / checked-out time | **No backing column today.** Add nullable `checked_in_at` / `checked_out_at` to `reservations`, stamped by the existing transitions. |

---

## 1. Service catalog — data model

### 1.1 New table: `service_categories`

Top level (exactly the 8 requested). `code` is the stable machine key — it is also what gets written into `service_requests.type`, which keeps the P10 operations queue and `Department::forServiceType()` semantics intact.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | internal |
| `uuid` | uuid, **unique** | public identifier (convention: never expose ids) |
| `code` | string(50), **unique** | `room_service`, `laundry`, `housekeeping`, `concierge`, `transport`, `restaurant`, `maintenance`, `do_not_disturb` |
| `name` | json | `{en, ar}` (`HasTranslations`) |
| `description` | json, nullable | `{en, ar}` |
| `kind` | string(20) | `catalog` \| `direct` \| `link` \| `toggle` — new string-backed enum `App\Enums\ServiceCategoryKind` |
| `department` | string(30), nullable | routing target for created requests (`Department` value); null for `link`/`toggle` kinds |
| `link_target` | string(30), nullable | only for `kind=link`; e.g. `dining` |
| `icon` | string(50), nullable | client icon key |
| `sort_order` | unsignedSmallInteger, default 0 | |
| `is_active` | boolean, default true | |
| timestamps | | |

**Indexes:** `unique(code)`, `unique(uuid)`, `index(is_active, sort_order)`.

### 1.2 New table: `service_items` (microservices)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `uuid` | uuid, **unique** | what the mobile app sends when requesting |
| `service_category_id` | FK → service_categories, `cascadeOnDelete` | |
| `name` | json | `{en, ar}` |
| `description` | json, nullable | `{en, ar}` |
| `expected_minutes` | unsignedSmallInteger, nullable | see §3 |
| `is_default` | boolean, default false | the hidden item behind `direct` categories (§2) |
| `sort_order` | unsignedSmallInteger, default 0 | |
| `is_active` | boolean, default true | |
| timestamps | | |

**Indexes:** `index(service_category_id)`, `index(service_category_id, is_active, sort_order)` (leftmost-prefix covers the catalog query), `unique(uuid)`.

### 1.3 Additive change to `service_requests`

```
$table->foreignId('service_item_id')->nullable()
      ->constrained('service_items')->nullOnDelete();
$table->index('service_item_id');
```

- **Existing rows untouched** — column is nullable, no backfill needed. Free-string `type` + `department` + `notes` stay exactly as they are; the P10 ops queue keys on `status`/`department` strings and sees no change.
- When a request is placed via a catalog item, `PlaceServiceRequestAction` resolves item → category and writes `type = category.code`, `department = category.department`, `service_item_id = item.id`. Legacy calls (bare `type` string) keep the current `Department::forServiceType()` fallback.
- `nullOnDelete` + the snapshotted `type` string means deleting an item never orphans history. Operationally, prefer `is_active = false` over delete.

### 1.4 Seed data (SeederClass: `ServiceCatalogSeeder`)

| Category (`code`, kind, department) | Items (`expected_minutes` are tunable seeds) |
|---|---|
| Room service — `room_service`, catalog, kitchen | Carlton Breakfast — "Full breakfast selection with fresh juice" (30) · Lunch Menu — "Syrian and international cuisine" (45) · Late Night Menu — "Light bites available until 2 AM" (30) |
| House keeping — `housekeeping`, catalog, housekeeping | Room cleaning — "Full room service and tidying" (45) · Fresh Towels — "Towels and linens replacement" (15) · Turndown Service — "Evening bed preparation" (20) |
| Laundry — `laundry`, catalog, housekeeping | Express Laundry — "Fast cleaning for items you need today." (240) · Dry Cleaning — "Suits, dresses, and delicates" (1440) · Pressing Service — "Quick pressing for a crisp finish." (120) |
| Concierge — `concierge`, direct, concierge | 1 hidden default item "Concierge Assistance" (`is_default`, 30) |
| Transport — `transport`, direct, concierge | 1 hidden default item "Transport / Pickup Request" (`is_default`, 30) |
| Maintenance — `maintenance`, direct, **maintenance (new enum case)** | 1 hidden default item "Maintenance Request" (`is_default`, 45) |
| Restaurant — `restaurant`, link (`link_target: dining`), — | none |
| Do not disturb — `do_not_disturb`, toggle, — | none |

Arabic translations are required for every seeded `name`/`description` (skill rule: both `en` and `ar`).

### 1.5 Enum change

Add `case MAINTENANCE = 'maintenance';` to `App\Enums\Department` (additive, string-backed) and extend `forServiceType()`: `'laundry' => HOUSEKEEPING`, `'maintenance' => MAINTENANCE`. P10's permission check is per queue *type* (`service-requests|tickets`), not per department, so a new department value is safe; dashboard groupings must merely tolerate it (risk R2).

### 1.6 Migration order (all additive, one deploy)

1. `2026_07_26_100000_create_service_categories_table`
2. `2026_07_26_100001_create_service_items_table`
3. `2026_07_26_100002_add_service_item_id_to_service_requests_table`
4. `2026_07_26_100003_add_stay_timestamps_and_dnd_to_reservations_table` (§5.1)
5. `ServiceCatalogSeeder` (idempotent upsert by `code` / item name)

---

## 2. Behavior of the 5 microservice-less categories

**Decision: the category's `kind` tells the client how to render; everything that creates a request funnels through the one existing endpoint.** Rationale: the mobile client gets a single uniform screen algorithm and a single write path; the server never grows a bespoke endpoint per category; and modules that already have richer flows (dining, transfers) are linked to, not duplicated — duplicating them as free-text requests would bypass pricing, scheduling, and the folio.

| Category | `kind` | Client behavior | Server behavior |
|---|---|---|---|
| Concierge | `direct` | Tap → notes sheet → submit | `POST /service-requests` with the category's `default_item_uuid` (returned in the catalog payload). Lands as `type=concierge`, dept `concierge`. |
| Transport | `direct` | Same | Same; lands as `type=transport`, dept `concierge` — identical on the ops queue to today's `POST /transport-requests` output. `/transport-requests` stays for back-compat; mobile should migrate to the uniform path. |
| Maintenance | `direct` | Same | `type=maintenance`, dept `maintenance` (new). |
| Restaurant | `link` | Navigate to existing dining screens (`GET /public/dining-venues`, table booking via `POST /service-bookings` with `bookable_type=restaurant_table`) | No request row created via the catalog. |
| Do not disturb | `toggle` | Switch in UI | `PATCH /stays/active/dnd` flips `reservations.dnd_until` (§5.1). Not a queue row — a stale "DND" ticket sitting in `new` forever is queue pollution, and DND is state, not work. |

**Uniform client algorithm:** render categories from `GET /public/service-catalog`; on tap switch on `kind` — `catalog` → show items then request; `direct` → request `default_item_uuid` immediately; `link` → navigate by `link_target`; `toggle` → render switch bound to the stay's DND state.

---

## 3. "Expected time" representation

**Decision: `expected_minutes` — a single nullable unsigned integer.**

An integer is locale-free (the client renders "~30 min" / "٣٠ دقيقة" from its own l10n), sortable, comparable, and directly usable later for ops-queue SLA/overdue highlighting — a translatable string can do none of that, and would push copywriting into a data column. A range (`min`/`max`) doubles the schema for negligible UX gain; hotels quote one number. Copy like "available until 2 AM" is *availability*, and already lives in `description`. Nullable covers items where a promise makes no sense.

---

## 4. Service catalog — API surface

| Method & path | Middleware | Purpose |
|---|---|---|
| `GET /public/service-catalog` | none (public, `is_active` only — same pattern as `/public/*` CMS reads) | Categories with nested active items, ordered by `sort_order`. |
| `POST /service-requests` | `auth:guests`, `is_checked_in` | **Extended, not replaced.** |
| `GET /service-requests` | `auth:guests`, `is_checked_in` | Unchanged; response items gain `service_item`. |
| `PATCH /stays/active/dnd` | `auth:guests`, `is_checked_in` | DND toggle (§5.4). |
| `apiResource cms/service-categories` | `auth:users`, `permission:cms.edit` | Admin CRUD (no destroy while `code` is referenced by seeds — prefer `is_active=false`). |
| `apiResource cms/service-items` | `auth:users`, `permission:cms.edit` | Admin CRUD. |

### 4.1 `GET /public/service-catalog` — response `data`

```json
[{
  "uuid": "...", "code": "room_service", "kind": "catalog",
  "name": {"en": "Room service", "ar": "..."}, "description": {"en": "...", "ar": "..."},
  "icon": "room_service", "link_target": null, "default_item_uuid": null,
  "items": [{ "uuid": "...", "name": {"en": "Carlton Breakfast", "ar": "..."},
              "description": {"en": "Full breakfast selection with fresh juice", "ar": "..."},
              "expected_minutes": 30 }]
}]
```
`direct` categories return `items: []` plus `default_item_uuid`; `link`/`toggle` return `items: []`, `default_item_uuid: null`. One un-paginated payload (bounded: 8 categories, ~15 items).

### 4.2 `POST /service-requests` — extended contract (backward compatible)

| Field | Rule | Notes |
|---|---|---|
| `service_item_uuid` | `nullable`, exists in active `service_items` | **new** — "the id of the service" |
| `type` | `required_without:service_item_uuid`, string, max 255 | legacy free-string path unchanged |
| `notes` | nullable, string, max 1000 | = "special instructions" |
| `priority` | nullable, enum | unchanged |

Loosening `type` to `required_without` is non-breaking for existing clients. Response gains `service_item: {uuid, name, expected_minutes} | null` and `category_code: string | null`. Unknown/inactive `service_item_uuid` → `validation_failed` (422). Item belonging to a `link`/`toggle` category cannot occur (no items exist there).

---

## 5. Stays — data model deltas and API surface

### 5.1 Additive columns on `reservations` (one migration)

| Column | Type | Written by | Why |
|---|---|---|---|
| `checked_in_at` | timestamp, nullable | `AssignRoom`/check-in transition (the action that sets `status=checked_in`) | Mobile "check-in time" — **no backing column exists today**; `check_in` is a DATE. |
| `checked_out_at` | timestamp, nullable | folio-approve action (sets `status=checked_out`) and admin settle path | Symmetry; receipts. |
| `dnd_until` | datetime, nullable | `PATCH /stays/active/dnd` | DND enabled ⇔ `dnd_until > now()`. Auto-expires; no stale-forever DND. |

No backfill: historical rows return `null` and the resource falls back to `check_in` date only (documented for mobile). No new indexes needed — stays queries are covered by existing `guest_id`, `status`, `check_in`, `check_out` indexes (add composite `(guest_id, status)` only if slow-query logs justify it).

### 5.2 Endpoints

| Endpoint | Middleware | Reuse or new |
|---|---|---|
| `GET /stays/active` | `auth:guests` | **New.** Object or `data: null`. |
| `GET /stays/upcoming` | `auth:guests` | **New.** Array (a guest can hold several future bookings). |
| `GET /stays/past` | `auth:guests` | **New.** Paginated (`items` + `meta`), newest `check_out` first. |
| Checkout active stay | `auth:guests`, `is_checked_in` | **Reuse `POST /folio/approve`** — it already approves the bill *and* flips the reservation to `checked_out`. A `/stays/active/checkout` alias would duplicate a write path for nothing. |
| Cancel upcoming stay | `auth:guests` | **Reuse `DELETE /reservations/{uuid}`** (404 not-yours, `reservation_state` 422 past cancellable window). |
| `GET /stays/{reservation}/receipt` | `auth:guests` | **New** (§7). Ownership check in `FormRequest::authorize()` → 404 `not_found`, never 403. |
| `GET /stays/{reservation}/receipt/pdf` | `auth:guests` | **New** (§7). Binary response — the one documented exception to the JSON envelope. |
| `PATCH /stays/active/dnd` | `auth:guests`, `is_checked_in` | **New.** Body `{enabled: bool, until?: datetime}`; enable defaults `dnd_until` to end of current hotel day. Returns `{enabled, until}`. |

**Why plain `auth:guests` on the three reads:** gating `/stays/active` behind `is_checked_in` would 403 (`no_active_reservation`) when the guest simply has no active stay — forcing the app to treat an error as an empty state. The views are projections the server resolves; empty is `null`/`[]`, not an error. Implementation: thin `StayService` + `StayResource` variants per view (do **not** overload `ReservationResource` — its contract is already published in `API_GUIDE_MOBILE.md`).

**View definitions (server-side, from the token's guest):**
- **Active:** `status = checked_in` (at most one; `GuestEntitlement` ordering as tiebreak).
- **Upcoming:** `status ∈ (pending, confirmed)` and `check_out ≥ today`. `pending_verification` excluded (unverified soft-hold).
- **Past:** `status ∈ (checked_out, cancelled)`.

### 5.3 Field → column maps (⚠ = no backing column today)

**Active stay** (`GET /stays/active`):

| API field | Backing |
|---|---|
| `uuid`, `booking_code`, `status` | `reservations` columns |
| `room_number` (e.g. "812") | `reservation_rooms.room_id → rooms.number` (non-null once checked in — room is assigned at check-in) |
| `room_name` | `reservation_rooms.room_type_id → room_types.name` (`{en, ar}`) |
| `checked_in_at` | ⚠ new `reservations.checked_in_at` (null for pre-migration rows) |
| `check_in`, `check_out` | date columns |
| `nights`, `nights_remaining` | computed: `check_out − check_in`, `max(0, check_out − today)` |
| `dnd` `{enabled, until}` | ⚠ new `reservations.dnd_until` |
| `folio_total_usd` | `folios.total_usd` via existing 1:1 (null if not yet generated) |

**Upcoming stay** (`GET /stays/upcoming`, array items):

| API field | Backing |
|---|---|
| `uuid`, `booking_code` ("reservation code"), `status` | columns |
| `room_number` | `rooms.number` — **null until staff assign a room** (normal case pre-check-in; mobile must render room type instead — risk R4) |
| `room_name` | `room_types.name` via `reservation_rooms` |
| `price_usd` | `reservations.total_usd` (per-room snapshot `reservation_rooms.price_usd` also included in `rooms[]`) |
| `check_in`, `check_out`, `nights` | columns / computed |
| `is_cancellable` | computed: `ReservationStatus::isCancellable()` |

**Past stays** (`GET /stays/past`, paginated items):

| API field | Backing |
|---|---|
| `uuid`, `booking_code` | columns |
| `room_name` | `room_types.name` via `reservation_rooms` |
| `total_nights` | computed |
| `check_in`, `check_out`, `checked_out_at` | columns (⚠ `checked_out_at` new, null historically) |
| `total_charge_usd` | `folios.total_usd` when a folio exists (frozen once settled), else `reservations.total_usd` |
| `status` | raw `checked_out` / `cancelled` — ⚠ there is no `complete` status; the app maps `checked_out → "Completed"` (renaming server-side would break website/admin contracts) |
| `has_receipt` | computed: folio row exists |
| `room_type_uuid` | via `reservation_rooms.room_type_id` — powers Book again (§6) |

---

## 6. "Book again" semantics

**Decision: no server endpoint; book-again is a client deep-link, powered by `room_type_uuid` on the past-stay item.** The app navigates to its booking flow pre-filled with that room type, calls the existing `GET /public/availability` + `GET /public/quote` for fresh dates/price, then the existing `POST /reservations` (tier-2, one step). Rationale: a new reservation legally requires *new dates* and *current availability/price* — the server cannot create one from a past stay without guessing dates and would routinely 409 (`no_availability`) or silently re-price; returning a "prefill" object from a bespoke endpoint would only duplicate what `quote` already returns. What it "creates" is therefore a normal `pending` reservation via the existing path; what the past-stays payload returns is the pointer (`room_type_uuid`) that makes the flow one tap.

---

## 7. Receipt

**What a receipt IS:** the reservation's **folio** (1:1, `folios` + `folio_items`) plus the **payments** recorded against the stay (`payments` where `payable` is the reservation/folio) — there is no separate receipt table and none is needed; the folio *is* the bill, frozen at `settled`. A receipt is available whenever a folio row exists; cancelled stays (no folio) have none (`has_receipt: false`; endpoint → `not_found`).

### `GET /stays/{reservation}/receipt` — response `data`

| Field | Backing |
|---|---|
| `reservation` `{uuid, booking_code, check_in, check_out, nights, guest_name}` | `reservations` (+ `guests`) |
| `folio` `{uuid, status, subtotal_usd, total_usd, approved_by_guest_at, settled_at}` | `folios` |
| `items[]` `{description, amount_usd, source_type}` | `folio_items` (⚠ `description` is a plain string, not `{en,ar}` — risk R6) |
| `payments[]` `{method, amount_usd, status, created_at}` | `payments` (payable = reservation) |
| `balance_due_usd` | computed: folio total − completed payments |

Why a new endpoint: the existing `GET /folio` is `is_checked_in` + *current* reservation only — it structurally cannot serve past stays. The new endpoint is read-only over a **settled/closed** folio; it must **not** trigger the open-folio recalculation path.

### PDF — `GET /stays/{reservation}/receipt/pdf`

**Decision: `mpdf/mpdf` (new composer dependency), Blade template → HTML → PDF, generated on demand and streamed (`Content-Disposition: attachment; filename="receipt-{booking_code}.pdf"`).** mpdf over dompdf because AR is a first-class locale in this codebase and dompdf's Arabic shaping/RTL support is effectively unusable, while mpdf ships proper Arabic ligature shaping and RTL out of the box. On-demand (no queue, no storage) because a one-page receipt renders in well under a second and pre-generating/storing adds an invalidation problem for unsettled folios. Locale from `Accept-Language` for labels; line-item descriptions render as stored (see R6). This endpoint returns raw `application/pdf` — documented as the envelope exception; errors (`not_found`) still return the JSON error envelope.

---

## 8. Ranked risks / conflicts with the existing schema & architecture

| # | Risk | Severity | Mitigation in this design |
|---|---|---|---|
| R1 | **Catalog room-service/laundry requests are unpriced.** `service_requests` carries no amount and the folio only ingests *priced service bookings* — a "Carlton Breakfast" ordered via the catalog never reaches the bill. If these must be billable, staff must post folio items manually (no admin add-item endpoint exists today). | High | Documented as out of scope; decide billing model before GA. Future: `price_usd` on `service_items` + folio ingestion. |
| R2 | **"Check-in time" has no backing column** — `reservations.check_in` is a DATE and `checked_in_at` doesn't exist. Historical stays will show `null` forever. | High | Additive columns (§5.1); mobile falls back to the date. |
| R3 | **DND is invisible to staff.** Nothing in P10's queue or the admin dashboard reads `reservations.dnd_until`. Without a companion admin change (expose the flag in admin `ReservationResource` + a housekeeping board filter), the toggle is guest-side theater. | High | Listed as required companion work in the same phase. |
| R4 | **Upcoming-stay "room number" is usually null** — rooms are assigned at check-in (`reservation_rooms.room_id` nullable). The mock showing a room number pre-arrival can't be honored for most bookings. | Medium | Contract returns `room_number: null` + `room_name`; frontend informed via this doc. |
| R5 | **No `complete` status exists.** Mobile's "status (complete/cancelled)" maps to `checked_out`/`cancelled`; renaming would break the published website/admin contracts. | Medium | Raw statuses in the API; label mapping is client-side. |
| R6 | **`folio_items.description` is not translatable** (plain string, mostly EN). AR receipts show EN line items. | Medium | Accepted for now; flagged for a future translatable-description migration. |
| R7 | **New `maintenance` department value.** Any dashboard grouping/filtering by the current 6 department strings must tolerate it; staff seeding may need a maintenance role/permission mapping. | Medium | Additive enum case; ops-queue permission is per queue-type, not department, so no auth change. |
| R8 | **Bookable discovery gap.** `spa_services` / `restaurant_tables` / `transfers` have only CMS routes — a guest cannot enumerate `bookable_uuid`s for `POST /service-bookings`, so the Restaurant `link` flow dead-ends at table selection. | Medium | Out of scope here; needs `GET /public/…` reads (mirror the dining-venues pattern) in the same milestone as the Restaurant link. |
| R9 | **No-show reservations never reach "past".** A `confirmed` stay whose dates lapse without check-in stays out of all three views until staff cancel it. | Low | Ops hygiene (auto-cancel job) — noted, not solved here. |
| R10 | **Legacy free-string `type`.** Existing app builds may post arbitrary `type` values; validation stays `required_without`, so nothing breaks; catalog analytics must tolerate non-catalog types. | Low | Contract kept additive throughout. |

---

## 9. Implementation checklist (for the executing engineer)

1. Migrations 1–4 (§1.6) + `ServiceCatalogSeeder`; `Department::MAINTENANCE`; `ServiceCategoryKind` enum.
2. Models `ServiceCategory`, `ServiceItem` (`HasTranslations`, `HasUuid`, `LogsActivity`); `ServiceRequest::serviceItem()`; `Reservation` casts for the three new timestamps.
3. Extend `PlaceServiceRequestAction` (item resolution → type/department snapshot) + `PlaceServiceRequestRequest` (§4.2).
4. `ServiceCatalogController` (public read) + resources; admin `apiResource` controllers extending `BaseCRUDController` with `cms.edit`.
5. `StayService` + `StayController` + per-view resources; `SetDndAction`; receipt service reading folio/items/payments read-only; mpdf receipt renderer.
6. Stamp `checked_in_at` / `checked_out_at` inside the existing check-in and folio-approve transitions (same transaction).
7. Admin companion: expose `dnd_until` in admin reservation resource (R3).
8. Translations for every new string in `lang/en/custom.php` **and** `lang/ar/custom.php`; feature tests per endpoint group (happy + unauth + wrong tier + validation) asserting the envelope; update `docs/API_GUIDE_MOBILE.md` after implementation.
