# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

This file covers the `mobile/` directory only — a Flutter app (package name `carlton`, Android app ID `com.tupcode.carlton`).

## Commands

Run all commands from within `mobile/`.

```
flutter pub get                        # install dependencies
flutter run                            # run on connected device/emulator
flutter analyze                        # lint (flutter_lints via analysis_options.yaml)
flutter test                           # run tests
flutter test test/some_test.dart       # run a single test file
flutter build apk / ios / windows      # platform builds
```

`test/` holds ~95 targeted tests — model `fromJson` parsing, pricing math, and a couple of widget flows. Coverage is deliberately narrow: **no test pumps a full screen**, so view-level changes (rebuild scoping, loading/empty states, navigation) are verified by `flutter analyze` and by running the app, not by the suite. Don't assume a UI change is covered.

## Architecture

Flutter app using **GetX** for state management, DI, and routing. Every screen follows the same triad:

- `views/<feature>/<name>_view.dart` — `GetView<XController>`, or a `StatelessWidget` that resolves its controller with `Get.find`. Views are dumb; all logic lives in controllers.
- `controllers/<feature>/<name>_controller.dart` — `GetxController` holding `Rx` state (`RxInt`, `RxString`, `RxList`, `Rxn<T>`, …). **There is no `GetBuilder` and no `update()` anywhere in the app** — every observed field is `Rx` and every observing widget is an `Obx`. Don't reintroduce either.

  **Rebuild scoping.** An `Obx` rebuilds everything inside its callback and subscribes to exactly the `.value` reads that happen during it, so put it around the smallest widget that reads the state — never around a whole screen. Two consequences worth knowing:

  - Reading a plain (non-`Rx`) field inside `Obx` compiles and yields the current value, but subscribes to nothing. That's deliberate in places — `BookingFlowController.focusedDay` is plain so swiping the calendar's month doesn't fight the widget's own state.
  - `TextEditingController.text` is not observable. Where a CTA depends on typed text, the controller pushes the result into an `Rx` instead: see `BookingFlowController.isCardComplete`, recomputed by `onPaymentFieldChanged()`.

  **Collections:** declare as `final xs = <T>[].obs`. `RxList`/`RxSet` notify from `add`/`insert`/`remove`/`removeWhere`/`clear`, so in-place mutation is fine — but prefer `assignAll(...)` over `clear()` + `addAll(...)`, which notifies twice and renders one empty frame.
- `bindings/binding.dart` — single file, one `Bindings` class per route, registered via `Get.lazyPut`. `MainBinding` uses `fenix: true` for tab controllers so they survive being recreated. Add new controllers here, not inline in views.

Routes are centralized in two files:
- `routes/routes.dart` — `Routes` (route name constants) + `Pages.getPages` (route → view + binding wiring). Add both a `Routes.xxx` constant and a `GetPage` entry here for any new screen.
- `middleware/middleware.dart` — a `GetMiddleware` that reads `MiddlewareService` for redirect logic; currently a no-op scaffold (both branches empty).

`main.dart` bootstraps services in a fixed order before `runApp`: `StorageService.init()` → `SettingsService` → `ApiService` → `MiddlewareService` → `PermissionService`, all `Get.put(..., permanent: true)`. Add new global singletons here in dependency order.

### Networking (`services/api/`)

`ApiService` (GetxService) wraps a `Dio` client built by `ApiClient.build()`. Interceptor order matters and is documented inline in `api_client.dart`:
1. `ConnectivityInterceptor` — rejects early if offline
2. `HeadersInterceptor` — stamps Accept-Language, X-Request-Id
3. `AuthInterceptor` — injects Bearer token
4. `CustomPrettyDioLogger` — debug builds only
5. `RetryInterceptor` — retries transient failures on the raw `DioException`
6. `ErrorInterceptor` — converts final failure into `ApiException` (always last)

Call sites use `ApiService.find.get/post/put/delete<T>(...)`, which return the **unwrapped `data` field** of the response envelope as `ApiResponse<T>`. **These methods never throw** — guard on `statusCode`/`.ok` and read `data!` inside the guard; there is no try/catch anywhere in the app.

**UI feedback from the API layer: dialogs only, and only when asked for.** `ApiDialogHandler` never raises a snackbar — every failure it reports needs acknowledgement, and a toast is too easy to miss. It shows nothing unless the call site opted in: `showErrorDialog` (default true) on the HTTP methods, `showDialog` (default true) on `FileUploader`/`FileDownloader`. Pass false to stay silent, then optionally report yourself with `dialogs.showError(res.error!)` after running your own logic (revert an optimistic update, set an inline error). Loading dialogs are opt-in via `showLoading: true` — don't use one for a tap that should feel instant, such as opening a room from a list; use a re-entrancy guard plus an inline state instead (`BookingFlowController.openingRoom`).

Controllers may still use `CustomSnackbars` for their own non-API feedback ("Code copied", "Request submitted"). That rule is about the API layer only.

`ApiException` wraps the backend's standard error envelope (`message`, `error_code`, `context`, `errors`, `request_id`). **Branch UI logic on `errorCode` (see `constants/error_codes.dart`), never on `message` or raw HTTP status** — convenience predicates like `isValidation`, `isBusinessRule`, `isRateLimited`, `isNetworkError` exist for this. Always include a `default` case when switching on error codes since the backend can add new ones.

File uploads/downloads are delegated to `FileUploader`/`FileDownloader` (`services/api/upload_donwload/` — the folder name is misspelled in the repo) and bypass the envelope: they throw raw `DioException`, not `ApiException`.

**`showErrorDialog: false` hides the failure from the user *and* from your controller.** A request that fails silently leaves the target list empty, which is indistinguishable from a successful empty response. Any controller that suppresses the dialog and renders a list needs its own error flag, or the view cannot tell "nothing here" from "couldn't load" — see `HomeController.contentError`, set from `!roomsRes.ok || !diningRes.ok`.

### Demo/mock state — important caveat

**No backend is wired up yet.** Several pieces of "real" architecture (API client, error handling) coexist with hardcoded demo flows:
- `constants/demo_data.dart` — every hardcoded value (rooms, restaurants, services, OTP code `123456`, network delays) lives here so a real API integration is a single-file hunt. Nothing in this file should survive integration.
- `SessionService` (`services/session_service.dart`) — persists fake "signed in"/"has reservation" booleans via `StorageService`, explicitly marked demo-only. A real backend would store an actual auth token instead.
- `MiddlewareService` (`services/middleware_service.dart`) — in parallel, implements real token-based auth checking (`/user/check-token`, `StorageKeys.token`). These two auth mechanisms are not yet unified — be aware of which one a given screen actually reads before changing auth-adjacent logic.
- Controllers marked `/// Demo-only` in their doc comment (e.g. `SignInController`) simulate network delay via `DemoData.networkDelay` and route forward optimistically rather than calling `ApiService`.

### Localization

Two-file split under `l10n/`:
- `local.dart` — the raw `en`/`ar` key→string maps (implements GetX `Translations`).
- `app_translations.dart` — a typed façade exposing each key as a static getter (e.g. `AppTranslations.signInTitle` → `'auth.signInTitle'.tr`). **Always add strings through both files** and reference them via `AppTranslations.xxx` in views/controllers, never raw `.tr` key strings.

Note: many keys in `local.dart` (e.g. `auth.joinCartXForTheBestDeals`, `fileService.*`) are leftover from a prior "CartX" marketplace template this project was bootstrapped from — not all existing keys are relevant to the Carlton Hotel domain; don't treat their presence as a pattern to imitate for hotel-specific features.

### Other conventions

- Widgets currently live in two folders — check both before adding a new one:
  - `customWidgets/custom_*.dart` — buttons, text fields, containers, dialogs, snackbars, scaffold, image loader, validation.
  - `components/` — cards (`components/cards/`), app bar, bottom nav, avatar, chips, badges, request tile, active-stay section.
  - The split isn't a settled rule yet — most `components/` widgets take plain params just like `customWidgets/` ones; only the cards (`CustomListingCard` etc.) are actually parameterized by a domain model (`List<CardMeta>`). Treat `components/` as "recently moved out of `customWidgets/`," not as a principled boundary — check both folders for an existing widget before adding a new one, and don't assume a file's folder tells you why it's there.
- **Widgets take models and callbacks, never controllers.** Nothing under `components/` or `customWidgets/` may declare a `XController` field, extend `GetView`, or call `Get.find` — verified: zero occurrences, and zero imports from `controllers/`. Pass the values a widget renders plus `onX` callbacks; where that would mean many params, pass one model (`BookingPriceSummary`, `CardMeta`). Framework controllers (`TextEditingController`, `ScrollController`, `TabController`, `VideoPlayerController`) are fine to pass — they're owned by the caller because their state must outlive a rebuild.
  - The `Obx` belongs in the **view**, not the widget: the view unwraps `Rx` values and hands the widget one frame's worth of plain data (see `_ReviewsTab` in `restaurant_detail_view.dart`).
  - Navigation and auth gating live in controllers, not widgets — `RestaurantController.openReviewSheet` both auth-gates and navigates, so the tab just calls it.
- **An icon next to a text is `RowTextComponent`** (`customWidgets/custom_texts.dart`), not a hand-rolled `Row`. It renders the icon as *leading*, so a trailing-icon row can't use it. Takes `icon` (IconData), `iconPath` (SVG), or a custom `leading`. Two traps: `spacing` defaults to **10**, so pass `spacing: 0` if the original Row had none; and the SVG branch is `width: iconSize ?? 20, height: iconSize` — the asymmetry is deliberate, since `height: null` preserves aspect for non-square icons (`king_bed`/`space`/`view` are 10 × 11 with `preserveAspectRatio="none"`). Don't "fix" it to `height: iconSize ?? 20` or those get squashed.
- **Loading, empty and error are three different states**, and a list needs all three:
  - loading → **shimmer** shaped like the real content (`shimmer` package, already a dependency; see `_RailShimmer`/`_CardShimmer` in `home_view.dart`), not a spinner, so nothing jumps when data lands.
  - failed → `CustomEmptyPlaceholder` with `cloud_off_outlined` **and a Retry button**.
  - loaded-but-empty → `CustomEmptyPlaceholder` with **no** button; retrying an empty list returns the same list.
  A section that returns `SizedBox.shrink()` while loading reads as "nothing here" and then pops in — give it a placeholder instead.
- **Parse at the model boundary.** `fromJson` converts `created_at` to a `DateTime?` via a local `_date` helper (`models/review.dart`, `models/receipt.dart`); views never hold a raw ISO string or re-parse on rebuild. Format through the shared extensions in `extensions/date_extension.dart` (`formatDate`, `formatDatePicker`, `formatDateMonth`, `formatApiDate`) — add a new one there rather than inlining a `DateFormat`.
- **Validation messages live in the validator.** Add a `validateX` to `CustomValidation` that returns its own `AppTranslations.…` string; never pass error copy in as a parameter. New messages go through both l10n files.
- `models/api/api_response.dart`, `api_exception.dart`, `paginated_meta.dart` — response envelope types shared by all API calls.
- `mixins/paginated_controller_mixin.dart` — mix into a controller for infinite-scroll list screens; implement `fetchPage()` and it manages `items`/`loading`/`loadingMore`/`hasMore`/scroll-triggered loading.
- `constants/storage_keys.dart` — single source of truth for `GetStorage` key names; add new persisted keys here rather than inlining strings.
- `theme/app_colors.dart` + `theme/theme.dart` — central color/typography source; fonts are Plus Jakarta Sans (UI) and The Seasons (display/serif accents).
- Supports `en`/`ar` locales including RTL; test new screens in Arabic when touching layout.

### Assets

**Never pass a literal `assets/…` path to `CustomImage`.** Use `Image.asset` / `SvgPicture.asset` directly. `CustomImage`'s asset branch exists only because `DemoData` still stands in for the API: model-driven sources (`room.images`, `restaurant.imagePath`, `item.photo`, …) are asset paths today and storage URLs after integration, and that branch dies with `DemoData`.

Bundled Figma exports are up to 4× resolution (2 MB+), and `Image.asset` decodes at native pixel size. **Anything full-bleed needs a `cacheWidth`** — screen width × `MediaQuery.devicePixelRatioOf(context)`. Identical on screen, a fraction of the RAM. Small fixed-size images can skip it.

**Before deleting an "unused" asset, check for interpolated paths.** A grep by filename misses them. Today there is exactly one such site: `_amenityAsset` in `models/booking_models.dart`, which builds `'assets/icons/$icon.svg'` from a whitelist (`jacuzzi`, `desk`, `tv`, `coffee`, `butler`, `view`). Those files appear nowhere as literals; deleting them breaks amenity icons at runtime with no compile error and no failing test.

Note `pubspec.yaml` declares whole directories (`assets/icons/`, `assets/images/`, …), so anything dropped in a folder ships — including non-assets like `assets/videos/README.txt`. Two paths are referenced but absent (`assets/images/demo_passport.png`, `assets/videos/carlton_promo.mp4`); the hero video falls back to a network URL, so this degrades rather than crashes.

### Traps worth knowing

Two mistakes made and caught during review — both compile cleanly and neither is covered by a test:

- **Don't name a controller method `refresh()`.** `GetxController` already has one and GetX calls it internally to notify listeners; overriding it to do network work fires your fetches from framework internals. Pull-to-refresh on Home is `HomeController.refreshHome()` for this reason.
- **`MainView` deliberately has no keep-alive.** Its `PageView` tabs are plain widgets — the tab controllers come from `MainBinding` (route-scoped, `fenix`), so a disposed tab subtree re-fetches nothing and loses no controller state. The cost is scroll offsets, since no tab carries a `PageStorageKey`. Re-add a keep-alive only if a tab gains widget state that must survive a switch.

### Home view

`HomeView.sectionsFor()` is a pure selector returning one of three **const** section lists: `exploreSections` (no reservation), `preArrivalSections` (booked, not checked in — the checklist + airport transfer), `reservationSections` (checked in). It must keep returning the *same const instances* — `Element.updateChild` short-circuits the whole subtree when an identical const list comes back, which is what stops an unrelated profile edit from rebuilding every carousel. Don't replace them with computed lists.

The middle state is **pre-arrival** (`CheckInService.isPreArrival`), i.e. before check-in. It is sometimes called "pre-checkout" in conversation; the code name is the accurate one.
