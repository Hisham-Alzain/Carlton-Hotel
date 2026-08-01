# Carlton Mobile — Backend API Integration Plan

> Status: **in progress.** ✅ Phase 0 done (analyze clean, tests). ✅ Phase 1 done
> (auth unified; analyze clean, tests). ✅ Phase 2 done — Home rails, Discover, and
> the dining-menu fetch are wired to `/public/*`; DTOs in place; 31 tests, analyze
> clean. Two Phase-2 pieces deliberately deferred (see the Phase 2 "Delivered" note):
> the **services-catalog fetch** folds into Phase 5 (where its `kind`-switch is
> actually consumed), and the dining **about/gallery** stay demo. ✅ Phase 3 done —
> booking wired to `GET /public/quote` + `POST /reservations`, real `Reservation`
> DTO, payment mapping; analyze clean, tests pass. All needs on-device verification
> against the live backend. ✅ **Phases 4–7 done** — delivered via a multi-agent
> workflow (parallel blueprints → serial dependency-ordered implementation →
> verify → adversarial review); analyze clean, 62 tests pass. See "Phases 4–7 —
> workflow delivery" below for what landed, what stayed demo, and open follow-ups.
> **All 8 phases (0–7) now integrated; everything needs on-device verification
> against the live backend.**

---

## Phases 4–7 — workflow delivery (summary)

Implemented serially in dependency order, each analyze-gated; then an independent
`flutter analyze` + `flutter test` (**0 issues, 62 tests pass**) and an adversarial
review.

- **Phase 4 (Stays/Folio/Checkout):** `stay.dart` (ActiveStay/UpcomingStay/PastStay,
  all carrying `uuid`+`booking_code` from the real resources), `folio.dart`,
  `receipt.dart`. `StaysController` = real data layer: active (nullable), upcoming
  (array), past (`PaginatedControllerMixin` — its first real use, Obx island),
  receipt + PDF download, cancel (`DELETE /reservations/{uuid}` + entitlement resync
  via `checkToken()`). Home DND → `PATCH /stays/active/dnd`, checkout →
  `POST /folio/approve`. Added a `patch` verb to `ApiService`. Retired
  `upcomingStays()`/`pastStays()`/`_receipt()`.
- **Phase 5 (In-stay & pre-arrival):** `ServiceRequest` rewritten (getter-alias DTO,
  cards unchanged); `ServicesController` fetches `/public/service-catalog` and
  switches on `kind` (catalog→list, direct→notes sheet, link→Discover dining,
  toggle→DND sheet); `POST /service-requests` (checked-in gated). Table reservations
  (`RestaurantController.confirmReservation`), pre-arrival document upload (indexed
  multipart). Deleted `service_models.dart`; retired `serviceCategories`/
  `serviceCategoryByName`/`initialActiveRequests`/`homeActiveRequests`.
- **Phase 6 (Chat & notifications):** `ChatMessage` extended + `Conversation` DTO;
  AI-Concierge tab 1 → `GET/POST /conversations` (+ image via `postWithFiles`),
  pull-to-refresh, attachment picker; tab 0 stays the coming-soon P11 stub;
  `notifications_service` → `POST /device-tokens` (kIsWeb-guarded). Retired
  `customerServiceThread()`. No Firestore live-mirror (deferred).
- **Phase 7 (Reviews):** `ReviewController` (`PaginatedControllerMixin<Review>`),
  submit sheet (star input), review tile, a Reviews tab on restaurant detail + a
  Write-a-Review CTA on room details (auth-gated). `review.dart` gained
  `ReviewTargetType`. No demo retired (net-new UI).

**Fixed post-review:** the dead "Book a Stay"/"Book Again" CTA (`startBooking` →
switch to the Book tab, same `planStay`-gap fix as Phase 3) and a double error UI on
document upload (`postWithFiles showDialog:false`).

**Open follow-ups (documented, not blocking analyze/tests):**
1. **Pre-arrival documents screen has no in-app entry point** (route/binding exist,
   no Figma frame) — add a tile/link when the design lands.
2. **DND toggle isn't hydrated from server state** — `ActiveStay.dndEnabled` is
   parsed but the switch always opens "off"; wire it when Home's active dashboard is
   converted off demo.
3. **Home active-booking dashboard + bill stay demo** (`activeStay()`,
   `currentBillLines/Total`) — deferred: the Figma hero assumes checked-in, but a
   `has_booking`-not-checked-in guest has no pre-arrival dashboard state. Convert via
   `GET /stays/active` (+ `GET /folio`, guarding null/403) without redesigning Figma.
4. `CustomPastStayCard` hard-codes a "COMPLETED" pill, so a cancelled past stay reads
   "COMPLETED" (card not in scope to redesign).
5. PDF receipt saves to temp dir + snackbar; opening/sharing needs a viewer dep
   (`open_filex`/`share_plus` absent) — flagged, not silently added.
6. Table reservations, multipart doc/chat-image upload, and file_picker v12 need
   **on-device verification** against the live backend.

## Context

The Carlton Hotel Flutter app (`mobile/`, GetX architecture, package `carlton`)
already has a complete, well-built networking layer — `ApiService`/`ApiClient`
(Dio + interceptor chain: connectivity → headers/locale → auth bearer → logger →
retry → error-envelope parsing), `ApiResponse`/`ApiException` (envelope types),
`PaginatedControllerMixin` (reusable infinite-scroll contract), and file
upload/download helpers. None of it is actually wired to the real backend yet.
Per `mobile/CLAUDE.md`, every screen currently runs on hardcoded values in
`constants/demo_data.dart` (872 lines, explicitly documented as "nothing here
should survive integration"), and auth has two parallel, non-communicating
mechanisms (`SessionService`'s fake signed-in booleans vs `MiddlewareService`'s
real-but-misdirected token check).

The backend (Laravel, `backend/docs/API_GUIDE_MOBILE.md`, cross-checked against
`backend/docs/postman/carlton-api.postman_collection.json`) exposes a complete
guest-facing REST API across auth, public content, booking, in-stay services,
stays/folio, and chat/notifications. This plan wires every mobile-relevant
endpoint into the existing screens, in dependency order, replacing demo data
phase by phase rather than in one large rewrite.

This was produced via direct code audit (3 parallel Explore passes over auth,
booking/stays, and content/chat) plus a dedicated planning pass, then reviewed
against 4 scope questions with the user. Decisions from that review are baked
into the phases below — see "Scope decisions" first.

## Scope decisions (from user review — do not re-litigate during implementation)

1. **Payment method:** Keep the existing 4-option UI (Cash / Card / Apple Pay /
   Google Pay / Pay at Hotel) as-is visually. Since the backend's
   `POST /reservations` only accepts `payment_method: cash|on_arrival`, map
   functionally: `cash → cash`, `payAtHotel → on_arrival`; card/applePay/googlePay
   remain selectable but are not wired to any real gateway — treat them as a
   disabled/"coming soon" state at the moment of submission (same pattern the app
   already uses elsewhere for not-yet-built features) rather than silently sending
   a bogus `payment_method` string to the backend. No real payment gateway
   integration in this plan.
2. **"Experiences" rail (Home/Discover):** stays on DemoData permanently — do not
   wire it to any backend endpoint. There is no server-side "experiences" concept,
   and the user chose to keep this rail faked rather than mis-map it to
   event-spaces/promotions. Leave the corresponding `DemoData.experiences`-style
   members in place; do not delete them in the Phase 2 cleanup pass.
3. **Facilities / Event Spaces / Pages / Promotions / Amenities:** fully out of
   scope. No DTOs, no screens, no wiring. These five public content endpoints exist
   server-side but have no mobile screen today and none should be built as part of
   this plan. (Consequence: Event Inquiry, which only makes sense attached to an
   Event Space detail screen, is also dropped from scope — see Phase 7.)
4. **Service-request cancellation:** remove the existing "cancel active request"
   affordance in `ServicesController` entirely (button + confirm dialog) — no
   backend endpoint lets a guest cancel a service-request, and a client-only cancel
   that silently desyncs from what staff sees is worse than no button.

## Guiding principles

- Never touch the interceptor chain or envelope contract (`ApiService`,
  `ApiClient`, `ApiResponse`, `ApiException` are correct as-is). All work is call
  sites + models.
- No abstraction beyond what's needed. DTOs replace the existing hand-built
  demo/UI models in place (add uuid + real fields, drop pre-formatted
  display-string fields) rather than introducing a separate "raw DTO → UI model"
  mapping layer. Two shared exceptions, used narrowly because they're each reused
  15+ times: a `Bilingual{en,ar}` value type (resolves against current locale
  live, avoiding a refetch on language switch) and a
  `MediaImage{uuid,url,file_name,sort_order}` type with a banner helper.
- `demo_data.dart` shrinks phase by phase. Each phase below states exactly which
  members become dead and should be deleted in that phase, not deferred to one
  final sweep.
- New domain DTOs live directly under `lib/models/` (e.g. `lib/models/guest.dart`)
  — `lib/models/api/` stays reserved for envelope types only.

---

## Phase 0 — Foundation (blocks everything; do first)

1. **Fix `ApiService.host` default** (`lib/services/api/api_service.dart`) —
   currently `'https://api.offershi.com/'`, a leftover from the CartX template this
   app was bootstrapped from. Replace with the Android-emulator-safe dev default
   (`http://10.0.2.2:8000`, matching the existing ADB-reverse doc comment), and
   update the doc comment's `--dart-define=API_HOST=...` examples to reference
   Carlton instead of Offershi/CartX.
2. **Add guest-specific error codes** to `constants/error_codes.dart`:
   `identity_required`, `otp_expired`, `otp_invalid`, `otp_locked`,
   `booking_link_failed`, `verified_contact_immutable`, `no_active_reservation`,
   `no_availability`, `invalid_promo`, `reservation_state`. Pure addition, matches
   the file's existing static-const style.
3. **Unify pagination types.** Two parallel meta classes exist:
   `lib/models/api/paginated_meta.dart` (`PaginationMeta`, used only inside
   `api_response.dart` itself) vs `lib/models/pagination.dart` (`Pagination`, what
   `PaginatedControllerMixin` actually consumes). Delete `paginated_meta.dart`;
   change `ApiResponse`'s paginated-envelope branch to parse into `Pagination`
   instead. This makes every paginated `ApiService.get(...)` call's `.meta` directly
   usable by the mixin with no adapter.
4. **Add shared value types:** `lib/models/bilingual.dart`
   (`Bilingual{en,ar}`, `.value` getter resolving against
   `SettingsService.find.locale` live) and `lib/models/media_image.dart`
   (`MediaImage{uuid,url,fileName,sortOrder}` + static
   `MediaImage? banner(List<MediaImage>)`).
5. **Delete `models/user.dart` and the dead `StorageKeys.user`** — zero current
   readers, wrong shape (generic staff/customer user), superseded by the new Guest
   model in Phase 1.

No screen work in this phase — pure plumbing/model scaffolding.

---

## Phase 1 — Auth & unified session

**Depends on:** Phase 0. Can run in parallel with Phase 2 (content) — no shared code.

### Unify the two auth mechanisms

`MiddlewareService` (`lib/services/middleware_service.dart`) becomes the single
source of truth for token + guest identity — it already has the right shape
(permanent singleton, Rx state, a `checkToken()` lifecycle hook), it just calls
the wrong endpoint through the wrong pipe (`dio.get('/user/check-token')`
directly, bypassing `ApiService`'s envelope handling). Changes:

- Repoint `checkToken()` at `GET /api/auth/guest/me` via
  `ApiService.find.get<Map<String,dynamic>>(...)`, parse into a new Guest model,
  store as `Rx<Guest?> guest`.
- Delete the generic `userType`/`role` fields (no "customer vs other role" concept
  for a guest app).
- Add `saveSession({required String token, required Guest guest})` — the only
  place a token is ever written (called once, from `OtpVerifyController.verify()`
  on success).
- Add `signOut()` — clears token + guest state; called from
  `AccountController.confirmSignOut()` and added to `ApiService._handleUnauthorized`'s
  existing 401 path (which already clears the token but currently leaves a stale
  Guest object behind).
- Add convenience getters: `isAuthenticated`, `hasBooking`, `isCheckedIn` (from the
  guest's `has_booking`/`is_checked_in`) — these replace every current read of
  `SessionService.isSignedIn`/`hasReservation` across `home_controller.dart`,
  `home_active_booking_view.dart`, `home_view.dart`, `account_controller.dart`,
  `services_controller.dart`, `splash_screen_controller.dart`,
  `find_booking_controller.dart`, `otp_verify_controller.dart`.

`SessionService` (`lib/services/session_service.dart`, confirmed by direct read:
`isSignedIn`/`markSignedIn`/`signOut`/`hasReservation`/`markHasReservation`/
`attachPendingReservation` are pure demo scaffolding) — delete the auth-boolean
half entirely. Keep and rename its legitimate non-auth job: stashing a booking
code + last name between the "Find Booking" screen and OTP verification. Since
Reservation is about to become a real DTO in Phase 3 (not a 2-field stash),
replace its use as the stash type with a small dedicated
`PendingBookingLink{bookingCode, lastName?, phone?}`. Rewrite the doc comment to
state this is a scratch bridge only, not an auth mechanism.

New storage key: `StorageKeys.guest` (cached guest JSON, read on splash before the
`/me` round-trip completes, so `SplashScreenController` isn't network-blocked on
cold start).

### OTP-first flow reorder

Current demo order is Create Profile → Phone Entry → OTP Verify, which is
backwards: `PUT /auth/guest/profile` requires an already-authenticated token, and
verify-otp itself returns any existing name fields. New order:

1. `PhoneEntryController.submit()` (and `SignInController.submit()` for the
   returning-guest path) call `POST /auth/guest/request-otp {channel, phone|email,
   purpose}` instead of `Future.delayed`; navigate to OTP verify with a richer
   argument object (channel, purpose, identifier) rather than today's bare display
   string.
2. `OtpVerifyController.verify()` calls `POST /auth/guest/verify-otp`; on success
   calls `MiddlewareService.find.saveSession(...)`, then branches to
   `Routes.welcomeBack` (guest already has a name) or `Routes.createProfile` (new
   guest) — never unconditionally to welcomeBack as today. Resend now re-calls
   request-otp (or link-booking-code again if `purpose == booking_link`) instead of
   resetting a local timer. Distinguish `otp_expired`/`otp_invalid`/`otp_locked`
   via `error!.errorCode` into different UI states instead of one generic `hasError`
   bool.
3. `CreateProfileController.submit()` now runs after OTP verify (route stays the
   same name/path — only who navigates to it changes) and calls
   `PUT /auth/guest/profile` with the now-present bearer token, then
   `Get.offAllNamed(Routes.main)`.
4. `FindBookingController.submit()` calls `POST /auth/guest/link-booking-code
   {booking_code, last_name|phone}` — currently only sends last_name, must send
   whichever field the screen actually collected. On success, show the real
   `masked_contact` from the response (replacing the hardcoded
   `AppTranslations.reservationPhoneDestination` string) and navigate to OTP with
   `purpose: booking_link`.
5. Mirror `guest.preferred_locale` into `SettingsService` inside `saveSession`
   itself (one place, fires exactly once when a guest object first arrives) —
   required by the guide, currently not done anywhere.

### New affordance needed

No edit-profile screen exists (only onboarding create-profile). Add a
`Routes.editProfile` reusing `CreateProfileView`'s form pre-filled from
`MiddlewareService.find.guest`, reachable from `AccountController` — this is the
only home for `PUT /auth/guest/profile` outside onboarding.

**Files:** new `lib/models/guest.dart`, `lib/models/pending_booking_link.dart`,
`lib/models/otp_verify_args.dart`; changed `lib/services/middleware_service.dart`,
`lib/services/session_service.dart`, `lib/services/api/api_service.dart` (one-line
addition to `_handleUnauthorized`),
`lib/controllers/auth/{phone_entry,sign_in,otp_verify,create_profile}_controller.dart`,
`lib/controllers/booking/find_booking_controller.dart`,
`lib/controllers/splash/splash_screen_controller.dart`,
`lib/controllers/account/account_controller.dart` (name/email from
`MiddlewareService.find.guest`, sign-out calls `MiddlewareService.find.signOut()`),
new edit-profile view/route/binding entry.

**demo_data.dart retired this phase:** `otpCode`, `otpResendSeconds`, `userName`,
`userEmail`.

**Tests:** `otp_verify_controller_test.dart` (new) — asserts branch to welcomeBack
vs createProfile, and distinct states for otp_expired/otp_invalid/otp_locked.
`middleware_service_test.dart` (new, first HTTP-mocked test in the repo — pick a
Dio test-double approach here, reuse for later phases) — asserts `checkToken()`
populates guest from a mocked 200 and clears session on a mocked 401. Update the
existing `home_reservation_state_test.dart` (currently reads
`SessionService.hasReservation`) to read the new `MiddlewareService` booleans
instead.

---

## Phase 2 — Public content

**Depends on:** Phase 0 only (everything here is public/tier-1). Runs in parallel
with Phase 1.

1. **Shared DTOs first:** `lib/models/room_type.dart`, `lib/models/room.dart`,
   `lib/models/dining_venue.dart`, `lib/models/service_catalog_item.dart` (with
   nested item options), `lib/models/review.dart`, extend the existing
   `models/service_item.dart`-family and menu-item model with real fields (uuid,
   bilingual name/description, price_usd, is_vegan, photo). (No
   Facility/EventSpace/Page/Promotion/Amenity DTOs — out of scope per decision #3.)
2. **Home** (`controllers/home/home_controller.dart`): hero slider, rooms rail,
   restaurants rail → `GET /public/home-sliders`, `GET /public/room-types`,
   `GET /public/dining-venues` as small first-page fetches (not full pagination —
   Home shows a bounded rail). Leave the Experiences rail on DemoData untouched per
   decision #2.
3. **Discover** (`controllers/home/discover_controller.dart`): rewrite to mix in
   `PaginatedControllerMixin<T>` for Rooms/Dining tabs (this is exactly the contract
   the mixin exists for). Drop the current hardcoded category-chip strings that map
   to nothing server-side; if the room-types/dining-venues list endpoints don't
   expose filter query params beyond pagination, drop the chip-filter UI down to
   pagination only rather than faking client-side filtering over a partial page.
   Experiences tab stays on DemoData, same as Home.
4. **Dining detail** (`controllers/dining/restaurant_controller.dart`): switch
   `Get.arguments` from a full demo object to a uuid string; fetch
   `GET /public/dining-venues/{uuid}`; fetch `GET .../menu-categories` for the
   filter chips (replacing index-based static filtering with slug-based); fetch
   `GET .../menu?type=slug` (paginated, mix in the pagination contract). Reserve-tab
   wiring (table reservations) is Phase 5 — this phase only lands the data-fetch
   half.
5. **Services hub tiles** (`controllers/home/services_controller.dart`): grid +
   category trees → `GET /public/service-catalog` (unpaginated array), switching on
   `kind` (catalog → item list, direct → notes sheet, link → navigate via
   link_target, toggle → DND switch). This phase wires the fetch + navigation switch
   only; what each destination actually submits is Phase 5.

**demo_data.dart retired this phase:** `restaurants`, `rooms`, `services`,
`serviceCategoryByName`, `restaurantServiceTitle`, `reserveTimeSlots`. `roomOptions`
stays until Phase 3 lands (booking flow still reads it). `experiences` and anything
Experiences-rail-specific is explicitly not retired (decision #2).

**Files:** the new models above, `controllers/home/home_controller.dart`,
`controllers/home/discover_controller.dart`,
`controllers/dining/restaurant_controller.dart`,
`controllers/home/services_controller.dart` (catalog-fetch half), matching `views/`
argument-shape adjustments (uuid instead of full object).

**Tests:** update `discover_filter_test.dart` — filtering assertions move from
"filters an in-memory list" to "requests the right page/params."

### ✅ Delivered (with reasoned deviations)

**Wired:**
- **DTOs:** `room_type`, `dining_venue`, `menu` (unified — the parallel demo
  `menu_item.dart` was deleted and its `MenuItem`/`MenuCategory` collapsed into the
  DTO), `service_catalog_item`, `review`, `home_slider`, `amenity`, plus value types
  `bilingual`/`media_image` (Phase 0).
- **Home** (`home_controller`): rooms + dining rails → `GET /public/room-types` +
  `/public/dining-venues` (bounded first-page `Future.wait`). Experiences rail stays
  demo (decision #2). Hero remains the looping video (there is no slider UI surface
  for `/public/home-sliders`, so it was not wired — noted below).
- **Discover** (`discover_controller`): fetches by `section`; the fake category chips
  were dropped (they mapped to nothing server-side). First-page fetch, not the full
  `PaginatedControllerMixin` — Discover is section-polymorphic (T differs per
  section), which the single-T mixin doesn't fit; infinite-scroll is a follow-up.
- **Dining menu** (`restaurant_controller` + `restaurant_menu_tab` + menu tile):
  `GET /public/dining-venues/{uuid}/menu-categories` + `/menu`, filtered client-side
  by category slug; loading + "menu coming soon" empty states. Tile now renders the
  DTO (network `photo` via `CustomImage`, bilingual name/desc, `is_vegan` → Vegan
  pill). `DemoData.restaurantMenu` retired.

**Deferred (deliberate):**
- **Services-catalog fetch (item 5) → Phase 5.** The hub grid tiles are hand-built,
  Figma-matched decorative-photo tiles (`DemoData.services`) whose geometry the
  `/public/service-catalog` array does not carry; re-driving the grid from the
  catalog is a *design* change, not a data swap, and would discard extracted-icon
  work. The plan itself consumes the catalog `kind`-switch in Phase 5 (submission),
  so the fetch lands there alongside what each `kind` actually does.
- **Dining about/gallery/rating hero copy** stay on `DemoData` — they map to the
  venue *detail* endpoint (description/images), a second fetch; not in the retire
  list, low value without the menu already proving the pattern. Follow-up.
- **`/public/home-sliders`** — no consuming UI (Home hero is a video). Not wired.

**demo_data retired this phase:** only `restaurantMenu` (superseded by the menu
fetch). `rooms`/`restaurants` stay — still load-bearing for room details
(`roomDetailsFor`, Phase 3) and the restaurant-tile/controller fallback.
`services`/`serviceCategoryByName`/`restaurantServiceTitle`/`reserveTimeSlots` stay —
their consumers (services hub, reserve tab) wire in Phase 5. Deleting demo members
whose consumer isn't wired yet would break the build, so retirement tracks wiring.

**Tests:** `discover_filter_test.dart` deleted (it guarded the removed client-side
category filter); replaced by `phase2_content_test.dart` guarding the DTO parsing
(RoomType/DiningVenue/MenuCategory/MenuItem `fromJson`, banner sort-order fallback,
numeric coercion, vegan/price defaults) — hermetic, no `SettingsService`/`GetStorage`.

---

## Phase 3 — Booking

**Depends on:** Phase 1 (token for the one-step reservation endpoint) + Phase 2
(RoomType DTO).

1. **`GET /public/availability` + `GET /public/quote`** — public, no auth
   dependency, land first. This replaces `booking_flow_controller.dart`'s entire
   client-side pricing engine (flat `taxRate=0.15`/`promoRate=0.10` constants,
   `applyPromo()` currently accepting any non-empty string as valid) — the
   controller trusts whatever the quote endpoint returns; a bad/expired promo now
   surfaces `invalid_promo` (422) inline instead of always succeeding.
2. **`POST /reservations`** — `confirmBooking()` stops calling
   `DemoData.newConfirmationCode()` and posts `{room_type_uuid, check_in, check_out,
   payment_method, promo_code?}`, storing the real `booking_code`/`status`/
   `hold_expires_at`. Apply the payment-method mapping from decision #1 (cash→cash,
   payAtHotel→on_arrival; card/Apple/Google Pay show a "not available yet" state
   instead of submitting).
3. **`GET /reservations` + `GET /reservations/{uuid}` + `DELETE /reservations/{uuid}`**
   — the Reservation DTO built here (replacing the current 2-field `{code,lastName}`
   stub) is what Phase 4 (Stays) consumes; must land in this phase, not redone later.

**Files:** `lib/models/reservation.dart` (rewritten), `lib/models/quote.dart` (new,
availability+quote shape), `controllers/booking/booking_flow_controller.dart` (major
rewrite — pricing getters removed, confirmBooking/applyPromo become async),
`views/book/review_booking_view.dart` (reads real quote breakdown),
`views/book/booking_confirmed_view.dart` (reads real booking_code).
`views/book/payment_view.dart` gets the disabled-state treatment for
card/Apple/Google Pay, not a full rebuild (per decision #1, the UI stays).

**demo_data.dart retired:** `taxRate`, `promoRate`, `newConfirmationCode()`,
`bookingAdults`/`bookingChildren`/`bookingCheckIn`/`bookingCheckOut` (become live
state), `roomOptions` (now safe to delete — Phase 2 covered display, this phase
covers booking). `addOns` — **flag for the reviewer:** no backend concept of
add-ons exists anywhere in the API guide; leave `addOns` as still-mock/UI-only
unless/until confirmed otherwise, same treatment as Experiences.

**Tests:** rewrite `booking_pricing_test.dart` — old premise (asserting
client-computed tax/promo) is invalid; new version mocks `GET /public/quote` and
asserts the controller surfaces exactly what the mock returns (i.e., proves it no
longer recomputes anything).

### ✅ Delivered (analyze clean, tests pass)

- **`models/reservation.dart`** rewritten to the real DTO (`uuid`, `bookingCode`,
  `status`, `checkIn`/`checkOut`, `nights`, `source`, `paymentMethod`, `totalUsd`,
  `holdExpiresAt`) + `isCancellable` (status-derived) + `listFromJson`. **Phase 4
  consumes this.**
- **`models/quote.dart`** new: `Quote` (`daily_rate_usd`, `nights`, `subtotal_usd`,
  `discount_usd`, `total_usd`, `promo_code_id`, `rules_applied`; `hasPromo`) +
  `Availability`. Note: the quote returns **no separate tax line** — total already
  nets the discount.
- **`booking_flow_controller`**: the client tax/promo engine is gone. `_fetchQuote()`
  hits `GET /public/quote` (fired on entering Payment + on `applyPromo`); the
  breakdown/total read the quote (with a room×nights estimate fallback for a
  pure-demo room). `applyPromo` is async and surfaces `invalid_promo` inline
  (`promoError`) instead of always "succeeding". `confirmBooking` is async →
  `POST /reservations` with the real `booking_code`; on success it flips the guest's
  `hasBooking` entitlement (Home/Services active-booking state). Payment mapping
  (decision #1): only Pay-at-Hotel submits (→ `on_arrival`); card/Apple/Google Pay
  are blocked at confirm with a clear message. Errors branch on `errorCode`
  (`no_availability`/`invalid_promo`/default).
- **`room_details_view`**: "Select This Room" (was a no-op) now starts a booking with
  the room preselected, threading the real `room_type_uuid` (added to `RoomOption` +
  `roomDetailsFor`) into the quote/POST.
- **Views**: `booking_price_breakdown` + `booking_summary_header` read the quote;
  `payment_view` gained the disabled "not available yet" treatment for card/wallets.

**Retired:** `taxRate`, `promoRate`, `newConfirmationCode()`, `bookingAdults/Children/
CheckIn/CheckOut/FirstDay/LastDay` (+ the now-unused `dart:math` import).
**Kept (deferred):** `roomOptions`/`addOns` — the choose-room list + add-ons step are
still demo (add-ons have no backend concept per the plan; choose-room isn't wired this
phase, so bookings started from the Book-tab list carry no uuid and fall back to the
estimate). `GET /reservations` + `DELETE /reservations/{uuid}` wrappers land in Phase 4
(StaysController), against this Reservation DTO. **Tests:** `booking_pricing_test.dart`
rewritten hermetically (Quote/Reservation `fromJson`, `isCancellable`, payment mapping,
money formatting, estimate fallback) — no HTTP.

---

## Phase 4 — Stays, Folio & Express Checkout

**Depends on:** Phase 1 + Phase 3 (Reservation DTO, booking_code).

**Order:** `GET /stays/active` → `GET /stays/upcoming` → `GET /stays/past`
(paginated) → receipt (`GET /stays/{uuid}/receipt` + `/receipt/pdf`) → DND
(`PATCH /stays/active/dnd`) → folio (`GET /folio`, `POST /folio/approve`,
`POST /transport-requests`). Active/upcoming/past are independent reads, safest to
validate first; receipt needs a real stay UUID; DND/folio are the two "active-stay
only" mutations, sequenced last since they depend on the active-stay screen already
rendering real data.

`stays_controller.dart`: replace field-initializer demo loads with async fetches —
active is a single nullable-object fetch, upcoming a plain array fetch, past mixes
in `PaginatedControllerMixin`. Add loading/empty/error states to
`views/stays/stays_view.dart` (none exist today — this is real UI work, not just
controller wiring). Cancel → `DELETE /reservations/{uuid}`, branching
`reservation_state` (422) into an inline error instead of unconditional local
removal. Receipt sheet → `GET /stays/{uuid}/receipt` (JSON); PDF download →
`ApiService.find.downloadFile` (raw DioException, not the envelope — wrap in its own
try/catch, replacing the current "coming soon" stub). `requestService`/
`expressCheckout` stubs → real navigation / `POST /folio/approve`.

DND's natural home is Home's active-stay dashboard (`HomeController`, where a
local-only toggle already exists) — wire `PATCH /stays/active/dnd` there directly
(one `ApiService.put` call; not worth a shared service per the "no unneeded
abstraction" principle, even though the service-catalog's `kind:toggle` tile from
Phase 2 will also need to trigger it — duplicate the one-liner rather than build a
shared DND service for two call sites).

**Files:** `lib/models/stay.dart` → split into three distinct small classes
(`ActiveStay`, `UpcomingStay`, `PastStay` — these are three genuinely different
response shapes per the guide, not one nullable-heavy class), `lib/models/folio.dart`,
`lib/models/receipt.dart`, `controllers/stays/stays_controller.dart`,
`controllers/home/home_controller.dart` (DND action), `views/stays/stays_view.dart`,
`components/sheets/receipt_sheet.dart` / `cancel_reservation_sheet.dart` (already
present as their own components).

**demo_data.dart retired:** `activeStay()`, `upcomingStays()`, `pastStays()`,
`currentBillLines`, `currentBillTotal`.

**Tests:** new `stays_controller_test.dart` — asserts cancel is blocked with the
right error on a mocked `reservation_state` response, and that active/upcoming/past
load independently (one failing doesn't blank the other two).

---

## Phase 5 — In-stay & pre-arrival services

**Depends on:** Phase 1 (tier-3a/3b gating) + Phase 2 (service-catalog DTO) + Phase
4 (active-stay concept).

**Order:** service-catalog action wiring (the `kind` switch from Phase 2) →
`POST /service-requests` → `GET /service-requests` (mine, paginated) →
`POST /service-bookings` (spa/table/cabana/transfer) →
`POST /dining-venues/{uuid}/table-reservations` (dining reserve-tab, deferred from
Phase 2) → `POST /pre-arrival/documents` (multipart, entirely new screen).

`services_controller.dart`: `quickRequest`/`openServiceRequest`/`editRequest` →
real `POST /service-requests` calls; `GET /service-requests` backs the
active-requests list. Remove `cancelRequest` and its confirm dialog entirely per
decision #4.

`restaurant_controller.dart` (continuing from Phase 2): `confirmReservation()` →
`POST /dining-venues/{uuid}/table-reservations {date, time, guest_count,
special_request?}`.

**Pre-arrival documents** — no existing controller/view at all; new
`controllers/booking/pre_arrival_documents_controller.dart`, matching view,
`Routes.preArrivalDocuments` + binding entry. Uses `FileUploader.postWithFiles`.
Implementation-time verification needed, not a product decision: the endpoint
expects `documents:[{type,file}]` (array of `{type, file}` objects); Laravel's
expected multipart bracket convention (`documents[0][type]`/`documents[0][file]` vs
`documents[][type]`/`documents[][file]`) must be confirmed against the actual
backend FormRequest validation before writing the upload call — a wrong guess
either 422s or silently drops files. `FileUploader`'s current signature doesn't
naturally express "N files each paired with a sibling scalar field sharing an
index," so it likely needs a small dedicated method rather than a genericization of
`postWithFiles`.

**Files:** `lib/models/service_request.dart` (rewritten: uuid, type, department,
status, priority, notes, created_at, category_code, service_item?),
`lib/models/service_booking.dart` (new), `lib/models/pre_arrival_document.dart`
(new), `controllers/home/services_controller.dart`,
`controllers/dining/restaurant_controller.dart`, new pre-arrival
controller/view/route/binding.

**demo_data.dart retired:** `serviceCategories`, `initialActiveRequests()`,
`homeActiveRequests()`.

**Tests:** update `service_request_ui_test.dart` for the removed cancel affordance
(a reverted removal that brings the button back should fail this test). New
`pre_arrival_upload_test.dart` asserting the multipart field names match the agreed
convention once confirmed.

---

## Phase 6 — Chat & notifications

**Depends on:** Phase 1 only. Can run in parallel with Phases 3–5.

`ai_concierge_controller.dart`: tab 0 ("AI Concierge") stays exactly as today's
"coming soon" stub — the P11 chatbot isn't built server-side, don't touch beyond a
clarifying code comment. Tab 1 ("Customer Service") is the real staff-chat surface:
`GET /conversations` (take the first item — "in practice one" per the guide, not
worth the pagination mixin for a single-conversation list),
`GET /conversations/{uuid}/messages` (paginated, oldest-first — worth the mixin here
for a long-running thread), `send()` → `POST /conversations {body}`. Attachment
support (image, max 5MB) is a genuinely new input-row affordance, not currently
present at all.

Device-token registration (`services/notifications_service.dart`) currently POSTs
to `/user/device-token` with `{device_token}` — both the path and body shape are
CartX leftovers. Fix to `POST /device-tokens {token, platform}`; the existing
idempotency check (only send when the stored token differs from the last registered
one) is already correct, just needs the right endpoint. `removeToken()`'s
`DELETE /user/device-token` has no documented counterpart in the guide's
Notifications section — drop this call rather than guess at an undocumented endpoint
(flag as a fact to confirm with the backend if push-token cleanup on logout turns
out to matter).

**Files:** extend `lib/models/chat_message.dart` (uuid, sender_type,
attachment_url, created_at), new `lib/models/conversation.dart`,
`controllers/home/ai_concierge_controller.dart`,
`services/notifications_service.dart`.

**demo_data.dart retired:** `customerServiceThread()`. (`aiSuggestions` stays as a
small const in the controller itself, tab 0 remains unwired.)

Firestore live-mirror subscription is an explicit stretch item per the guide (MySQL
via REST is the source of truth) — not built in this plan.

**Tests:** new `chat_controller_test.dart` — asserts tab 0 remains a stub
(regression guard against accidentally wiring the not-yet-built chatbot endpoint)
and tab 1's `send()` posts the right body shape.

---

## Phase 7 — Reviews

**Depends on:** Phase 2 (room-type/dining-venue detail screens) + Phase 1
(submitting is tier-2). Can run in parallel with Phases 4–6.

(Event Inquiry dropped from scope per decision #3 — it only makes sense attached to
an Event Space detail screen, which isn't being built.)

No existing controller/view owns reviews — net-new UI.
`POST /reviews/{room_type|dining_venue}/{uuid}` (201 first submission / 200 on edit
— same envelope shape, differ only in status code, don't branch UI logic on which
one) + `GET /public/reviews/{...}/{uuid}` (paginated). Recommend: extend
`RestaurantController`'s existing tab set (already Menu/Info/Reserve) with a fourth
Reviews tab; add a review-submit bottom sheet to Room Details following the existing
`room_details_sheet.dart` pattern.

**Files:** `lib/models/review.dart` (from Phase 2, reused), new
review-list/review-submit components under `components/sheets/` or
`components/reviews/`.

**Tests:** `review_submit_test.dart` — asserts 201 and 200 responses are both
treated as success (a plausible regression is branching on `statusCode == 201` and
silently failing the edit path).

---

## Verification approach (applies across all phases)

- `flutter analyze` clean and `flutter test` passing before any phase is declared
  done — never "should work."
- Every phase adds or updates at least one test that would fail if its core change
  were reverted (table above per phase); Phase 1's `middleware_service_test.dart` is
  the repo's first HTTP-mocked test — pick one Dio test-double approach there and
  reuse it for every later controller test that touches `ApiService`.
- Run the actual app against the local Laravel backend (`php artisan migrate:fresh
  --seed` per the guide's demo data, `adb reverse tcp:8000 tcp:8000` for device
  testing) for at least the golden path of each phase's screens before moving on —
  type-checking and unit tests confirm code correctness, not that a screen actually
  renders real data end-to-end.
- Test in Arabic as well as English when touching any screen with bilingual content
  fields (`Bilingual.value`) or RTL-sensitive layout, per the existing repo
  convention.

## Critical files (for orientation, not exhaustive)

- `mobile/lib/services/api/api_service.dart`, `api_client.dart` — networking core
  (Phase 0 only touches the host default)
- `mobile/lib/services/middleware_service.dart`, `session_service.dart` — auth
  unification (Phase 1)
- `mobile/lib/models/api/api_response.dart`, `constants/error_codes.dart` —
  envelope/error foundation (Phase 0)
- `mobile/lib/mixins/paginated_controller_mixin.dart` — reused by Discover,
  past-stays, service-requests, conversation messages, reviews
- `mobile/lib/controllers/auth/otp_verify_controller.dart` — auth flow linchpin
  (Phase 1)
- `mobile/lib/controllers/booking/booking_flow_controller.dart` — booking rewrite
  (Phase 3)
- `mobile/lib/controllers/stays/stays_controller.dart` — stays/folio rewrite
  (Phase 4)
- `mobile/lib/constants/demo_data.dart` — the shrinking mock-data file tracked phase
  by phase
