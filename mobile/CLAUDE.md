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

There is currently no `test/` directory — no tests exist yet in this project.

## Architecture

Flutter app using **GetX** for state management, DI, and routing. Every screen follows the same triad:

- `views/<feature>/<name>_view.dart` — `GetView<XController>` or `StatelessWidget` wrapped in `GetBuilder<XController>`. Views are dumb; all logic lives in controllers.
- `controllers/<feature>/<name>_controller.dart` — `GetxController`, calls `update()` to rerender (this codebase uses `GetBuilder`, not `.obs`/`Obx`, except for cross-cutting services like `MiddlewareService`/`PaginatedControllerMixin` which use `Rx` types).
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

Call sites use `ApiService.find.get/post/put/delete<T>(...)`, which return the **unwrapped `data` field** of the response envelope as `ApiResponse<T>`, and throw `ApiException` on any non-2xx or network failure. Error dialogs are shown automatically by default; pass `showErrorDialog: false` to suppress (e.g. for inline form errors). Loading dialogs are opt-in via `showLoading: true`.

`ApiException` wraps the backend's standard error envelope (`message`, `error_code`, `context`, `errors`, `request_id`). **Branch UI logic on `errorCode` (see `constants/error_codes.dart`), never on `message` or raw HTTP status** — convenience predicates like `isValidation`, `isBusinessRule`, `isRateLimited`, `isNetworkError` exist for this. Always include a `default` case when switching on error codes since the backend can add new ones.

File uploads/downloads are delegated to `FileUploader`/`FileDownloader` (`services/api/upload_download/`) and bypass the envelope — they throw raw `DioException`, not `ApiException`.

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

- `customWidgets/custom_*.dart` — shared UI kit (buttons, cards, dialogs, snackbars, scaffold, image loader). Check here before writing a new low-level widget.
- `models/api/api_response.dart`, `api_exception.dart`, `paginated_meta.dart` — response envelope types shared by all API calls.
- `mixins/paginated_controller_mixin.dart` — mix into a controller for infinite-scroll list screens; implement `fetchPage()` and it manages `items`/`loading`/`loadingMore`/`hasMore`/scroll-triggered loading.
- `constants/storage_keys.dart` — single source of truth for `GetStorage` key names; add new persisted keys here rather than inlining strings.
- `theme/app_colors.dart` + `theme/theme.dart` — central color/typography source; fonts are Plus Jakarta Sans (UI) and The Seasons (display/serif accents).
- Supports `en`/`ar` locales including RTL; test new screens in Arabic when touching layout.
