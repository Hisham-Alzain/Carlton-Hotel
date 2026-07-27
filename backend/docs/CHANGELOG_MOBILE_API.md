# Changelog — Mobile API milestone

Every implementation change made to satisfy the mobile app's API list, across
nine commits (`2fd294f` → `5d254a7`).

**Totals:** 158 files, +8 035 / −358 lines · 15 migrations · 9 new test classes ·
366 tests green (980 assertions) · `migrate:fresh --seed` clean.

| # | Commit | Module |
|---|---|---|
| 1 | `2fd294f` | Amenity catalog, bed/view types, cancellation window |
| 2 | `759193a` | Guest reviews + denormalized ratings |
| 3 | `8cf4fd5` | Home sliders |
| 4 | `de94705` | Venue-scoped menus, vegan/photo, table reservation |
| 5 | `37192c4` | Offer secondary description, guest profile |
| 6 | `56e62c4` | Two-level service catalog + folio billing |
| 7 | `5dc2f13` | Stay views, do-not-disturb, receipts + PDF |
| 8 | `f742cea` | Documentation (guide + Postman) |
| 9 | `5d254a7` | Room reserved at booking time; public bookables |

---

## ⚠️ Breaking changes

Three, all affecting the **dashboard**, none affecting existing mobile builds.

| What | Before | After |
|---|---|---|
| `RoomTypeResource.amenities` | `["WiFi", "Safe"]` | `[{uuid, slug, name, icon, sort_order}]` |
| Room type create/update `amenities` | `["WiFi"]` | `[{uuid, is_highlight?, sort_order?}]` — omit the key to leave the pivot untouched, send `[]` to clear |
| `POST`/`PUT /cms/menu-categories` | venue-less | `dining_venue_uuid` now **required** |

The legacy `room_types.amenities` JSON column still exists and is still
writable, but no resource reads it — a data migration lifted its contents into
real amenity rows.

Non-breaking but behavioural: `POST /cms/reservations/{uuid}/assign-room` now
accepts an **optional** `room_uuid` (see §9).

---

## 1 — Amenity catalog, bed/view types, cancellation window (`2fd294f`)

**Why:** the room-details screen needed view type, bed configuration, a free-
cancellation window, and a structured amenity list with a 4-item "highlights"
subset. None of it existed.

**Migrations**
- `create_amenities_table` — `uuid`, `slug` (unique), `name` (JSON), `icon`, `is_active`, `sort_order`
- `create_amenity_room_type_table` — pivot with `is_highlight`, `sort_order`, unique `(amenity_id, room_type_id)`
- `add_mobile_fields_to_room_types_table` — `view_type` (indexed), `bed_types` (JSON), `cancellation_hours` (default 48)
- `migrate_room_type_amenities_to_pivot` — **data migration**: lifts each legacy free-text string into an `Amenity` row (AR mirrors EN until edited) and attaches it; first four become highlights

**New:** `BedType` enum (king/queen/double/twin/single/extra) · `RoomView` enum
(city/garden/pool/courtyard/mountain/interior) · `Amenity` model ·
`AmenityResource` · `AmenityService` · `Admin\AmenityController` ·
`Api\AmenityController` · `Create/UpdateAmenityRequest` · `AmenityFactory` ·
`AmenitySeeder` (the six catalog entries) · `AmenityTest`

**Changed:** `RoomType` gains the `amenityList()` belongsToMany and
`highlightAmenities()` (flagged first, topped up from the head of the list —
reads the loaded relation, never queries) · `RoomTypeResource` gains
`view_type`, `bed_types`, `cancellation_hours`, `banner`, `amenities`,
`highlights` · `RoomTypeService` syncs the pivot inside a transaction ·
`Create/UpdateRoomTypeRequest` · `RoomTypeFactory`, `CmsContentSeeder`

**Endpoints:** `GET /public/amenities` · full CRUD under `/cms/amenities`

---

## 2 — Guest reviews + denormalized ratings (`759193a`)

**Why:** room details and restaurant info both show a star rating; there was no
source for one.

**Migrations**
- `create_reviews_table` — polymorphic `reviewable`, `guest_id`, nullable
  `reservation_id`, `rating`, `comment`, `is_verified_stay`, `is_published`,
  unique `(guest_id, reviewable_type, reviewable_id)`
- `add_rating_columns_to_reviewables` — `rating_avg` DECIMAL(2,1) + `rating_count` on `room_types` **and** `dining_venues`

**New:** `Review` model · `HasReviews` trait · `ReviewableType` enum ·
`RecalculateRatingAction` · `SubmitReviewAction` · `SetReviewPublishedAction` ·
`ReviewService` · `ReviewResource` · `Api\ReviewController` ·
`Admin\ReviewController` · `ReviewFactory` · `ReviewSeeder` · `ReviewTest`

**Key decisions**
- `RecalculateRatingAction` is the **only** writer of the aggregates and runs
  inside the same transaction as the review change, so the cache cannot drift.
- `is_verified_stay` is derived server-side from the guest's
  checked-in/checked-out history — never accepted from the client.
- `ReviewableType` resolves `{type}` instead of `Relation::morphMap()`:
  `RoomType` and `DiningVenue` both use `LogsActivity`, so aliasing them would
  retroactively rewrite `subject_type` on every logged activity and
  `mediable_type` on every uploaded image.
- Re-submitting **edits** the existing review (201 first time, 200 after).

**Endpoints:** `GET /public/reviews/{type}/{uuid}` ·
`POST /reviews/{type}/{uuid}` (tier-2) · `GET /cms/reviews` ·
`PATCH /cms/reviews/{review}/publish`

**Also changed:** `ReservationFactory` gains a `checkedOut()` state.

---

## 3 — Home sliders (`8cf4fd5`)

**Why:** the home hero carousel had no backing model at all.

**Migration:** `create_home_sliders_table` — `header_text`, `location`,
`description_text` (all translatable JSON), `is_active`, `sort_order`

**New:** `HomeSlider` model · `HomeSliderResource` · `HomeSliderService` ·
`Admin\HomeSliderController` · `Api\HomeSliderController` ·
`Create/UpdateHomeSliderRequest` · `HomeSliderFactory` · `HomeSliderTest`

**Changed:** `MediaController` gains `storeHomeSlider`/`destroyHomeSlider`;
`CmsContentSeeder` seeds the two slides.

The photo goes through the shared media morph (so uploads reuse `MediaService`
and the existing `/images` routes) but the resource exposes a single `photo` —
a slide carries exactly one image.

**Endpoints:** `GET /public/home-sliders` · CRUD + image upload under
`/cms/home-sliders`

---

## 4 — Venue-scoped menus, vegan/photo, table reservation (`de94705`)

**Why:** menus were one global list; mobile shows a menu **per restaurant** with
type chips and a vegan badge, and books a table from a party size.

**Migrations**
- `add_venue_and_slug_to_menu_categories` — `dining_venue_id` + `slug`, unique
  `(dining_venue_id, slug)`; backfills existing global categories onto the
  lowest-sorted venue and derives slugs from `name.en`
- `add_vegan_flag_to_menu_items` — `is_vegan` (indexed)
- `add_guest_count_to_service_bookings` — nullable `guest_count`

**New:** `ReserveTableAction` · `ReserveTableRequest` · `MenuFilterRequest` ·
`Api\MenuController` · `Api\TableReservationController` ·
`TableReservationTest`

**Changed:** `MenuCategory` (venue relation, slug) · `MenuItem` (`is_vegan`,
media morph) · `MenuCategoryResource` (+`slug`, `dining_venue_uuid`) ·
`MenuItemResource` (+`type`, `is_vegan`, `photo`) · `MenuCategoryService`,
`MenuItemService` · `ServiceBooking` + `CreateServiceBookingAction`
(`guest_count`) · `ServiceBookingResource` · `ServiceBookingStatus` gains
`blockingSeating()` · `MediaController` gains menu-item image routes ·
`ServiceCatalogSeeder` seeds four categories per venue with Arabic copy

**Key decisions**
- `slug` is the stable filter key the app sends back as `?type=`, so renaming a
  category's display name never breaks the client.
- `ReserveTableAction` assigns the **smallest** table that seats the party and is
  free for a two-hour seating window, so six-tops stay available for six-tops.
  Cancelled and completed seatings free the table immediately.
- Table reservation sits behind `auth:guests` + `has_booking` — the same tier as
  every other service booking (ARCHITECTURE §3.7).

**Endpoints:** `GET /public/dining-venues/{uuid}/menu-categories` ·
`GET /public/dining-venues/{uuid}/menu?type=` ·
`POST /dining-venues/{uuid}/table-reservations`

---

## 5 — Offer secondary description, guest profile (`37192c4`)

**Migration:** `add_secondary_description_to_promotions` — translatable JSON

**New:** `UpdateGuestProfileAction` · `UpdateGuestProfileRequest` ·
`VerifiedContactImmutableException` (`verified_contact_immutable`, 409) ·
`GuestProfileTest`

**Changed:** `Promotion` model + resource (`secondary_description`, `banner`) ·
`Create/UpdatePromotionRequest` · `CmsContentSeeder` · `AuthGuestService` ·
`GuestAuthController`

`terms` was left alone — it means legal small print, not marketing copy, so the
second block of offer copy got its own column.

**Profile rules:** names and locale are freely editable; a phone or email may be
**filled in** when the guest has none (the "create profile" step after signing in
through the other channel); replacing an already-verified contact is refused
rather than silently moving the login identifier without proof of ownership.
Phones normalize to E.164; uniqueness exempts the current guest.

**Endpoint:** `PUT /auth/guest/profile`

---

## 6 — Two-level service catalog + folio billing (`56e62c4`)

**Migrations**
- `create_service_categories_table` — `code` (unique), `name`, `description`,
  `kind`, `department`, `link_target`, `icon`, `is_active`, `sort_order`
- `create_service_items_table` — `expected_minutes`, `price_usd`, `is_default`,
  composite catalog index
- `add_service_item_id_to_service_requests_table` — nullable FK, `nullOnDelete`

**New:** `ServiceCategoryKind` enum · `ServiceCategory` / `ServiceItem` models ·
`ServiceCategoryResource` / `ServiceItemResource` · `ServiceCatalogService` ·
`ServiceCategoryService` / `ServiceItemService` · `Api\ServiceCatalogController` ·
`Admin\ServiceCategoryController` / `Admin\ServiceItemController` ·
`StoreServiceCategoryRequest` / `StoreServiceItemRequest` · factories ·
`GuestServiceCatalogSeeder` · `ServiceCatalogTest`

**Changed:** `Department` gains `MAINTENANCE`; `forServiceType()` also maps
`laundry` and `maintenance` · `PlaceServiceRequestAction` resolves an item to
its category · `PlaceServiceRequestRequest` (`service_item_uuid`, `type` becomes
`required_without`) · `ServiceRequest` model + resource ·
`ServiceRequestService` eager-loads the item · **`GenerateFolioAction` ingests
priced catalog requests**

**Key decisions**
- A category's `kind` drives the client — `catalog` (item picker), `direct`
  (request the hidden default item straight away), `link` (open an existing
  module), `toggle` (a switch). Putting that in data means a new category needs
  no app release.
- `type` and `department` stay **snapshotted strings** even for catalog
  requests: the P10 operations queue keys on them, and deactivating an item must
  never rewrite the history of a request staff already worked.
- Billing happens in `GenerateFolioAction`, **not** at request time — that action
  deletes and rebuilds all line items on every call, so a row written at request
  time would vanish on the next regeneration. Cancelled requests and null-priced
  items are never charged.
- `expected_minutes` is an integer, not a formatted string: locale-free,
  sortable, and usable later for SLA highlighting.

**Endpoints:** `GET /public/service-catalog` · CRUD under
`/cms/service-categories` and `/cms/service-items` ·
`POST /service-requests` extended

---

## 7 — Stay views, do-not-disturb, receipts + PDF (`5dc2f13`)

**Dependency added:** `mpdf/mpdf`

**Migration:** `add_stay_timestamps_and_dnd_to_reservations_table` —
`checked_in_at`, `checked_out_at`, `dnd_until`

**New:** `StayService` · `ActiveStayResource` / `UpcomingStayResource` /
`PastStayResource` · `Api\StayController` · `SetDndAction` · `SetDndRequest` ·
`ReceiptService` · `ReceiptPdfRenderer` · `ReceiptResource` ·
`resources/views/pdf/receipt.blade.php` · `StayTest`

**Changed:** `Reservation` casts + `nightsRemaining()`, `isDndActive()`,
`folio()` · `AssignRoomAction` stamps `checked_in_at` · `ApproveFolioAction`
stamps `checked_out_at` · `ReservationResource` exposes the timestamps and DND
to staff

**Key decisions**
- The three reads sit behind plain `auth:guests`, not `is_checked_in`: having no
  active stay is an **empty state**, and 403-ing would force the app to treat
  "not staying right now" as a failure.
- Checkout reuses `POST /folio/approve` and cancellation reuses
  `DELETE /reservations/{uuid}` — both already do exactly this work.
- **No book-again endpoint.** Past stays carry `room_type_uuid`; the app
  deep-links into the existing quote → `POST /reservations` flow, because a new
  booking needs fresh dates, availability and price.
- The receipt reads a **closed** folio without triggering regeneration, so a past
  stay reprints what the guest actually paid. Ownership mismatches 404, never
  403.
- **mpdf, not dompdf**: dompdf does not shape Arabic or handle RTL, so AR
  receipts would render as disconnected reversed glyphs. Rendered on demand — a
  one-page receipt is sub-second and storing it would need invalidation.
- DND is an expiring timestamp, not a boolean or a queue row: a forgotten toggle
  clears itself, and a never-closing "DND" ticket would pollute the ops queue.

**Endpoints:** `GET /stays/active` · `/stays/upcoming` · `/stays/past` ·
`GET /stays/{uuid}/receipt` · `/receipt/pdf` · `PATCH /stays/active/dnd`

---

## 8 — Documentation (`f742cea`)

`API_GUIDE_MOBILE.md` rewritten around the mobile surface: home-screen field
mapping, room details, restaurant info + menu filtering, reviews, the service
catalog and its four `kind` behaviours, the extended service-request contract
and its billing rule, the three stay views, receipts, DND, and the shipped
profile endpoint (replacing the "Coming in P12" placeholder).

Postman: **26 new requests** including a `17 - Stays (Guest)` folder, plus five
environment placeholders the seeder does not pin to a fixed row
(`amenity_uuid`, `home_slider_uuid`, `service_category_uuid`,
`service_item_uuid`, `review_uuid`).

---

## 9 — Room reserved at booking time; public bookables (`5d254a7`)

**Why:** rooms were only assigned at check-in, so an upcoming stay could not show
the room number. A booking now picks a specific room the moment it is created.

**New:** `Api\BookableController` · `RoomAssignmentAtBookingTest` ·
`PublicBookableTest`

**Changed:** `CheckAvailabilityAction` gains `findFreeRoom()` and refactors
occupancy onto a shared `overlapping()` query · `Reservation` gains
`scopeHoldingInventory()` · `CreateReservationAction` assigns the room ·
`AssignRoomAction` becomes check-in with an optional room · `AssignRoomRequest`
(`room_uuid` nullable) · `Admin\ReservationController` · `ReservationService` ·
`UpcomingStayResource`

**Key decisions**
- `findFreeRoom()` picks the **lowest-numbered** free room of the type, inside
  the existing `room_type` lock.
- Capacity is still decided by the **occupancy count first**. Overlapping
  bookings created before this change — and holds placed through other paths —
  carry a null `room_id` but must keep consuming inventory; filtering only on
  assigned room ids would have made them stop blocking, and the hotel could
  oversell. (A regression the existing `ConcurrencyTest` caught.)
- `scopeHoldingInventory()` is now the single predicate for "this booking still
  holds its room", shared by availability counting and staff assignment so the
  two cannot disagree. `assign-room`'s conflict check widened to it, so a room
  held by a merely `pending` booking can no longer be handed to someone else.
- `assign-room` takes an **optional** `room_uuid`: omit it to check the guest
  into the room they already hold, send it to move them.

**Endpoints:** `GET /public/spa-services` · `/public/pool-cabanas` ·
`/public/transfers` · `/public/dining-venues/{uuid}/tables`

---

## Full endpoint inventory (51 routes added or changed)

### Public (tier-1, no token)
```
GET    /public/home-sliders
GET    /public/amenities
GET    /public/service-catalog
GET    /public/reviews/{type}/{uuid}
GET    /public/dining-venues/{uuid}/menu-categories
GET    /public/dining-venues/{uuid}/menu
GET    /public/spa-services
GET    /public/pool-cabanas
GET    /public/transfers
GET    /public/dining-venues/{uuid}/tables
```

### Guest (tier-2, `auth:guests`)
```
PUT    /auth/guest/profile
POST   /reviews/{type}/{uuid}
GET    /stays/active
GET    /stays/upcoming
GET    /stays/past
GET    /stays/{reservation}/receipt
GET    /stays/{reservation}/receipt/pdf
```

### Guest with a booking (tier-3a, `has_booking`)
```
POST   /dining-venues/{diningVenue}/table-reservations
```

### Guest in-room (tier-3b, `is_checked_in`)
```
PATCH  /stays/active/dnd
POST   /service-requests            (extended: service_item_uuid)
```

### Admin (`auth:users` + `cms.edit` unless noted)
```
GET|POST|PUT|DELETE  /cms/amenities[/{amenity}]
GET|POST|PUT|DELETE  /cms/home-sliders[/{homeSlider}]
POST|DELETE          /cms/home-sliders/{homeSlider}/images[/{media}]
POST|DELETE          /cms/menu-items/{menuItem}/images[/{media}]
GET                  /cms/reviews
PATCH                /cms/reviews/{review}/publish
apiResource          /cms/service-categories
apiResource          /cms/service-items
POST                 /cms/reservations/{uuid}/assign-room   (room_uuid now optional)
```

---

## Known gaps the frontend must design around

1. **No `complete` status.** You get raw `checked_out` / `cancelled`; label
   `checked_out` as "Completed" client-side. Renaming server-side would break
   the published website and admin contracts.
2. **`checked_in_at` is null for reservations created before commit `5dc2f13`.**
   Only relevant to data that already exists in a deployed environment — a fresh
   `migrate:fresh --seed` has none. Fall back to the `check_in` date.
3. **Folio line descriptions are not translatable** (`folio_items.description`
   is a plain string), so Arabic receipts show line items in English. Fixing it
   needs a migration to a JSON column plus a backfill.
4. **No book-again endpoint** — by design, see §7.
5. **`database/database.sqlite` is tracked in git** and changes on every
   `migrate:fresh`. It predates this milestone but now churns on every commit;
   worth gitignoring.
