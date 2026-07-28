import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:get/get.dart';

/// Single source of truth for the guest session — the bearer token plus the
/// authenticated [Guest] identity and its entitlement flags. Permanent
/// singleton (see main.dart). Replaces the old dual auth mechanism
/// (SessionService's fake booleans + a mis-directed token check).
class MiddlewareService extends GetxService {
  late bool isFirstTime;

  /// The authenticated guest, or null when signed out. Hydrated from the cache
  /// on cold start, then refreshed by [checkToken] against `/auth/guest/me`.
  final Rx<Guest?> guest = Rx<Guest?>(null);

  MiddlewareCases middlewareCase = MiddlewareCases.noToken;

  static MiddlewareService get find => Get.find();

  @override
  void onInit() {
    super.onInit();
    isFirstTime = StorageService.getBool(StorageKeys.isFirstTime) ?? true;
    _hydrateGuestFromCache();
  }

  // ── Session state (read across the app) ───────────────────────────────────
  bool get isAuthenticated => guest.value != null;
  bool get hasBooking => guest.value?.hasBooking ?? false;
  bool get isCheckedIn => guest.value?.isCheckedIn ?? false;

  bool get isTokenValid => middlewareCase == MiddlewareCases.validToken;
  bool get isTokenInvalid => middlewareCase == MiddlewareCases.invalidToken;
  bool get hasNoToken => middlewareCase == MiddlewareCases.noToken;

  // ── Lifecycle ─────────────────────────────────────────────────────────────

  /// Refreshes the guest + entitlements from `/auth/guest/me`. Called on splash
  /// after cache hydration. A network/server hiccup keeps the cached session —
  /// only a real 401 signs the guest out (handled by the error interceptor →
  /// [ApiService] `_handleUnauthorized`).
  Future<void> checkToken() async {
    final token = StorageService.getString(StorageKeys.token);
    if (token == null || token.isEmpty) {
      middlewareCase = MiddlewareCases.noToken;
      return;
    }
    final response = await ApiService.find.get<Map<String, dynamic>>(
      path: '/auth/guest/me',
      showErrorDialog: false,
    );
    if (response.statusCode == 200 && response.data != null) {
      _setGuest(Guest.fromJson(response.data!));
      middlewareCase = MiddlewareCases.validToken;
    } else if (response.statusCode == 401) {
      middlewareCase = MiddlewareCases.invalidToken;
    } else {
      middlewareCase = MiddlewareCases.validToken;
    }
  }

  /// The only place a token is written — from OTP verify on success. Mirrors
  /// the guest's `preferred_locale` into the app on first sign-in.
  Future<void> saveSession({
    required String token,
    required Guest guest,
  }) async {
    await StorageService.setString(StorageKeys.token, token);
    _setGuest(guest);
    middlewareCase = MiddlewareCases.validToken;
    await SettingsService.find.setLocaleFromCode(guest.preferredLocale);
  }

  /// Merge a fresh guest (e.g. after a profile edit) into the session.
  void updateGuest(Guest next) => _setGuest(next);

  /// Clears token + guest everywhere. Called on explicit sign-out and on a 401.
  Future<void> signOut() async {
    await StorageService.remove(StorageKeys.token);
    await StorageService.remove(StorageKeys.guest);
    guest.value = null;
    middlewareCase = MiddlewareCases.noToken;
  }

  // ── Private ───────────────────────────────────────────────────────────────
  void _setGuest(Guest next) {
    guest.value = next;
    StorageService.write(StorageKeys.guest, next.toJson());
  }

  void _hydrateGuestFromCache() {
    final cached = StorageService.read<Map>(StorageKeys.guest);
    if (cached == null) return;
    guest.value = Guest.fromJson(Map<String, dynamic>.from(cached));
    middlewareCase = MiddlewareCases.validToken;
  }
}
